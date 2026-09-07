import { describe, expect, it, vi } from "vitest";
import { WordPressClient } from "../src/wordpress-client.js";
import { WPAgentError } from "../src/errors.js";

const site = { id: "blog", url: "https://blog.test", username: "admin", password: "secret" };

describe("WordPressClient", () => {
  it("sends basic auth and parses JSON", async () => {
    const fetchImpl = vi.fn(async () => {
      return new Response(JSON.stringify({ items: [] }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }) as unknown as typeof fetch;

    const client = new WordPressClient(site, fetchImpl);
    const data = await client.request<{ items: unknown[] }>({ path: "/posts" });
    expect(data.items).toEqual([]);
    const [url, init] = fetchImpl.mock.calls[0] as [URL, RequestInit];
    expect(String(url)).toBe("https://blog.test/wp-json/wpagent/v1/posts");
    const headers = init.headers as Record<string, string>;
    expect(headers.Authorization).toMatch(/^Basic /);
  });

  it("maps missing capability to a structured error", async () => {
    const fetchImpl = vi.fn(async () => {
      return new Response(
        JSON.stringify({
          code: "wpagent_capability",
          message: "Missing capability: manage_options",
          data: { status: 403, code: "capability", capability: "manage_options" },
        }),
        { status: 403, headers: { "Content-Type": "application/json" } },
      );
    }) as unknown as typeof fetch;

    const client = new WordPressClient(site, fetchImpl);
    await expect(client.request({ path: "/site/health" })).rejects.toMatchObject({
      code: "capability",
      status: 403,
    } satisfies Partial<WPAgentError>);
  });
});
