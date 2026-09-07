import { mkdtemp, readFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { decryptString, encryptString } from "../src/session-crypto.js";
import { FileSessionStore } from "../src/session/file-store.js";

describe("session encryption and file store", () => {
  it("round-trips AES-256-GCM", () => {
    const cipher = encryptString("app password secret", "master-secret");
    expect(cipher).not.toContain("app password secret");
    expect(decryptString(cipher, "master-secret")).toBe("app password secret");
    expect(() => decryptString(cipher, "other")).toThrow();
  });

  it("persists sessions to disk without plaintext passwords", async () => {
    const dir = await mkdtemp(path.join(tmpdir(), "wpagent-"));
    const file = path.join(dir, "sessions.json");
    const store = new FileSessionStore(file);
    await store.putSession({
      id: "s1",
      tokenHash: "abc",
      email: "a@example.com",
      createdAt: 1,
      expiresAt: 2,
      lastUsedAt: 1,
      sites: [{ id: "blog", url: "https://blog.test", username: "ed", passwordCipher: "cipher-text" }],
    });

    const raw = await readFile(file, "utf8");
    expect(raw).toContain("a@example.com");
    expect(raw).not.toContain("app-pass");

    const reloaded = new FileSessionStore(file);
    const session = await reloaded.getSessionById("s1");
    expect(session?.email).toBe("a@example.com");
    expect(session?.sites[0].passwordCipher).toBe("cipher-text");
  });
});
