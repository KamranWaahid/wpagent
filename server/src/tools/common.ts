import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { WPAgentError } from "../errors.js";
import { findSite, type SiteConfig } from "../sites.js";
import { WordPressClient } from "../wordpress-client.js";

export type ToolContext = {
  sites: SiteConfig[];
  selectedSiteId?: string;
  setSelectedSiteId: (id: string) => void;
};

export type McpTextResult = {
  content: Array<{ type: "text"; text: string }>;
  isError?: boolean;
};

export function jsonResult(data: unknown): McpTextResult {
  return {
    content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
  };
}

export function errorResult(err: unknown): McpTextResult {
  const mapped = WPAgentError.fromUnknown(err);
  return {
    isError: true,
    content: [{ type: "text", text: JSON.stringify(mapped.toJSON(), null, 2) }],
  };
}

export function clientFor(
  ctx: ToolContext,
  siteId?: string,
): { site: SiteConfig; client: WordPressClient } {
  const site = findSite(ctx.sites, siteId ?? ctx.selectedSiteId);
  return { site, client: new WordPressClient(site) };
}

export function wrap<T>(
  handler: (args: T) => Promise<unknown>,
): (args: T) => Promise<McpTextResult> {
  return async (args: T) => {
    try {
      return jsonResult(await handler(args));
    } catch (err) {
      return errorResult(err);
    }
  };
}

export type RegisterTools = (server: McpServer, ctx: ToolContext) => void;
