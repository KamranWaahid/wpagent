import express, { type Express, type NextFunction, type Request, type Response } from "express";
import { randomUUID } from "node:crypto";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import type { AppConfig } from "../config.js";
import { createServer, SERVER_VERSION } from "../create-server.js";
import { log } from "../log.js";
import type { SiteConfig } from "../sites.js";
import { timingSafeEqualText, tokenPrefix } from "../crypto-util.js";
import type { AuthService } from "../auth/service.js";
import { connectPageHtml } from "./connect-page.js";
import { RateLimiter } from "./rate-limit.js";

export type HttpAppDeps = {
  config: AppConfig;
  auth?: AuthService;
  loadEnvSites: () => Promise<SiteConfig[]>;
  limiter?: RateLimiter;
};

type AuthError = Error & { status?: number; code?: string };

function clientIp(req: Request): string {
  const forwarded = req.headers["x-forwarded-for"];
  if (typeof forwarded === "string" && forwarded.length > 0) {
    return forwarded.split(",")[0].trim();
  }
  return req.ip || req.socket.remoteAddress || "unknown";
}

function bearer(req: Request): string {
  const header = req.headers.authorization;
  if (typeof header === "string" && /^bearer\s+/i.test(header)) {
    return header.replace(/^bearer\s+/i, "").trim();
  }
  return "";
}

function rpcName(body: unknown): string | undefined {
  if (!body || typeof body !== "object") {
    return undefined;
  }
  const payload = body as { method?: string; params?: { name?: string } };
  if (payload.method === "tools/call") {
    return payload.params?.name;
  }
  return payload.method;
}

function fail(res: Response, status: number, code: string, message: string): void {
  res.status(status).json({ error: true, code, message });
}

export function createHttpApp(deps: HttpAppDeps): Express {
  const { config, auth } = deps;
  const limiter = deps.limiter ?? new RateLimiter();
  const app = express();

  if (config.trustProxy) {
    app.set("trust proxy", 1);
  }

  app.disable("x-powered-by");
  app.use(express.json({ limit: config.jsonLimit }));
  app.use((req, res, next) => {
    const requestId = typeof req.headers["x-request-id"] === "string" ? req.headers["x-request-id"] : randomUUID();
    res.locals.requestId = requestId;
    res.setHeader("X-Request-Id", requestId);
    res.setHeader("X-Content-Type-Options", "nosniff");
    res.setHeader("Referrer-Policy", "no-referrer");
    next();
  });

  app.use((req, res, next) => {
    const origin = req.headers.origin;
    const allowAll = config.corsOrigins.includes("*");
    const allowed = allowAll
      ? origin || "*"
      : origin && config.corsOrigins.includes(origin)
        ? origin
        : undefined;
    if (allowed) {
      res.setHeader("Access-Control-Allow-Origin", allowed);
      res.setHeader("Vary", "Origin");
    }
    res.setHeader("Access-Control-Allow-Methods", "GET,POST,DELETE,OPTIONS");
    res.setHeader("Access-Control-Allow-Headers", "Authorization, Content-Type, mcp-session-id, mcp-protocol-version");
    res.setHeader("Access-Control-Expose-Headers", "mcp-session-id");
    if (req.method === "OPTIONS") {
      res.status(204).end();
      return;
    }
    next();
  });

  app.get("/healthz", (_req, res) => {
    res.json({
      ok: true,
      name: "wpagent",
      version: SERVER_VERSION,
      transport: "streamable-http",
      mode: config.httpMode,
      mcp: "/mcp",
    });
  });
  app.get("/health", (_req, res) => {
    res.redirect(307, "/healthz");
  });

  app.get("/connect", (_req, res) => {
    if (!auth) {
      fail(res, 404, "not_found", "Connect page is hosted-mode only. Personal mode uses env credentials.");
      return;
    }
    res.type("html").send(connectPageHtml(config.publicUrl));
  });

  const hostedAuth = (_req: Request, res: Response, next: NextFunction): void => {
    if (!auth) {
      fail(
        res,
        404,
        "not_found",
        "Sign-in is hosted-mode only (WPAGENT_HTTP_MODE=hosted). Personal mode uses WPAGENT_URL credentials — see docs/chatgpt.md.",
      );
      return;
    }
    next();
  };

  app.post("/auth/start", hostedAuth, async (req, res, next) => {
    try {
      const ip = clientIp(req);
      const email = String(req.body?.email ?? "");
      const ipLimit = limiter.hit(`auth:start:ip:${ip}`, config.rateLimitAuthPerWindow, config.rateLimitAuthWindowMs);
      const emailLimit = limiter.hit(
        `auth:start:email:${email.toLowerCase()}`,
        config.rateLimitAuthPerWindow,
        config.rateLimitAuthWindowMs,
      );
      if (!ipLimit.ok || !emailLimit.ok) {
        res.setHeader("Retry-After", String(Math.ceil((ipLimit.ok ? emailLimit.retryAfterMs : ipLimit.retryAfterMs) / 1000)));
        fail(res, 429, "rate_limited", "Too many sign-in attempts. Try again later.");
        return;
      }
      const started = await auth!.start(email);
      log("info", "auth_start", {
        request_id: res.locals.requestId,
        email_domain: email.split("@")[1] ?? "",
        outcome: "ok",
      });
      res.json({
        ok: true,
        expires_in: started.expiresIn,
        email_provider: started.provider,
        message: "If the address is valid, a 6-digit code was sent.",
      });
    } catch (err) {
      next(err);
    }
  });

  app.post("/auth/verify", hostedAuth, async (req, res, next) => {
    try {
      const email = String(req.body?.email ?? "");
      const code = String(req.body?.code ?? "");
      const limit = limiter.hit(
        `auth:verify:${email.toLowerCase()}`,
        config.rateLimitAuthPerWindow * 2,
        config.rateLimitAuthWindowMs,
      );
      if (!limit.ok) {
        fail(res, 429, "rate_limited", "Too many verification attempts.");
        return;
      }
      const verified = await auth!.verify(email, code);
      log("info", "auth_verify", {
        request_id: res.locals.requestId,
        session_id: verified.session.id,
        outcome: "ok",
      });
      res.json({
        ok: true,
        token: verified.token,
        token_type: "Bearer",
        expires_in: verified.expiresIn,
        pairing_code: verified.pairingCode,
        connect_url: verified.connectUrl,
        mcp_url: `${config.publicUrl}/mcp`,
        session: verified.session,
      });
    } catch (err) {
      next(err);
    }
  });

  app.get("/auth/session", hostedAuth, async (req, res, next) => {
    try {
      const session = await auth!.getSessionByToken(bearer(req));
      if (!session) {
        fail(res, 401, "auth", "Invalid or expired session.");
        return;
      }
      res.json({
        ok: true,
        session: {
          id: session.id,
          email: session.email,
          expires_at: session.expiresAt,
          selected_site_id: session.selectedSiteId,
          sites: session.sites.map(({ id, url, username }) => ({ id, url, username })),
        },
      });
    } catch (err) {
      next(err);
    }
  });

  app.delete("/auth/session", hostedAuth, async (req, res, next) => {
    try {
      await auth!.revoke(bearer(req));
      res.json({ ok: true });
    } catch (err) {
      next(err);
    }
  });

  app.post("/auth/pairing", hostedAuth, async (req, res, next) => {
    try {
      const pairing = await auth!.createPairing(bearer(req));
      res.json({ ok: true, pairing_code: pairing.pairingCode, expires_in: pairing.expiresIn });
    } catch (err) {
      next(err);
    }
  });

  app.post("/auth/sites", hostedAuth, async (req, res, next) => {
    try {
      const session = await auth!.attachSite(bearer(req), {
        id: req.body?.id,
        url: String(req.body?.url ?? ""),
        username: String(req.body?.username ?? ""),
        password: String(req.body?.password ?? ""),
      });
      log("info", "site_linked", { request_id: res.locals.requestId, session_id: session.id, outcome: "ok" });
      res.json({ ok: true, session });
    } catch (err) {
      next(err);
    }
  });

  app.post("/auth/link-by-pairing", hostedAuth, async (req, res, next) => {
    try {
      const session = await auth!.attachSiteByPairing(String(req.body?.pairing_code ?? ""), {
        id: req.body?.id,
        url: String(req.body?.url ?? ""),
        username: String(req.body?.username ?? ""),
        password: String(req.body?.password ?? ""),
      });
      log("info", "site_linked_pairing", { request_id: res.locals.requestId, session_id: session.id, outcome: "ok" });
      res.json({ ok: true, session });
    } catch (err) {
      next(err);
    }
  });

  const handleMcp = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    const started = Date.now();
    const requestId = String(res.locals.requestId ?? "");
    const token = bearer(req);
    let sessionId = "";
    let sites: SiteConfig[] = [];
    let selectedSiteId: string | undefined;
    let onSelectSite: ((id: string) => Promise<void>) | undefined;

    try {
      if (token && auth) {
        const session = await auth.getSessionByToken(token);
        if (!session) {
          fail(res, 401, "auth", "Invalid or expired session.");
          return;
        }
        sessionId = session.id;
        sites = auth.decryptedSites(session);
        selectedSiteId = session.selectedSiteId;
        onSelectSite = async (id: string) => {
          await auth.setSelectedSite(token, id);
        };
      } else if (config.sharedToken) {
        if (!token || !timingSafeEqualText(token, config.sharedToken)) {
          const failLimit = limiter.hit(
            `mcp:authfail:${clientIp(req)}`,
            Math.max(10, config.rateLimitMcpPerWindow),
            config.rateLimitMcpWindowMs,
          );
          if (!failLimit.ok) {
            res.setHeader("Retry-After", String(Math.ceil(failLimit.retryAfterMs / 1000)));
            fail(res, 429, "rate_limited", "Too many failed MCP auth attempts.");
            return;
          }
          fail(res, 401, "auth", "Bearer token required. Set Authorization to the same value as WPAGENT_HTTP_TOKEN.");
          return;
        }
        sites = await deps.loadEnvSites();
      } else if (config.allowAnonEnvSites) {
        sites = await deps.loadEnvSites();
      } else {
        fail(res, 401, "auth", "Bearer token required. Set WPAGENT_HTTP_TOKEN and send Authorization: Bearer …");
        return;
      }

      const limited = limiter.hit(
        sessionId ? `mcp:session:${sessionId}` : `mcp:ip:${clientIp(req)}`,
        config.rateLimitMcpPerWindow,
        config.rateLimitMcpWindowMs,
      );
      if (!limited.ok) {
        res.setHeader("Retry-After", String(Math.ceil(limited.retryAfterMs / 1000)));
        fail(res, 429, "rate_limited", "Too many MCP requests.");
        return;
      }

      const mcp = await createServer({
        sites,
        selectedSiteId,
        useEnvSites: false,
        onSelectSite,
      });
      const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });
      await mcp.connect(transport);
      await transport.handleRequest(req, res, req.body);
      log("info", "mcp_request", {
        request_id: requestId,
        session_id: sessionId,
        tool: rpcName(req.body),
        latency_ms: Date.now() - started,
        outcome: "ok",
        token_prefix: token ? tokenPrefix(token) : null,
      });
    } catch (err) {
      log("error", "mcp_request", {
        request_id: requestId,
        session_id: sessionId,
        tool: rpcName(req.body),
        latency_ms: Date.now() - started,
        outcome: "error",
      });
      next(err);
    }
  };

  app.post("/mcp", handleMcp);
  app.get("/mcp", handleMcp);
  app.delete("/mcp", handleMcp);

  app.use((err: unknown, _req: Request, res: Response, _next: NextFunction) => {
    const mapped = err as AuthError;
    const status = mapped.status ?? 500;
    const code = mapped.code ?? (status >= 500 ? "error" : "validation");
    const message =
      status >= 500 ? "Internal server error." : mapped.message || "Request failed.";
    if (status >= 500) {
      log("error", "http_error", {
        request_id: res.locals.requestId,
        outcome: "error",
        message: mapped.message,
      });
    }
    if (!res.headersSent) {
      fail(res, status, code, message);
    }
  });

  return app;
}
