import { z } from "zod";
import { siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerDiagnosticTools: RegisterTools = (server, ctx) => {
  server.tool(
    "inspect_rendered_html",
    "Fetch a URL on the connected WordPress site and return simplified text, headings, and an HTML excerpt so you can verify a change rendered. Pass add_to_cart with a WooCommerce product id to prime a cart cookie before fetching (needed for /checkout/).",
    {
      site: siteIdSchema,
      url: z.string().url().optional(),
      path: z.string().optional().describe("Path on the site, e.g. /about/"),
      add_to_cart: z
        .number()
        .int()
        .positive()
        .optional()
        .describe("WooCommerce product id to add before the fetch so checkout/cart HTML is not the empty-cart view."),
      contains: z
        .string()
        .optional()
        .describe("Center html_excerpt on this substring (body text, not the document head)."),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/inspect-html",
        method: "POST",
        body: { url: args.url, path: args.path, add_to_cart: args.add_to_cart, contains: args.contains },
      });
    }),
  );

  server.tool(
    "query_db",
    "Run a read-only SELECT or SHOW TABLES / SHOW TABLES LIKE against the WordPress database. Mutating SQL, SHOW CREATE, and SHOW VARIABLES are rejected. A row limit is enforced on SELECT.",
    {
      site: siteIdSchema,
      sql: z.string().min(8),
      limit: z.number().int().min(1).max(500).optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/query",
        method: "POST",
        body: { sql: args.sql, limit: args.limit },
      });
    }),
  );
};
