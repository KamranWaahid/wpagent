import { z } from "zod";
import { confirmSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerCliTools: RegisterTools = (server, ctx) => {
  server.tool(
    "list_wp_cli_commands",
    "List the WP-CLI-style commands WPAgent will run in PHP. Anything else is refused. There is no shell.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/cli/commands" });
    }),
  );

  server.tool(
    "run_wp_cli",
    "Run one allowlisted command such as `plugin list --status=active`, `post delete 42`, or `db query \"SELECT ID FROM wp_posts\"`. `post delete` (and `post update --post_status=trash`) works for posts and pages. Destructive verbs (plugin delete, user delete) return approval_required; re-run with the same command, confirm=true, and that approval_id.",
    {
      site: siteIdSchema,
      command: z.string().min(3),
      confirm: confirmSchema,
      approval_id: z.string().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/cli/run",
        method: "POST",
        body: {
          command: args.command,
          confirm: args.confirm === true,
          approval_id: args.approval_id,
        },
      });
    }),
  );
};
