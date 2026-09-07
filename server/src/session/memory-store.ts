import type { AuthCodeRecord, PairingRecord, SessionRecord } from "./types.js";
import type { SessionStore } from "./store.js";

export type StoreSnapshot = {
  sessions: SessionRecord[];
  codes: AuthCodeRecord[];
  pairings: PairingRecord[];
};

export class MemorySessionStore implements SessionStore {
  private readonly sessions = new Map<string, SessionRecord>();
  private readonly sessionsByToken = new Map<string, string>();
  private readonly codes = new Map<string, AuthCodeRecord>();
  private readonly pairings = new Map<string, PairingRecord>();

  snapshot(): StoreSnapshot {
    return {
      sessions: [...this.sessions.values()],
      codes: [...this.codes.values()],
      pairings: [...this.pairings.values()],
    };
  }

  async restore(snapshot: StoreSnapshot): Promise<void> {
    this.sessions.clear();
    this.sessionsByToken.clear();
    this.codes.clear();
    this.pairings.clear();
    for (const session of snapshot.sessions) {
      await this.putSession(session);
    }
    for (const code of snapshot.codes) {
      await this.putAuthCode(code);
    }
    for (const pairing of snapshot.pairings) {
      await this.putPairing(pairing);
    }
  }

  async putSession(session: SessionRecord): Promise<void> {
    const existing = this.sessions.get(session.id);
    if (existing && existing.tokenHash !== session.tokenHash) {
      this.sessionsByToken.delete(existing.tokenHash);
    }
    this.sessions.set(session.id, session);
    this.sessionsByToken.set(session.tokenHash, session.id);
  }

  async getSessionById(id: string): Promise<SessionRecord | undefined> {
    return this.sessions.get(id);
  }

  async getSessionByTokenHash(tokenHash: string): Promise<SessionRecord | undefined> {
    const id = this.sessionsByToken.get(tokenHash);
    return id ? this.sessions.get(id) : undefined;
  }

  async deleteSession(id: string): Promise<void> {
    const session = this.sessions.get(id);
    if (session) {
      this.sessionsByToken.delete(session.tokenHash);
    }
    this.sessions.delete(id);
  }

  async putAuthCode(record: AuthCodeRecord): Promise<void> {
    this.codes.set(record.email, record);
  }

  async getAuthCode(email: string): Promise<AuthCodeRecord | undefined> {
    return this.codes.get(email);
  }

  async deleteAuthCode(email: string): Promise<void> {
    this.codes.delete(email);
  }

  async putPairing(record: PairingRecord): Promise<void> {
    this.pairings.set(record.code, record);
  }

  async getPairing(code: string): Promise<PairingRecord | undefined> {
    return this.pairings.get(code);
  }

  async deletePairing(code: string): Promise<void> {
    this.pairings.delete(code);
  }
}
