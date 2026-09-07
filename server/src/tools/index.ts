import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { registerAbilityTools } from "./abilities.js";
import { registerAuditTools } from "./audit.js";
import { registerBuilderTools } from "./builders.js";
import { registerCliTools } from "./cli.js";
import type { ToolContext } from "./common.js";
import { registerContentTools } from "./content.js";
import { registerDiagnosticTools } from "./diagnostics.js";
import { registerMediaTools } from "./media.js";
import { registerSiteTools } from "./site.js";
import { registerThemeTools } from "./theme.js";
import { registerUserTools } from "./users.js";
import { registerWooTools } from "./woo.js";

export function registerAllTools(server: McpServer, ctx: ToolContext): void {
  registerContentTools(server, ctx);
  registerMediaTools(server, ctx);
  registerSiteTools(server, ctx);
  registerWooTools(server, ctx);
  registerThemeTools(server, ctx);
  registerUserTools(server, ctx);
  registerAuditTools(server, ctx);
  registerDiagnosticTools(server, ctx);
  registerCliTools(server, ctx);
  registerAbilityTools(server, ctx);
  registerBuilderTools(server, ctx);
}

export type { ToolContext } from "./common.js";
