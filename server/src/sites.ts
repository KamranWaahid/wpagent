import { readFile } from "node:fs/promises";

export type SiteConfig = {
  id: string;
  url: string;
  username: string;
  password: string;
};

function stripTrailingSlash(url: string): string {
  return url.replace(/\/+$/, "");
}

function parseSitesJson(raw: string, source: string): SiteConfig[] {
  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch {
    throw new Error(`Invalid JSON in ${source}`);
  }

  if (Array.isArray(parsed)) {
    return parsed.map((item, index) => coerceSite(item, String(index)));
  }

  if (parsed && typeof parsed === "object") {
    return Object.entries(parsed as Record<string, unknown>).map(([id, value]) =>
      coerceSite(value, id),
    );
  }

  throw new Error(`${source} must be a JSON object of sites or an array.`);
}

function coerceSite(value: unknown, id: string): SiteConfig {
  if (!value || typeof value !== "object") {
    throw new Error(`Site "${id}" is invalid.`);
  }
  const row = value as Record<string, unknown>;
  const url = String(row.url ?? row.site_url ?? "");
  const username = String(row.username ?? row.user ?? "");
  const password = String(row.password ?? row.app_password ?? "");
  if (!url || !username || !password) {
    throw new Error(`Site "${id}" needs url, username, and password.`);
  }
  return {
    id: String(row.id ?? id),
    url: stripTrailingSlash(url),
    username,
    password,
  };
}

export function loadSitesFromEnv(env: NodeJS.ProcessEnv = process.env): SiteConfig[] {
  if (env.WPAGENT_SITES) {
    return parseSitesJson(env.WPAGENT_SITES, "WPAGENT_SITES");
  }

  if (env.WPAGENT_URL && env.WPAGENT_USERNAME && env.WPAGENT_APP_PASSWORD) {
    return [
      {
        id: "default",
        url: stripTrailingSlash(env.WPAGENT_URL),
        username: env.WPAGENT_USERNAME,
        password: env.WPAGENT_APP_PASSWORD,
      },
    ];
  }

  return [];
}

export async function loadSitesFromFile(path: string): Promise<SiteConfig[]> {
  const raw = await readFile(path, "utf8");
  return parseSitesJson(raw, path);
}

export async function loadSites(env: NodeJS.ProcessEnv = process.env): Promise<SiteConfig[]> {
  if (env.WPAGENT_SITES_FILE) {
    return loadSitesFromFile(env.WPAGENT_SITES_FILE);
  }
  return loadSitesFromEnv(env);
}

export function findSite(sites: SiteConfig[], id?: string): SiteConfig {
  if (sites.length === 0) {
    throw new Error(
      "No WordPress sites linked. Set WPAGENT_URL + WPAGENT_USERNAME + WPAGENT_APP_PASSWORD (stdio and personal HTTP). Hosted mode uses pairing instead.",
    );
  }
  if (!id) {
    return sites[0];
  }
  const match = sites.find((site) => site.id === id);
  if (!match) {
    throw new Error(
      `Unknown site "${id}". Connected sites: ${sites.map((s) => s.id).join(", ")}`,
    );
  }
  return match;
}
