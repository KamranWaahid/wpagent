import type { SiteConfig } from "../sites.js";

export type StoredSite = {
  id: string;
  url: string;
  username: string;
  passwordCipher: string;
};

export type SessionRecord = {
  id: string;
  tokenHash: string;
  email: string;
  createdAt: number;
  expiresAt: number;
  lastUsedAt: number;
  selectedSiteId?: string;
  sites: StoredSite[];
};

export type AuthCodeRecord = {
  email: string;
  codeHash: string;
  expiresAt: number;
  attempts: number;
};

export type PairingRecord = {
  code: string;
  sessionId: string;
  expiresAt: number;
};

export type PublicSite = {
  id: string;
  url: string;
  username: string;
};

export function toPublicSites(sites: StoredSite[]): PublicSite[] {
  return sites.map(({ id, url, username }) => ({ id, url, username }));
}

export type SessionView = {
  id: string;
  email: string;
  expiresAt: number;
  selectedSiteId?: string;
  sites: PublicSite[];
};

export function toSessionView(session: SessionRecord): SessionView {
  return {
    id: session.id,
    email: session.email,
    expiresAt: session.expiresAt,
    selectedSiteId: session.selectedSiteId,
    sites: toPublicSites(session.sites),
  };
}

export function decryptSites(sites: StoredSite[], decrypt: (cipher: string) => string): SiteConfig[] {
  return sites.map((site) => ({
    id: site.id,
    url: site.url,
    username: site.username,
    password: decrypt(site.passwordCipher),
  }));
}
