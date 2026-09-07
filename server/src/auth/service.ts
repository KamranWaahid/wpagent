import type { SiteConfig } from "../sites.js";
import { hmacSha256, isValidEmail, normalizeEmail, randomDigits, randomPairingCode, randomToken, sha256, timingSafeEqualText } from "../crypto-util.js";
import { decryptString, encryptString } from "../session-crypto.js";
import type { SessionStore } from "../session/store.js";
import {
  decryptSites,
  toSessionView,
  type SessionRecord,
  type SessionView,
  type StoredSite,
} from "../session/types.js";
import type { EmailSender } from "./email.js";

const MAX_CODE_ATTEMPTS = 5;

export type AuthConfig = {
  sessionSecret: string;
  sessionTtlMs: number;
  authCodeTtlMs: number;
  pairingTtlMs: number;
  publicUrl: string;
};

export type AuthClock = {
  now: () => number;
};

export type SiteInput = {
  id?: string;
  url: string;
  username: string;
  password: string;
};

function siteIdFromUrl(url: string): string {
  try {
    return new URL(url).hostname.replace(/[^a-z0-9.-]/gi, "-") || "site";
  } catch {
    return "site";
  }
}

function stripSlash(url: string): string {
  return url.replace(/\/+$/, "");
}

export class AuthService {
  constructor(
    private readonly store: SessionStore,
    private readonly mailer: EmailSender,
    private readonly config: AuthConfig,
    private readonly clock: AuthClock = { now: () => Date.now() },
    private readonly generateCode: () => string = () => randomDigits(6),
    private readonly generatePairing: () => string = () => randomPairingCode(),
    private readonly generateToken: () => string = () => `wpa_${randomToken(32)}`,
  ) {}

  async start(emailRaw: string): Promise<{ expiresIn: number; provider: string }> {
    const email = normalizeEmail(emailRaw);
    if (!isValidEmail(email)) {
      throw Object.assign(new Error("Enter a valid email address."), { status: 400, code: "validation" });
    }

    const code = this.generateCode();
    const now = this.clock.now();
    await this.store.putAuthCode({
      email,
      codeHash: this.hashCode(email, code),
      expiresAt: now + this.config.authCodeTtlMs,
      attempts: 0,
    });

    await this.mailer.send({
      to: email,
      subject: "Your WPAgent sign-in code",
      text: `Your WPAgent verification code is ${code}. It expires in ${Math.round(this.config.authCodeTtlMs / 60_000)} minutes.\n\nIf you did not request this, ignore this email.`,
    });

    return {
      expiresIn: Math.round(this.config.authCodeTtlMs / 1000),
      provider: this.mailer.provider,
    };
  }

  async verify(emailRaw: string, code: string): Promise<{
    token: string;
    expiresIn: number;
    pairingCode: string;
    connectUrl: string;
    session: SessionView;
  }> {
    const email = normalizeEmail(emailRaw);
    const record = await this.store.getAuthCode(email);
    const now = this.clock.now();
    if (!record || record.expiresAt < now) {
      throw Object.assign(new Error("Invalid or expired code."), { status: 401, code: "auth" });
    }
    if (record.attempts >= MAX_CODE_ATTEMPTS) {
      await this.store.deleteAuthCode(email);
      throw Object.assign(new Error("Too many attempts. Request a new code."), { status: 401, code: "auth" });
    }

    const expected = this.hashCode(email, code.trim());
    if (!timingSafeEqualText(expected, record.codeHash)) {
      record.attempts += 1;
      await this.store.putAuthCode(record);
      throw Object.assign(new Error("Invalid or expired code."), { status: 401, code: "auth" });
    }

    await this.store.deleteAuthCode(email);

    const token = this.generateToken();
    const session: SessionRecord = {
      id: randomToken(16),
      tokenHash: sha256(token),
      email,
      createdAt: now,
      expiresAt: now + this.config.sessionTtlMs,
      lastUsedAt: now,
      sites: [],
    };
    await this.store.putSession(session);

    const pairingCode = this.generatePairing();
    await this.store.putPairing({
      code: pairingCode,
      sessionId: session.id,
      expiresAt: now + this.config.pairingTtlMs,
    });

    return {
      token,
      expiresIn: Math.round(this.config.sessionTtlMs / 1000),
      pairingCode,
      connectUrl: `${this.config.publicUrl}/connect`,
      session: toSessionView(session),
    };
  }

  async getSessionByToken(token: string): Promise<SessionRecord | undefined> {
    if (!token) {
      return undefined;
    }
    const session = await this.store.getSessionByTokenHash(sha256(token));
    if (!session) {
      return undefined;
    }
    if (session.expiresAt < this.clock.now()) {
      await this.store.deleteSession(session.id);
      return undefined;
    }
    session.lastUsedAt = this.clock.now();
    await this.store.putSession(session);
    return session;
  }

  async revoke(token: string): Promise<void> {
    const session = await this.getSessionByToken(token);
    if (session) {
      await this.store.deleteSession(session.id);
    }
  }

  async attachSite(token: string, input: SiteInput): Promise<SessionView> {
    const session = await this.requireSession(token);
    return this.addSiteToSession(session, input);
  }

  async attachSiteByPairing(pairingCode: string, input: SiteInput): Promise<SessionView> {
    const pairing = await this.store.getPairing(pairingCode.trim().toUpperCase());
    const now = this.clock.now();
    if (!pairing || pairing.expiresAt < now) {
      throw Object.assign(new Error("Invalid or expired pairing code."), { status: 401, code: "auth" });
    }
    const session = await this.store.getSessionById(pairing.sessionId);
    if (!session || session.expiresAt < now) {
      throw Object.assign(new Error("Session expired. Sign in again."), { status: 401, code: "auth" });
    }
    await this.store.deletePairing(pairing.code);
    return this.addSiteToSession(session, input);
  }

  async createPairing(token: string): Promise<{ pairingCode: string; expiresIn: number }> {
    const session = await this.requireSession(token);
    const pairingCode = this.generatePairing();
    await this.store.putPairing({
      code: pairingCode,
      sessionId: session.id,
      expiresAt: this.clock.now() + this.config.pairingTtlMs,
    });
    return {
      pairingCode,
      expiresIn: Math.round(this.config.pairingTtlMs / 1000),
    };
  }

  async setSelectedSite(token: string, siteId: string): Promise<void> {
    const session = await this.requireSession(token);
    if (!session.sites.some((site) => site.id === siteId)) {
      throw Object.assign(new Error(`Unknown site "${siteId}" for this session.`), {
        status: 400,
        code: "validation",
      });
    }
    session.selectedSiteId = siteId;
    await this.store.putSession(session);
  }

  decryptedSites(session: SessionRecord): SiteConfig[] {
    return decryptSites(session.sites, (cipher) => decryptString(cipher, this.encryptionSecret()));
  }

  private async requireSession(token: string): Promise<SessionRecord> {
    const session = await this.getSessionByToken(token);
    if (!session) {
      throw Object.assign(new Error("Invalid or expired session."), { status: 401, code: "auth" });
    }
    return session;
  }

  private async addSiteToSession(session: SessionRecord, input: SiteInput): Promise<SessionView> {
    const url = stripSlash(input.url);
    if (!/^https?:\/\//i.test(url)) {
      throw Object.assign(new Error("Site URL must start with http:// or https://."), {
        status: 400,
        code: "validation",
      });
    }
    if (!input.username || !input.password) {
      throw Object.assign(new Error("username and password are required."), { status: 400, code: "validation" });
    }

    const id = (input.id || siteIdFromUrl(url)).replace(/[^a-z0-9._-]/gi, "-");
    const stored: StoredSite = {
      id,
      url,
      username: input.username,
      passwordCipher: encryptString(input.password, this.encryptionSecret()),
    };
    session.sites = [...session.sites.filter((site) => site.id !== id && site.url !== url), stored];
    session.selectedSiteId = session.selectedSiteId ?? id;
    await this.store.putSession(session);
    return toSessionView(session);
  }

  private hashCode(email: string, code: string): string {
    return hmacSha256(this.config.sessionSecret || "wpagent-dev-secret", `${email}:${code}`);
  }

  private encryptionSecret(): string {
    return this.config.sessionSecret || "wpagent-dev-secret";
  }
}
