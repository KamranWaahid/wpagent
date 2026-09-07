import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { loadSites, type SiteConfig } from "./sites.js";
import { registerAllTools, type ToolContext } from "./tools/index.js";

export const SERVER_NAME = "wpagent";
export const SERVER_VERSION = "0.10.0";

export type CreateServerOptions = {
  sites?: SiteConfig[];
  selectedSiteId?: string;
  useEnvSites?: boolean;
  onSelectSite?: (id: string) => void | Promise<void>;
};

export async function createServer(options: CreateServerOptions = {}): Promise<McpServer> {
  const connected =
    options.sites ??
    (options.useEnvSites === false ? [] : await loadSites());

  const ctx: ToolContext = {
    sites: connected,
    selectedSiteId: options.selectedSiteId ?? connected[0]?.id,
    setSelectedSiteId(id: string) {
      ctx.selectedSiteId = id;
      void options.onSelectSite?.(id);
    },
  };

  const server = new McpServer({
    name: SERVER_NAME,
    version: SERVER_VERSION,
  });

  registerAllTools(server, ctx);
  return server;
}
