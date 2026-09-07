import { z } from "zod";
import { confirmSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerAbilityTools: RegisterTools = (server, ctx) => {
  server.tool(
    "discover_abilities",
    "List WordPress/plugin abilities on this site (WordPress 6.9+ Abilities API). Runs on the site, not a cloud registry.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/abilities" });
    }),
  );

  server.tool(
    "get_ability_info",
    "Inspect one ability: description, schemas, whether it is read-only, and whether the connected user may run it.",
    { site: siteIdSchema, name: z.string().min(3) },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/abilities/${encodeURI(args.name)}` });
    }),
  );

  server.tool(
    "run_ability",
    "Execute a registered ability. The plugin's own permission_callback and schemas still apply. Non-readonly abilities require confirm=true.",
    {
      site: siteIdSchema,
      name: z.string().min(3),
      input: z.unknown().optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/abilities/${encodeURI(args.name)}/run`,
        method: "POST",
        body: { input: args.input ?? null, confirm: args.confirm === true },
      });
    }),
  );
};
