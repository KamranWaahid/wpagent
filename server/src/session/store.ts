import type { AuthCodeRecord, PairingRecord, SessionRecord } from "./types.js";

export interface SessionStore {
  putSession(session: SessionRecord): Promise<void>;
  getSessionById(id: string): Promise<SessionRecord | undefined>;
  getSessionByTokenHash(tokenHash: string): Promise<SessionRecord | undefined>;
  deleteSession(id: string): Promise<void>;
  putAuthCode(record: AuthCodeRecord): Promise<void>;
  getAuthCode(email: string): Promise<AuthCodeRecord | undefined>;
  deleteAuthCode(email: string): Promise<void>;
  putPairing(record: PairingRecord): Promise<void>;
  getPairing(code: string): Promise<PairingRecord | undefined>;
  deletePairing(code: string): Promise<void>;
}
