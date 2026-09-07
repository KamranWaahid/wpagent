import { z } from "zod";
import { paginationSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerMediaTools: RegisterTools = (server, ctx) => {
  server.tool(
    "list_media",
    "List media library items.",
    {
      site: siteIdSchema,
      ...paginationSchema,
      search: z.string().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/media",
        query: { page: args.page, per_page: args.per_page, search: args.search },
      });
    }),
  );

  server.tool(
    "upload_media",
    "Upload media from a URL or base64 payload. Pass attribution when the file came from a stock photo search.",
    {
      site: siteIdSchema,
      url: z.string().url().optional(),
      base64: z.string().optional(),
      filename: z.string().optional(),
      title: z.string().optional(),
      alt: z.string().optional(),
      attribution: z.string().optional(),
    },
    wrap(async (args) => {
      if (!args.url && !args.base64) {
        throw new Error("Provide url or base64.");
      }
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/media", method: "POST", body: args });
    }),
  );

  server.tool(
    "read_pdf",
    "Extract text from a PDF in this site's media library. Provide attachment id, an uploads-relative path, or a same-origin uploads URL. Does not execute binaries or read files outside wp-content/uploads.",
    {
      site: siteIdSchema,
      id: z.number().int().positive().optional(),
      path: z.string().optional().describe("Path relative to wp-content/uploads, e.g. 2026/09/brief.pdf"),
      url: z.string().url().optional(),
      max_pages: z.number().int().min(1).max(40).optional(),
    },
    wrap(async (args) => {
      if (!args.id && !args.path && !args.url) {
        throw new Error("Provide id, path, or url.");
      }
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/media/read-pdf",
        method: "POST",
        body: {
          id: args.id,
          path: args.path,
          url: args.url,
          max_pages: args.max_pages,
        },
      });
    }),
  );

  server.tool(
    "search_stock_photos",
    "Search Unsplash for stock photos. Does not upload; call upload_media with the image URL and attribution.",
    {
      query: z.string().min(2),
      per_page: z.number().int().min(1).max(20).optional(),
    },
    wrap(async (args) => {
      const key = process.env.UNSPLASH_ACCESS_KEY;
      if (!key) {
        return {
          error: true,
          code: "validation",
          message: "Set UNSPLASH_ACCESS_KEY to use search_stock_photos.",
        };
      }
      const url = new URL("https://api.unsplash.com/search/photos");
      url.searchParams.set("query", args.query);
      url.searchParams.set("per_page", String(args.per_page ?? 5));
      const response = await fetch(url, {
        headers: { Authorization: `Client-ID ${key}`, "Accept-Version": "v1" },
      });
      if (!response.ok) {
        throw new Error(`Unsplash request failed (${response.status})`);
      }
      const data = (await response.json()) as {
        results?: Array<{
          id: string;
          alt_description?: string;
          urls?: { regular?: string; full?: string };
          user?: { name?: string; links?: { html?: string } };
          links?: { html?: string };
        }>;
      };
      return {
        query: args.query,
        items: (data.results ?? []).map((photo) => ({
          id: photo.id,
          alt: photo.alt_description ?? "",
          url: photo.urls?.regular ?? photo.urls?.full,
          photographer: photo.user?.name,
          photographer_url: photo.user?.links?.html,
          unsplash_url: photo.links?.html,
          attribution: `Photo by ${photo.user?.name ?? "Unknown"} on Unsplash`,
        })),
      };
    }),
  );
};
