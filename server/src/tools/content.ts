import { z } from "zod";
import { confirmSchema, explicitPublishSchema, paginationSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerContentTools: RegisterTools = (server, ctx) => {
  server.tool(
    "list_posts",
    "List WordPress posts. Does not publish or edit content.",
    {
      site: siteIdSchema,
      ...paginationSchema,
      search: z.string().optional(),
      status: z.string().optional().describe("Comma-separated statuses, e.g. draft,publish"),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/posts",
        query: { page: args.page, per_page: args.per_page, search: args.search, status: args.status },
      });
    }),
  );

  server.tool(
    "get_post",
    "Get a single WordPress post by id.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/posts/${args.id}` });
    }),
  );

  server.tool(
    "create_post",
    'Create a post. Status defaults to draft unless explicit_publish is true AND the user asked to publish.',
    {
      site: siteIdSchema,
      title: z.string().min(1),
      content: z.string().optional(),
      excerpt: z.string().optional(),
      slug: z.string().optional(),
      status: z.enum(["draft", "pending", "private", "publish", "future"]).optional().describe("Create cannot use trash; use delete_post on an existing item."),
      explicit_publish: explicitPublishSchema,
      categories: z.array(z.union([z.string(), z.number()])).optional(),
      tags: z.array(z.union([z.string(), z.number()])).optional(),
      featured_media: z.number().int().positive().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/posts", method: "POST", body: args });
    }),
  );

  server.tool(
    "update_post",
    "Update a post. Publishing still requires explicit_publish=true. status=trash moves the post to trash (same as delete_post).",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      title: z.string().optional(),
      content: z.string().optional(),
      excerpt: z.string().optional(),
      slug: z.string().optional(),
      status: z.enum(["draft", "pending", "private", "publish", "future", "trash"]).optional(),
      explicit_publish: explicitPublishSchema,
      categories: z.array(z.union([z.string(), z.number()])).optional(),
      tags: z.array(z.union([z.string(), z.number()])).optional(),
      featured_media: z.number().int().positive().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      const { id, site: _site, ...body } = args;
      return client.request({ path: `/posts/${id}`, method: "POST", body });
    }),
  );

  server.tool(
    "delete_post",
    "Move a post to trash. Permanent deletion is a separate tool and requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/posts/${args.id}`, method: "DELETE" });
    }),
  );

  server.tool(
    "permanent_delete_post",
    "Permanently delete a post. Requires confirm=true after the user agrees.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/posts/${args.id}/permanent-delete`,
        method: "POST",
        body: { confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "list_pages",
    "List WordPress pages.",
    {
      site: siteIdSchema,
      ...paginationSchema,
      search: z.string().optional(),
      status: z.string().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/pages",
        query: { page: args.page, per_page: args.per_page, search: args.search, status: args.status },
      });
    }),
  );

  server.tool(
    "get_page",
    "Get a single page by id.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/pages/${args.id}` });
    }),
  );

  server.tool(
    "create_page",
    "Create a page. Defaults to draft unless explicit_publish is true.",
    {
      site: siteIdSchema,
      title: z.string().min(1),
      content: z.string().optional(),
      excerpt: z.string().optional(),
      slug: z.string().optional(),
      status: z.enum(["draft", "pending", "private", "publish", "future"]).optional().describe("Create cannot use trash; use delete_page on an existing item."),
      explicit_publish: explicitPublishSchema,
      parent: z.number().int().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/pages", method: "POST", body: args });
    }),
  );

  server.tool(
    "update_page",
    "Update a page. Publishing still requires explicit_publish=true. status=trash moves the page to trash (same as delete_page).",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      title: z.string().optional(),
      content: z.string().optional(),
      excerpt: z.string().optional(),
      slug: z.string().optional(),
      status: z.enum(["draft", "pending", "private", "publish", "future", "trash"]).optional(),
      explicit_publish: explicitPublishSchema,
      parent: z.number().int().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      const { id, site: _site, ...body } = args;
      return client.request({ path: `/pages/${id}`, method: "POST", body });
    }),
  );

  server.tool(
    "delete_page",
    "Move a page to trash. Works for pages (including unused drafts). Permanent deletion is a separate tool and requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/pages/${args.id}`, method: "DELETE" });
    }),
  );

  server.tool(
    "permanent_delete_page",
    "Permanently delete a page. Requires confirm=true after the user agrees.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/pages/${args.id}/permanent-delete`,
        method: "POST",
        body: { confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "search_content",
    "Full-text search across posts and pages.",
    {
      site: siteIdSchema,
      q: z.string().min(2),
      ...paginationSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/search",
        query: { q: args.q, page: args.page, per_page: args.per_page },
      });
    }),
  );

  server.tool(
    "manage_taxonomy",
    "List, create, or assign categories and tags.",
    {
      site: siteIdSchema,
      action: z.enum(["list", "create", "assign"]),
      taxonomy: z.enum(["category", "post_tag"]).optional(),
      name: z.string().optional(),
      slug: z.string().optional(),
      post_id: z.number().int().positive().optional(),
      terms: z.array(z.union([z.string(), z.number()])).optional(),
      append: z.boolean().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      const method = args.action === "list" ? "GET" : "POST";
      return client.request({
        path: "/taxonomy",
        method,
        query: args.action === "list" ? { action: args.action, taxonomy: args.taxonomy } : undefined,
        body: args.action === "list" ? undefined : args,
      });
    }),
  );

  server.tool(
    "list_nav_menus",
    "List navigation menus and the theme's registered locations.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/menus" });
    }),
  );

  server.tool(
    "get_nav_menu",
    "Get one navigation menu and its items.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/menus/${args.id}` });
    }),
  );

  server.tool(
    "manage_nav_menu",
    "Create a menu, add/update/remove an item, or assign a theme location. Item types: custom (needs url), post, page, category (need object_id).",
    {
      site: siteIdSchema,
      action: z.enum(["create", "add_item", "update_item", "remove_item", "assign_location"]),
      name: z.string().optional(),
      menu_id: z.number().int().positive().optional(),
      item_id: z.number().int().positive().optional(),
      type: z.enum(["custom", "post", "page", "category"]).optional(),
      title: z.string().optional(),
      url: z.string().optional(),
      object_id: z.number().int().positive().optional(),
      parent_id: z.number().int().nonnegative().optional(),
      position: z.number().int().nonnegative().optional(),
      location: z.string().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      const { site: _site, ...body } = args;
      return client.request({ path: "/menus", method: "POST", body });
    }),
  );

  server.tool(
    "delete_nav_menu",
    "Delete a navigation menu. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/menus/${args.id}/delete`,
        method: "POST",
        body: { confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "manage_comments",
    "List comments or moderate them (approve, spam, trash). delete moves to trash.",
    {
      site: siteIdSchema,
      action: z.enum(["list", "approve", "spam", "trash", "delete"]),
      id: z.number().int().positive().optional(),
      post_id: z.number().int().positive().optional(),
      status: z.string().optional(),
      ...paginationSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      const method = args.action === "list" ? "GET" : "POST";
      return client.request({
        path: "/comments",
        method,
        query:
          args.action === "list"
            ? { action: "list", post_id: args.post_id, status: args.status, page: args.page, per_page: args.per_page }
            : undefined,
        body: args.action === "list" ? undefined : args,
      });
    }),
  );
};
