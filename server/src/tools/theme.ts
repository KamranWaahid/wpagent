import { z } from "zod";
import { confirmSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerThemeTools: RegisterTools = (server, ctx) => {
  server.tool(
    "create_draft_theme",
    "Clone the active WordPress theme into a sandbox. Live files are never written until publish_draft_theme.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/draft-theme", method: "POST", body: {} });
    }),
  );

  server.tool(
    "get_draft_theme",
    "Show whether a draft theme sandbox exists.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/draft-theme" });
    }),
  );

  server.tool(
    "delete_draft_theme",
    "Delete the draft sandbox. Requires confirm=true. Does not touch the live theme.",
    { site: siteIdSchema, confirm: confirmSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/draft-theme/delete",
        method: "POST",
        body: { confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "publish_draft_theme",
    "Copy the draft over the live theme after the user reviewed the preview URL. Requires confirm=true. Keeps a backup directory and rolls back if the homepage fatals.",
    { site: siteIdSchema, confirm: confirmSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/draft-theme/publish",
        method: "POST",
        body: { confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "get_theme_preview_url",
    "Return a URL that renders the draft theme. The live site stays on the current theme.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/draft-theme/preview" });
    }),
  );

  server.tool(
    "list_theme_files",
    "List files in the draft theme, or the live theme read-only if no draft exists. Pass glob (inc/*.php) or prefix (inc/) so large themes are not truncated before the files you need.",
    {
      site: siteIdSchema,
      glob: z.string().optional().describe("fnmatch-style filter, e.g. inc/*.php or inc/**"),
      prefix: z.string().optional().describe("Directory prefix, e.g. inc/"),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/theme/files",
        query: { glob: args.glob, prefix: args.prefix },
      });
    }),
  );

  server.tool(
    "read_theme_file",
    "Read one theme file (draft if present).",
    { site: siteIdSchema, path: z.string().min(1) },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/theme/file", query: { path: args.path } });
    }),
  );

  server.tool(
    "write_theme_file",
    "Write a file in the draft theme only. PHP is syntax-checked and not executed. Refuses if no draft exists.",
    {
      site: siteIdSchema,
      path: z.string().min(1),
      content: z.string(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/theme/file",
        method: "POST",
        body: { path: args.path, content: args.content },
      });
    }),
  );

  server.tool(
    "search_theme_files",
    "Search text inside the sandboxed theme files (including inc/). samples/** is excluded by default so demo XML does not drown out real matches. Pass exclude_glob=\"\" to search everything.",
    {
      site: siteIdSchema,
      query: z.string().min(1),
      path: z.string().optional().describe("Only search under this prefix, e.g. inc/"),
      exclude_glob: z
        .string()
        .optional()
        .describe("Comma-separated globs to skip. Default samples/**. Empty string disables the default."),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/theme/search",
        query: {
          query: args.query,
          path: args.path,
          exclude_glob: args.exclude_glob,
        },
      });
    }),
  );
};
