=== WPAgent ===
Contributors: kamranwaahid
Tags: mcp, ai, rest-api, application-passwords
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.12.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure MCP bridge so AI assistants can manage this WordPress site through an allowlisted, capability-checked REST API.

== Description ==

WPAgent exposes a versioned REST namespace (`wpagent/v1`) for the WPAgent MCP server. Authentication uses WordPress Application Passwords. Every command is default-deny, capability-checked on the server, and write actions are append-only audit-logged.

= External requests =

The plugin talks to WordPress itself over the REST API. The only optional outbound HTTPS request is when you click **Authorize AI connection** and supply an MCP server URL you control. That request posts an Application Password to `{your-mcp-url}/auth/link-by-pairing`. There is no hardcoded third-party API.

== Installation ==

1. Upload the `wpagent` folder to `/wp-content/plugins/`.
2. Activate WPAgent.
3. For local clients: generate an Application Password and paste the MCP snippet into Claude Desktop or Cursor.
4. For hosted clients (claude.ai / ChatGPT): verify email on your MCP server, then enter that server URL and pairing code here.

== Changelog ==

= 0.12.0 =
* WooCommerce store settings (shipping hide-when-free, selling/shipping countries, shipping instance titles, checkout privacy/terms) are allowlisted. Stripe/WooPayments settings stay forbidden.
* Option read/write errors include reason, hint (option list), and an allowed_sample.
* inspect_rendered_html returns final_url/redirected/requested_url and a body-centered html_excerpt (optional contains).
* query_db marks truncated when LIMIT is hit and redacts payment/Rocket secrets.
* get_page/get_post warn when Elementor _elementor_data is present. search_theme_files reports scoped_to theme only.

= 0.11.0 =
* delete_page / permanent_delete_page, and update_page status=trash. Wrong-type IDs say "id 42 is a page" instead of Post not found.
* Elementor save_builder_page can merge one widget via widget_id + settings.
* inspect_rendered_html add_to_cart primes a WooCommerce session for /checkout/.
* Theme search includes inc/, excludes samples/** by default, and list_theme_files accepts glob/prefix.
* Draft theme preview blocks trash/delete from draft PHP. read_theme_file reports draft=true once a draft is recorded.
* WP-CLI: post delete and post update --post_status=trash (posts and pages).

= 0.10.1 =
* Allowlist Headers Security Advanced & HSTS WP settings (delivery modes, CSP, HSTS flags). The probe token stays forbidden.

= 0.10.0 =
* Flush full-page caches through each plugin's own API after builder/theme/plugin writes. Nav menus (create, items, locations). Install plugins from wordpress.org by slug only, with confirm.

= 0.9.0 =
* read_pdf extracts text from a media-library PDF (attachment id, uploads path, or same-origin uploads URL). Files outside wp-content/uploads are refused. No shell.

= 0.8.0 =
* Page-builder save pipelines for Elementor, Bricks, Beaver Builder, Breakdance, and Divi. Writes go through each builder's own save path. New pages stay draft unless explicit_publish is true.

= 0.7.0 =
* Abilities API passthrough: discover, inspect schemas, and run plugin abilities on this site (WordPress 6.9+). Non-readonly runs need confirm.

= 0.6.0 =
* Allowlisted WP-CLI-style commands run in PHP (no shell). Destructive verbs issue a single-use approval ticket instead of executing.

= 0.5.0 =
* Draft theme sandbox: clone, preview URL, sandboxed file read/write/search, publish with backup and health rollback. Live theme files are never written until publish. MCP stays on your machine.

= 0.4.0 =
* Core updates are a standalone command. Plugin/theme updates snapshot files and probe health. Search-replace returns unified diffs. Admins can add extra option keys (forbidden keys stay blocked).

= 0.3.0 =
* Pairing flow to link this site to a hosted multi-tenant MCP session.

= 0.1.0 =
* Phase 1: connection, list/get posts, site health, safety layer, audit log.
