import { z } from "zod";
import { paginationSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerUserTools: RegisterTools = (server, ctx) => {
  server.tool(
    "list_users",
    "List WordPress users (id, login, name, email, roles).",
    {
      site: siteIdSchema,
      ...paginationSchema,
      search: z.string().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/users",
        query: { page: args.page, per_page: args.per_page, search: args.search },
      });
    }),
  );

  server.tool(
    "get_current_user_capabilities",
    "Show the authenticated user, WordPress capabilities, and which WPAgent commands they may run. Call this first when a command might fail.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/me/capabilities" });
    }),
  );
};
