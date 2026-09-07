import { WPAgentError, isRetryableStatus } from "./errors.js";
import type { SiteConfig } from "./sites.js";

export type HttpMethod = "GET" | "POST" | "PATCH" | "PUT" | "DELETE";

type RequestOptions = {
  method?: HttpMethod;
  path: string;
  query?: Record<string, string | number | boolean | undefined | null>;
  body?: unknown;
};

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function mapWpError(status: number, payload: unknown): WPAgentError {
  const body = payload && typeof payload === "object" ? (payload as Record<string, unknown>) : {};
  const data = body.data && typeof body.data === "object" ? (body.data as Record<string, unknown>) : {};
  const semantic = String(data.code ?? "");
  const message = String(body.message ?? `WordPress request failed (${status})`);

  if (status === 401 || semantic === "auth") {
    return new WPAgentError("auth", message, { status, details: data });
  }
  if (status === 403 || semantic === "capability") {
    return new WPAgentError("capability", message, { status, details: data });
  }
  if (status === 404 || semantic === "not_found") {
    return new WPAgentError("not_found", message, { status, details: data });
  }
  if (semantic === "safety" || body.code === "wpagent_confirm_required") {
    return new WPAgentError("safety", message, { status, details: data });
  }
  if (status === 400 || semantic === "validation") {
    return new WPAgentError("validation", message, { status, details: data });
  }
  return new WPAgentError("error", message, { status, details: data });
}

export class WordPressClient {
  constructor(
    private readonly site: SiteConfig,
    private readonly fetchImpl: typeof fetch = fetch,
  ) {}

  async request<T>(options: RequestOptions): Promise<T> {
    const url = new URL(`${this.site.url}/wp-json/wpagent/v1${options.path}`);
    if (options.query) {
      for (const [key, value] of Object.entries(options.query)) {
        if (value === undefined || value === null) {
          continue;
        }
        url.searchParams.set(key, String(value));
      }
    }

    const headers: Record<string, string> = {
      Authorization:
        "Basic " + Buffer.from(`${this.site.username}:${this.site.password}`).toString("base64"),
      Accept: "application/json",
    };
    if (options.body !== undefined) {
      headers["Content-Type"] = "application/json";
    }

    const init: RequestInit = {
      method: options.method ?? "GET",
      headers,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    };

    let lastError: WPAgentError | undefined;
    for (let attempt = 0; attempt < 3; attempt += 1) {
      try {
        const response = await this.fetchImpl(url, init);
        const text = await response.text();
        let payload: unknown = null;
        if (text) {
          try {
            payload = JSON.parse(text);
          } catch {
            payload = { raw: text };
          }
        }

        if (!response.ok) {
          const err = mapWpError(response.status, payload);
          if (isRetryableStatus(response.status) && attempt < 2) {
            lastError = err;
            await sleep(250 * (attempt + 1));
            continue;
          }
          throw err;
        }

        return payload as T;
      } catch (err) {
        if (err instanceof WPAgentError) {
          throw err;
        }
        lastError = new WPAgentError("network", err instanceof Error ? err.message : "Network error", {
          cause: err,
        });
        if (attempt < 2) {
          await sleep(250 * (attempt + 1));
          continue;
        }
      }
    }

    throw lastError ?? new WPAgentError("network", "Request failed");
  }
}
