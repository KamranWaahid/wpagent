export type EmailProviderName = "console" | "resend" | "postmark";
export type SessionStoreName = "memory" | "file";
export type HttpMode = "personal" | "hosted";

export type AppConfig = {
  nodeEnv: string;
  httpMode: HttpMode;
  host: string;
  port: number;
  publicUrl: string;
  corsOrigins: string[];
  trustProxy: boolean;
  sessionSecret: string;
  sessionStore: SessionStoreName;
  sessionDir: string;
  sessionTtlMs: number;
  authCodeTtlMs: number;
  pairingTtlMs: number;
  emailProvider: EmailProviderName;
  emailFrom: string;
  resendApiKey: string;
  postmarkToken: string;
  allowAnonEnvSites: boolean;
  sharedToken: string;
  jsonLimit: string;
  requestTimeoutMs: number;
  mcpTimeoutMs: number;
  rateLimitAuthPerWindow: number;
  rateLimitAuthWindowMs: number;
  rateLimitMcpPerWindow: number;
  rateLimitMcpWindowMs: number;
};

function csv(value: string | undefined, fallback: string[]): string[] {
  if (!value || value.trim() === "") {
    return fallback;
  }
  return value.split(",").map((item) => item.trim()).filter(Boolean);
}

export function loadConfig(env: NodeJS.ProcessEnv = process.env): AppConfig {
  const nodeEnv = env.NODE_ENV ?? "development";
  const publicUrl = (env.WPAGENT_PUBLIC_URL ?? `http://127.0.0.1:${env.WPAGENT_HTTP_PORT ?? "3333"}`).replace(
    /\/+$/,
    "",
  );

  const httpMode = resolveHttpMode(env);

  return {
    nodeEnv,
    httpMode,
    host: env.WPAGENT_HTTP_HOST ?? "127.0.0.1",
    port: Number(env.WPAGENT_HTTP_PORT ?? 3333),
    publicUrl,
    corsOrigins: csv(env.WPAGENT_CORS_ORIGINS, ["*"]),
    trustProxy: env.WPAGENT_TRUST_PROXY === "1",
    sessionSecret: env.WPAGENT_SESSION_SECRET ?? "",
    sessionStore: env.WPAGENT_SESSION_STORE === "file" ? "file" : "memory",
    sessionDir: env.WPAGENT_SESSION_DIR ?? "./data",
    sessionTtlMs: Number(env.WPAGENT_SESSION_TTL_HOURS ?? 24) * 60 * 60 * 1000,
    authCodeTtlMs: Number(env.WPAGENT_AUTH_CODE_TTL_SECONDS ?? 600) * 1000,
    pairingTtlMs: Number(env.WPAGENT_PAIRING_TTL_SECONDS ?? 900) * 1000,
    emailProvider: resolveEmailProvider(env, nodeEnv),
    emailFrom: env.WPAGENT_EMAIL_FROM ?? "WPAgent <noreply@localhost>",
    resendApiKey: env.RESEND_API_KEY ?? "",
    postmarkToken: env.POSTMARK_SERVER_TOKEN ?? "",
    allowAnonEnvSites: env.WPAGENT_HTTP_ALLOW_ANON_ENV === "1",
    sharedToken: env.WPAGENT_HTTP_TOKEN ?? "",
    jsonLimit: env.WPAGENT_JSON_LIMIT ?? "1mb",
    requestTimeoutMs: Number(env.WPAGENT_REQUEST_TIMEOUT_MS ?? 15000),
    mcpTimeoutMs: Number(env.WPAGENT_MCP_TIMEOUT_MS ?? 120000),
    rateLimitAuthPerWindow: Number(env.WPAGENT_RATE_LIMIT_AUTH ?? 5),
    rateLimitAuthWindowMs: Number(env.WPAGENT_RATE_LIMIT_AUTH_WINDOW_MS ?? 15 * 60 * 1000),
    rateLimitMcpPerWindow: Number(env.WPAGENT_RATE_LIMIT_MCP ?? 60),
    rateLimitMcpWindowMs: Number(env.WPAGENT_RATE_LIMIT_MCP_WINDOW_MS ?? 60 * 1000),
  };
}

function resolveHttpMode(env: NodeJS.ProcessEnv): HttpMode {
  if (env.WPAGENT_HTTP_MODE === "hosted") {
    return "hosted";
  }
  return "personal";
}

function resolveEmailProvider(env: NodeJS.ProcessEnv, nodeEnv: string): EmailProviderName {
  if (env.WPAGENT_EMAIL_PROVIDER) {
    return parseEmailProvider(env.WPAGENT_EMAIL_PROVIDER);
  }
  return nodeEnv === "production" ? "resend" : "console";
}

function parseEmailProvider(value: string | undefined): EmailProviderName {
  if (value === "resend" || value === "postmark" || value === "console") {
    return value;
  }
  return "console";
}

export function assertHttpConfig(config: AppConfig): void {
  if (config.httpMode === "personal") {
    if (!config.sharedToken && !config.allowAnonEnvSites) {
      throw new Error(
        "Personal HTTP needs WPAGENT_HTTP_TOKEN (a long random Bearer). Tunnel URLs are internet-reachable; do not rely on URL obscurity. Set WPAGENT_HTTP_ALLOW_ANON_ENV=1 only for localhost tests.",
      );
    }
    if (config.sharedToken && config.sharedToken.length < 16) {
      throw new Error("WPAGENT_HTTP_TOKEN must be at least 16 characters.");
    }
    return;
  }
  if (config.httpMode !== "hosted") {
    return;
  }
  if (config.nodeEnv === "production" && config.sessionSecret.length < 16) {
    throw new Error("WPAGENT_SESSION_SECRET must be set (16+ characters) in production.");
  }
  if (config.emailProvider === "resend" && !config.resendApiKey) {
    throw new Error("RESEND_API_KEY is required when WPAGENT_EMAIL_PROVIDER=resend.");
  }
  if (config.emailProvider === "postmark" && !config.postmarkToken) {
    throw new Error("POSTMARK_SERVER_TOKEN is required when WPAGENT_EMAIL_PROVIDER=postmark.");
  }
}
