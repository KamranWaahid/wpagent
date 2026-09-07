#!/usr/bin/env node
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { createServer } from "./create-server.js";
import { startHttpServer } from "./http.js";

async function main(): Promise<void> {
  const http = process.argv.includes("--http") || process.env.WPAGENT_HTTP === "1";

  if (http) {
    await startHttpServer();
    return;
  }

  const server = await createServer();
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err: unknown) => {
  const message = err instanceof Error ? err.message : String(err);
  process.stderr.write(`WPAgent MCP server failed: ${message}\n`);
  process.exit(1);
});
