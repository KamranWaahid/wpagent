import { describe, expect, it } from "vitest";
import { findSite, loadSitesFromEnv } from "../src/sites.js";

describe("site config", () => {
  it("loads a single site from env vars", () => {
    const sites = loadSitesFromEnv({
      WPAGENT_URL: "https://example.com/",
      WPAGENT_USERNAME: "admin",
      WPAGENT_APP_PASSWORD: "abcd efgh",
    });
    expect(sites).toEqual([
      { id: "default", url: "https://example.com", username: "admin", password: "abcd efgh" },
    ]);
  });

  it("loads multiple sites from WPAGENT_SITES", () => {
    const sites = loadSitesFromEnv({
      WPAGENT_SITES: JSON.stringify({
        blog: { url: "https://blog.test", username: "ed", password: "pw1" },
        shop: { url: "https://shop.test", username: "ed", password: "pw2" },
      }),
    });
    expect(sites.map((s) => s.id)).toEqual(["blog", "shop"]);
    expect(findSite(sites, "shop").url).toBe("https://shop.test");
  });

  it("rejects unknown site ids", () => {
    const sites = loadSitesFromEnv({
      WPAGENT_URL: "https://example.com",
      WPAGENT_USERNAME: "a",
      WPAGENT_APP_PASSWORD: "b",
    });
    expect(() => findSite(sites, "nope")).toThrow(/Unknown site/);
  });

  it("errors when no sites are configured", () => {
    expect(() => findSite([])).toThrow(/No WordPress sites linked/);
  });
});
