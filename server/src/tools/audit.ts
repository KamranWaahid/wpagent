import { z } from "zod";
import { paginationSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerAuditTools: RegisterTools = (server, ctx) => {
  server.tool(
    "get_audit_log",
    "Read the append-only WPAgent audit log of write actions.",
    {
      site: siteIdSchema,
      ...paginationSchema,
      command: z.string().optional(),
      result: z.enum(["success", "denied", "error"]).optional(),
      user_id: z.number().int().positive().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/audit-log",
        query: {
          page: args.page,
          per_page: args.per_page,
          command: args.command,
          result: args.result,
          user_id: args.user_id,
        },
      });
    }),
  );
};
