import { describe, expect, it } from "vitest";
import { WPAgentError } from "../src/errors.js";

describe("WPAgentError", () => {
  it("serializes a capability error the model can act on", () => {
    const err = new WPAgentError("capability", "Missing capability: manage_options", {
      status: 403,
      details: { capability: "manage_options", command: "get_site_health" },
    });
    const json = err.toJSON();
    expect(json.code).toBe("capability");
    expect(json.status).toBe(403);
    expect(json.details).toMatchObject({ capability: "manage_options" });
    expect(JSON.stringify(json)).not.toContain("stack");
  });

  it("wraps unknown errors without leaking objects", () => {
    const json = WPAgentError.fromUnknown(new Error("ECONNRESET")).toJSON();
    expect(json.code).toBe("error");
    expect(json.message).toBe("ECONNRESET");
  });
});
