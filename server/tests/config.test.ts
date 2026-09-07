import { describe, expect, it } from "vitest";
import { createEmailSender } from "../src/auth/email.js";
import { assertHttpConfig, loadConfig } from "../src/config.js";

describe("email provider defaults", () => {
  it("defaults to resend in production", () => {
    const config = loadConfig({ NODE_ENV: "production" });
    expect(config.emailProvider).toBe("resend");
  });

  it("defaults to console in development", () => {
    const config = loadConfig({ NODE_ENV: "development" });
    expect(config.emailProvider).toBe("console");
  });

  it("honors an explicit provider override", () => {
    const config = loadConfig({ NODE_ENV: "production", WPAGENT_EMAIL_PROVIDER: "console" });
    expect(config.emailProvider).toBe("console");
  });

  it("falls back to the console adapter when Resend has no key outside production", () => {
    const sender = createEmailSender({
      provider: "resend",
      from: "WPAgent <noreply@localhost>",
      nodeEnv: "development",
    });
    expect(sender.provider).toBe("console");
  });

  it("requires a Resend key when hosted production is configured for resend", () => {
    const config = loadConfig({
      NODE_ENV: "production",
      WPAGENT_HTTP_MODE: "hosted",
      WPAGENT_SESSION_SECRET: "a-long-enough-secret",
      RESEND_API_KEY: "",
    });
    expect(() => assertHttpConfig(config)).toThrow(/RESEND_API_KEY/);
  });

  it("defaults HTTP to personal mode and requires a tunnel token", () => {
    const config = loadConfig({ NODE_ENV: "production" });
    expect(config.httpMode).toBe("personal");
    expect(config.allowAnonEnvSites).toBe(false);
    expect(() => assertHttpConfig(config)).toThrow(/WPAGENT_HTTP_TOKEN/);
    expect(() =>
      assertHttpConfig(loadConfig({ NODE_ENV: "production", WPAGENT_HTTP_TOKEN: "a-long-enough-token" })),
    ).not.toThrow();
  });

  it("only enters hosted mode when asked", () => {
    const config = loadConfig({ WPAGENT_HTTP_MODE: "hosted" });
    expect(config.httpMode).toBe("hosted");
    expect(config.allowAnonEnvSites).toBe(false);
  });
});
