import { mkdir, readFile, rename, writeFile } from "node:fs/promises";
import path from "node:path";
import { MemorySessionStore, type StoreSnapshot } from "./memory-store.js";
import type { SessionStore } from "./store.js";
import type { AuthCodeRecord, PairingRecord, SessionRecord } from "./types.js";

export class FileSessionStore implements SessionStore {
  private readonly memory = new MemorySessionStore();
  private loaded = false;

  constructor(private readonly filePath: string) {}

  private async ensureLoaded(): Promise<void> {
    if (this.loaded) {
      return;
    }
    this.loaded = true;
    try {
      const raw = await readFile(this.filePath, "utf8");
      const snapshot = JSON.parse(raw) as StoreSnapshot;
      await this.memory.restore({
        sessions: snapshot.sessions ?? [],
        codes: snapshot.codes ?? [],
        pairings: snapshot.pairings ?? [],
      });
    } catch (err) {
      const code = (err as NodeJS.ErrnoException).code;
      if (code !== "ENOENT") {
        throw err;
      }
    }
  }

  private async persist(): Promise<void> {
    await mkdir(path.dirname(this.filePath), { recursive: true });
    const tmp = `${this.filePath}.${process.pid}.tmp`;
    await writeFile(tmp, JSON.stringify(this.memory.snapshot()), { mode: 0o600 });
    await rename(tmp, this.filePath);
  }

  async putSession(session: SessionRecord): Promise<void> {
    await this.ensureLoaded();
    await this.memory.putSession(session);
    await this.persist();
  }

  async getSessionById(id: string): Promise<SessionRecord | undefined> {
    await this.ensureLoaded();
    return this.memory.getSessionById(id);
  }

  async getSessionByTokenHash(tokenHash: string): Promise<SessionRecord | undefined> {
    await this.ensureLoaded();
    return this.memory.getSessionByTokenHash(tokenHash);
  }

  async deleteSession(id: string): Promise<void> {
    await this.ensureLoaded();
    await this.memory.deleteSession(id);
    await this.persist();
  }

  async putAuthCode(record: AuthCodeRecord): Promise<void> {
    await this.ensureLoaded();
    await this.memory.putAuthCode(record);
    await this.persist();
  }

  async getAuthCode(email: string): Promise<AuthCodeRecord | undefined> {
    await this.ensureLoaded();
    return this.memory.getAuthCode(email);
  }

  async deleteAuthCode(email: string): Promise<void> {
    await this.ensureLoaded();
    await this.memory.deleteAuthCode(email);
    await this.persist();
  }

  async putPairing(record: PairingRecord): Promise<void> {
    await this.ensureLoaded();
    await this.memory.putPairing(record);
    await this.persist();
  }

  async getPairing(code: string): Promise<PairingRecord | undefined> {
    await this.ensureLoaded();
    return this.memory.getPairing(code);
  }

  async deletePairing(code: string): Promise<void> {
    await this.ensureLoaded();
    await this.memory.deletePairing(code);
    await this.persist();
  }
}
