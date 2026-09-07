import { z } from "zod";
import { explicitPublishSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

const builderSlug = z
  .enum(["elementor", "bricks", "beaver", "breakdance", "divi"])
  .describe("Installed page builder to target.");

export const registerBuilderTools: RegisterTools = (server, ctx) => {
  server.tool(
    "list_page_builders",
    "List Elementor, Bricks, Beaver Builder, Breakdance, and Divi and whether each is active on this site.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/builders" });
    }),
  );

  server.tool(
    "list_builder_catalog",
    "List widgets, elements, or modules for one active page builder. Pass item to include that entry's schema when the builder exposes one.",
    {
      site: siteIdSchema,
      builder: builderSlug,
      item: z.string().optional().describe("Optional widget/element/module slug for a detailed schema."),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/builders/${args.builder}`,
        query: { item: args.item },
      });
    }),
  );

  server.tool(
    "get_builder_page",
    "Read the builder layout stored on a post or page. Does not change content.",
    {
      site: siteIdSchema,
      builder: builderSlug,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/builders/${args.builder}/pages/${args.id}` });
    }),
  );

  server.tool(
    "save_builder_page",
    "Create or update a page through the builder's own save pipeline (not raw post meta). Status stays draft unless explicit_publish is true. Payload shape depends on builder: Elementor/Bricks use elements, Beaver uses data, Breakdance uses tree, Divi uses content shortcodes. For Elementor, pass widget_id + settings to merge one widget without replacing the full layout.",
    {
      site: siteIdSchema,
      builder: builderSlug,
      id: z.number().int().positive().optional(),
      title: z.string().optional(),
      post_type: z.string().optional(),
      status: z.enum(["draft", "pending", "private", "publish", "future"]).optional(),
      explicit_publish: explicitPublishSchema,
      elements: z.array(z.unknown()).optional(),
      widget_id: z.string().min(1).optional().describe("Elementor node id to patch. Rejected if the widget is missing."),
      settings: z.record(z.string(), z.unknown()).optional().describe("Settings merged onto the widget identified by widget_id."),
      data: z.record(z.string(), z.unknown()).optional(),
      tree: z.record(z.string(), z.unknown()).optional(),
      tree_json_string: z.string().optional(),
      content: z.string().optional(),
      template_type: z.string().optional(),
      page_template: z.string().optional(),
      template: z.enum(["blank_canvas", "theme"]).optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      const { builder, site: _site, ...body } = args;
      return client.request({
        path: `/builders/${builder}/save`,
        method: "POST",
        body,
      });
    }),
  );
};
