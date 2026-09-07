import { z } from "zod";
import { WPAgentError } from "../errors.js";
import { confirmSchema, siteIdSchema } from "../schemas.js";
import { WordPressClient } from "../wordpress-client.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerSiteTools: RegisterTools = (server, ctx) => {
  server.tool(
    "list_connected_sites",
    "Fleet view: health and available updates for every WordPress site linked to this session (never other users' sites).",
    {
      include_updates: z.boolean().optional().describe("Include core/plugin/theme update availability (default true)"),
    },
    wrap(async (args) => {
      const includeUpdates = args.include_updates !== false;
      const selected = ctx.selectedSiteId ?? ctx.sites[0]?.id ?? null;
      const sites = await Promise.all(
        ctx.sites.map(async (site) => {
          const client = new WordPressClient(site);
          try {
            const health = (await client.request({
              path: "/site/health",
              query: includeUpdates ? { updates: "1" } : undefined,
            })) as Record<string, unknown>;
            return {
              id: site.id,
              url: site.url,
              username: site.username,
              ok: true,
              wordpress: health.wordpress,
              php: health.php,
              theme: health.theme,
              plugin_count: health.plugin_count,
              issues: health.issues,
              updates: health.updates ?? null,
            };
          } catch (err) {
            const mapped = WPAgentError.fromUnknown(err);
            return {
              id: site.id,
              url: site.url,
              username: site.username,
              ok: false,
              error: mapped.toJSON(),
            };
          }
        }),
      );
      return { selected, sites };
    }),
  );

  server.tool(
    "select_site",
    "Select the default site for subsequent tool calls in this session.",
    {
      site: z.string().min(1),
    },
    wrap(async (args) => {
      const { site } = clientFor(ctx, args.site);
      ctx.setSelectedSiteId(site.id);
      return { selected: site.id, url: site.url };
    }),
  );

  server.tool(
    "get_site_health",
    "Inspect PHP version, active theme/plugins, disk space, mail()/SMTP extras, and known issues.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/site/health", query: { updates: "1" } });
    }),
  );

  server.tool(
    "list_plugins",
    "List installed plugins and whether they are active.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/plugins" });
    }),
  );

  server.tool(
    "list_themes",
    "List installed themes.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/themes" });
    }),
  );

  server.tool(
    "install_plugin",
    "Install a plugin from wordpress.org by slug (e.g. akismet). Refuses paths, GitHub URLs, and any host other than downloads.wordpress.org. Does not activate. Requires confirm=true.",
    {
      site: siteIdSchema,
      slug: z.string().min(1).describe("wordpress.org plugin slug, e.g. akismet"),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/plugins/install",
        method: "POST",
        body: { slug: args.slug, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "flush_page_cache",
    "Flush the object cache and any known full-page cache (LiteSpeed, WP Rocket, Super Cache, W3TC, Autoptimize, Cache Enabler, WP Fastest Cache, Breeze, SiteGround, FlyingPress, Hummingbird). Also runs automatically after builder saves, theme publish, plugin/theme/core updates, and menu writes.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/cache/flush", method: "POST", body: {} });
    }),
  );

  server.tool(
    "activate_plugin",
    "Activate a plugin. Requires confirm=true. Never used for WordPress core.",
    {
      site: siteIdSchema,
      plugin: z.string().describe("Plugin file, e.g. akismet/akismet.php"),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/plugins/activate",
        method: "POST",
        body: { plugin: args.plugin, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "deactivate_plugin",
    "Deactivate a plugin. Requires confirm=true.",
    {
      site: siteIdSchema,
      plugin: z.string(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/plugins/deactivate",
        method: "POST",
        body: { plugin: args.plugin, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "update_plugin",
    "Update a plugin. Requires confirm=true. WordPress core updates are refused.",
    {
      site: siteIdSchema,
      plugin: z.string(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/plugins/update",
        method: "POST",
        body: { plugin: args.plugin, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "update_theme",
    "Update a theme. Requires confirm=true.",
    {
      site: siteIdSchema,
      stylesheet: z.string(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/themes/update",
        method: "POST",
        body: { stylesheet: args.stylesheet, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "update_core",
    "Update WordPress core only. Never bundled with plugin/theme updates. Requires confirm=true and a separate confirmation turn.",
    {
      site: siteIdSchema,
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/core/update",
        method: "POST",
        body: { confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "get_option",
    "Read an allowlisted wp_options key. Arbitrary keys are rejected.",
    {
      site: siteIdSchema,
      key: z.string().min(1),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/options/${encodeURIComponent(args.key)}` });
    }),
  );

  server.tool(
    "update_option",
    "Update an allowlisted option. Requires confirm=true. Includes Woo store address, stock, tax, SSL, shop pages, and email From/Reply-To. siteurl/home/admin_email, payment-gateway blobs, Site Kit credentials, and Facebook tokens cannot be written.",
    {
      site: siteIdSchema,
      key: z.string().min(1),
      value: z.unknown(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/options/${encodeURIComponent(args.key)}`,
        method: "POST",
        body: { value: args.value, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "run_search_replace",
    "Search-replace post/page content. Dry-run by default; set confirm=true to apply after previewing the diff.",
    {
      site: siteIdSchema,
      search: z.string().min(1),
      replace: z.string(),
      post_types: z.array(z.enum(["post", "page"])).optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/search-replace",
        method: "POST",
        body: {
          search: args.search,
          replace: args.replace,
          post_types: args.post_types,
          confirm: args.confirm === true,
        },
      });
    }),
  );
};
