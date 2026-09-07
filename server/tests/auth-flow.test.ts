import { afterEach, describe, expect, it } from "vitest";
import type { Server } from "node:http";
import { AddressInfo } from "node:net";
import { ConsoleEmailSender } from "../src/auth/email.js";
import { AuthService } from "../src/auth/service.js";
import { loadConfig } from "../src/config.js";
import { createHttpApp } from "../src/http/app.js";
import { RateLimiter } from "../src/http/rate-limit.js";
import { MemorySessionStore } from "../src/session/memory-store.js";

function codeFrom(mailer: ConsoleEmailSender): string {
  const last = mailer.sent.at(-1);
  const match = last?.text.match(/\b(\d{6})\b/);
  if (!match) {
    throw new Error("No code in email");
  }
  return match[1];
}

async function listen(app: ReturnType<typeof createHttpApp>): Promise<{ server: Server; url: string }> {
  const server = await new Promise<Server>((resolve) => {
    const httpServer = app.listen(0, "127.0.0.1", () => resolve(httpServer));
  });
  const addr = server.address() as AddressInfo;
  return { server, url: `http://127.0.0.1:${addr.port}` };
}

describe("Phase 3 sign-in flow", () => {
  let server: Server | undefined;

  afterEach(async () => {
    if (server) {
      await new Promise<void>((resolve, reject) => {
        server?.close((err) => (err ? reject(err) : resolve()));
      });
      server = undefined;
    }
  });

  async function harness(now = { value: Date.now() }) {
    const config = loadConfig({
      NODE_ENV: "test",
      WPAGENT_SESSION_SECRET: "test-secret-value-32chars-long!",
      WPAGENT_PUBLIC_URL: "http://127.0.0.1",
      WPAGENT_EMAIL_PROVIDER: "console",
    });
    config.sessionTtlMs = 60_000;
    config.authCodeTtlMs = 10_000;
    config.pairingTtlMs = 15_000;
    config.rateLimitAuthPerWindow = 20;
    config.allowAnonEnvSites = false;

    const store = new MemorySessionStore();
    const mailer = new ConsoleEmailSender();
    const auth = new AuthService(
      store,
      mailer,
      {
        sessionSecret: config.sessionSecret,
        sessionTtlMs: config.sessionTtlMs,
        authCodeTtlMs: config.authCodeTtlMs,
        pairingTtlMs: config.pairingTtlMs,
        publicUrl: config.publicUrl,
      },
      { now: () => now.value },
    );
    const app = createHttpApp({
      config,
      auth,
      loadEnvSites: async () => [],
      limiter: new RateLimiter(() => now.value),
    });
    const listening = await listen(app);
    server = listening.server;
    return { ...listening, mailer, now, auth };
  }

  it("issues a session after email code verification", async () => {
    const { url, mailer } = await harness();
    const start = await fetch(`${url}/auth/start`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "owner@example.com" }),
    });
    expect(start.status).toBe(200);
    const startBody = await start.json();
    expect(startBody.ok).toBe(true);
    expect(startBody).not.toHaveProperty("code");

    const verify = await fetch(`${url}/auth/verify`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "owner@example.com", code: codeFrom(mailer) }),
    });
    expect(verify.status).toBe(200);
    const body = await verify.json();
    expect(body.token).toMatch(/^wpa_/);
    expect(body.pairing_code).toMatch(/^[A-F0-9]{4}-[A-F0-9]{4}$/);
    expect(body.mcp_url).toContain("/mcp");
    expect(body.session.email).toBe("owner@example.com");
    expect(body.session.sites).toEqual([]);
  });

  it("rejects a wrong code and expires old codes", async () => {
    const { url, mailer, now } = await harness();
    await fetch(`${url}/auth/start`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "owner@example.com" }),
    });
    const code = codeFrom(mailer);

    const wrong = await fetch(`${url}/auth/verify`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "owner@example.com", code: "000000" }),
    });
    expect(wrong.status).toBe(401);

    now.value += 11_000;
    const expired = await fetch(`${url}/auth/verify`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "owner@example.com", code }),
    });
    expect(expired.status).toBe(401);
  });

  it("expires sessions and refuses MCP without a bearer", async () => {
    const { url, mailer, now } = await harness();
    await fetch(`${url}/auth/start`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "owner@example.com" }),
    });
    const verified = await (
      await fetch(`${url}/auth/verify`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: "owner@example.com", code: codeFrom(mailer) }),
      })
    ).json();

    const ok = await fetch(`${url}/auth/session`, {
      headers: { Authorization: `Bearer ${verified.token}` },
    });
    expect(ok.status).toBe(200);

    now.value += 61_000;
    const expired = await fetch(`${url}/auth/session`, {
      headers: { Authorization: `Bearer ${verified.token}` },
    });
    expect(expired.status).toBe(401);

    const mcp = await fetch(`${url}/mcp`, { method: "POST", headers: { "Content-Type": "application/json" }, body: "{}" });
    expect(mcp.status).toBe(401);
  });

  it("isolates sites across sessions and links via pairing code", async () => {
    const { url, mailer } = await harness();

    async function signIn(email: string) {
      await fetch(`${url}/auth/start`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });
      return (
        await fetch(`${url}/auth/verify`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email, code: codeFrom(mailer) }),
        })
      ).json();
    }

    const alice = await signIn("alice@example.com");
    const bob = await signIn("bob@example.com");

    const linked = await fetch(`${url}/auth/link-by-pairing`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        pairing_code: alice.pairing_code,
        url: "https://alice.example",
        username: "alice",
        password: "app-pass-alice",
      }),
    });
    expect(linked.status).toBe(200);

    const aliceSession = await (
      await fetch(`${url}/auth/session`, { headers: { Authorization: `Bearer ${alice.token}` } })
    ).json();
    const bobSession = await (
      await fetch(`${url}/auth/session`, { headers: { Authorization: `Bearer ${bob.token}` } })
    ).json();

    expect(aliceSession.session.sites.map((s: { url: string }) => s.url)).toEqual(["https://alice.example"]);
    expect(bobSession.session.sites).toEqual([]);
    expect(JSON.stringify(bobSession)).not.toContain("alice.example");
    expect(JSON.stringify(aliceSession)).not.toContain("app-pass-alice");
  });

  it("rate-limits auth start", async () => {
    const config = loadConfig({
      NODE_ENV: "test",
      WPAGENT_SESSION_SECRET: "test-secret-value-32chars-long!",
    });
    config.rateLimitAuthPerWindow = 2;
    config.rateLimitAuthWindowMs = 60_000;
    const mailer = new ConsoleEmailSender();
    const auth = new AuthService(new MemorySessionStore(), mailer, {
      sessionSecret: config.sessionSecret,
      sessionTtlMs: 60_000,
      authCodeTtlMs: 10_000,
      pairingTtlMs: 15_000,
      publicUrl: "http://127.0.0.1",
    });
    const app = createHttpApp({
      config,
      auth,
      loadEnvSites: async () => [],
      limiter: new RateLimiter(),
    });
    const listening = await listen(app);
    server = listening.server;
    const url = listening.url;

    for (let i = 0; i < 2; i += 1) {
      const res = await fetch(`${url}/auth/start`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: "rate@example.com" }),
      });
      expect(res.status).toBe(200);
    }
    const blocked = await fetch(`${url}/auth/start`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "rate@example.com" }),
    });
    expect(blocked.status).toBe(429);
  });
});
