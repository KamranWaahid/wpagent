import { afterEach, describe, expect, it } from "vitest";
import type { Server } from "node:http";
import { AddressInfo } from "node:net";
import { loadConfig } from "../src/config.js";
import { createHttpApp } from "../src/http/app.js";

async function listen(app: ReturnType<typeof createHttpApp>): Promise<{ server: Server; url: string }> {
  const server = await new Promise<Server>((resolve) => {
    const httpServer = app.listen(0, "127.0.0.1", () => resolve(httpServer));
  });
  const addr = server.address() as AddressInfo;
  return { server, url: `http://127.0.0.1:${addr.port}` };
}

describe("personal HTTP (ChatGPT tunnel)", () => {
  let server: Server | undefined;

  afterEach(async () => {
    if (server) {
      await new Promise<void>((resolve, reject) => {
        server?.close((err) => (err ? reject(err) : resolve()));
      });
      server = undefined;
    }
  });

  it("rejects anonymous /mcp and hosted auth routes in personal mode", async () => {
    const config = loadConfig({ NODE_ENV: "test", WPAGENT_HTTP_TOKEN: "a-long-enough-token" });
    const app = createHttpApp({
      config,
      loadEnvSites: async () => [
        { id: "default", url: "https://blog.example", username: "admin", password: "pw" },
      ],
    });
    const listening = await listen(app);
    server = listening.server;

    const health = await (await fetch(`${listening.url}/healthz`)).json();
    expect(health.mode).toBe("personal");
    expect(health.mcp).toBe("/mcp");

    const auth = await fetch(`${listening.url}/auth/start`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: "you@example.com" }),
    });
    expect(auth.status).toBe(404);

    const connect = await fetch(`${listening.url}/connect`);
    expect(connect.status).toBe(404);

    const anon = await fetch(`${listening.url}/mcp`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "ping" }),
    });
    expect(anon.status).toBe(401);
  });

  it("accepts an optional shared token without sessions", async () => {
    const config = loadConfig({ NODE_ENV: "test", WPAGENT_HTTP_TOKEN: "tunnel-secret" });
    const app = createHttpApp({
      config,
      loadEnvSites: async () => [
        { id: "default", url: "https://blog.example", username: "admin", password: "pw" },
      ],
    });
    const listening = await listen(app);
    server = listening.server;

    const denied = await fetch(`${listening.url}/mcp`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "ping" }),
    });
    expect(denied.status).toBe(401);

    const ok = await fetch(`${listening.url}/mcp`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer tunnel-secret",
      },
      body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "ping" }),
    });
    expect(ok.status).not.toBe(401);
  });
});
