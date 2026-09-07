import type { Server } from "node:http";
import path from "node:path";
import { assertHttpConfig, loadConfig, type AppConfig } from "./config.js";
import { AuthService } from "./auth/service.js";
import { createEmailSender } from "./auth/email.js";
import { createHttpApp } from "./http/app.js";
import { log } from "./log.js";
import { loadSites } from "./sites.js";
import { FileSessionStore } from "./session/file-store.js";
import { MemorySessionStore } from "./session/memory-store.js";
import type { SessionStore } from "./session/store.js";

export function createStore(config: AppConfig): SessionStore {
  if (config.sessionStore === "file") {
    return new FileSessionStore(path.join(config.sessionDir, "sessions.json"));
  }
  return new MemorySessionStore();
}

export async function startHttpServer(config: AppConfig = loadConfig()): Promise<Server> {
  assertHttpConfig(config);

  const loadEnvSites = () => loadSites();

  if (config.httpMode === "personal") {
    const sites = await loadEnvSites();
    if (sites.length === 0) {
      throw new Error(
        "Personal HTTP mode needs WPAGENT_URL + WPAGENT_USERNAME + WPAGENT_APP_PASSWORD (same as stdio).",
      );
    }
  }

  const auth =
    config.httpMode === "hosted"
      ? new AuthService(createStore(config), createEmailSender({
          provider: config.emailProvider,
          from: config.emailFrom,
          resendApiKey: config.resendApiKey,
          postmarkToken: config.postmarkToken,
          nodeEnv: config.nodeEnv,
        }), {
          sessionSecret: config.sessionSecret,
          sessionTtlMs: config.sessionTtlMs,
          authCodeTtlMs: config.authCodeTtlMs,
          pairingTtlMs: config.pairingTtlMs,
          publicUrl: config.publicUrl,
        })
      : undefined;

  const app = createHttpApp({
    config,
    auth,
    loadEnvSites,
  });

  const server = await new Promise<Server>((resolve, reject) => {
    const httpServer = app.listen(config.port, config.host, () => resolve(httpServer));
    httpServer.on("error", reject);
  });

  server.timeout = config.mcpTimeoutMs;
  server.headersTimeout = config.mcpTimeoutMs + 1000;
  server.requestTimeout = config.mcpTimeoutMs;

  log("info", "http_listen", {
    host: config.host,
    port: config.port,
    public_url: config.publicUrl,
    mode: config.httpMode,
    mcp_path: "/mcp",
    email_provider: config.httpMode === "hosted" ? config.emailProvider : "unused",
    session_store: config.httpMode === "hosted" ? config.sessionStore : "unused",
  });

  const shutdown = async (signal: string) => {
    log("info", "http_shutdown", { signal });
    await new Promise<void>((resolve, reject) => {
      server.close((err) => (err ? reject(err) : resolve()));
    });
    process.exit(0);
  };

  process.once("SIGTERM", () => void shutdown("SIGTERM"));
  process.once("SIGINT", () => void shutdown("SIGINT"));

  return server;
}
