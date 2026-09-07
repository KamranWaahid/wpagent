# Changelog

All notable changes to WPAgent are documented here. The WordPress plugin and MCP server share the same version.

## [0.12.0] — 2026-09-08

First public release.

WordPress plugin (`plugin/`, GPL-2.0-or-later) plus a local MCP server (`server/`, MIT) so Cursor, Claude Desktop, and ChatGPT can manage a self-hosted site. Credentials stay on your machine.

### Safety

- Draft-first writes unless `explicit_publish: true`
- Trash instead of permanent delete unless `confirm: true`
- Default-deny command allowlist and capability checks in PHP
- Read-only SQL (`SELECT` only), dry-run search-replace, append-only audit log
- Encrypted secrets; Application Passwords are never stored by the plugin

### Included

- Content, pages, media, users, and site health
- Draft theme sandbox, preview, publish with backup and health rollback
- WP-CLI-style commands in PHP (no shell); destructive verbs use a one-time approval ticket
- WordPress 6.9+ Abilities API passthrough
- Page-builder save pipelines: Elementor, Bricks, Beaver Builder, Breakdance, Divi
- PDF text extract from the media library
- Page-cache flush, nav menus, wordpress.org plugin install by slug
- WooCommerce storefront option allowlist (payment secrets stay forbidden)
- Personal HTTP + tunnel for ChatGPT; optional hosted multi-tenant stack in `deploy/`

### Clients

- Cursor / Claude Desktop: stdio → `server/dist/index.js`
- ChatGPT Developer mode: localhost HTTP + tunnel, Bearer `WPAGENT_HTTP_TOKEN`

## [0.11.0]

- `delete_page` / `permanent_delete_page`, and `update_page` `status=trash`
- Elementor `save_builder_page` can merge one widget via `widget_id` + `settings`
- `inspect_rendered_html` `add_to_cart` primes a WooCommerce checkout session
- Theme search includes `inc/`, excludes `samples/**` by default
- Draft theme preview blocks trash/delete from draft PHP
- WP-CLI: `post delete` and `post update --post_status=trash` (posts and pages)

## [0.10.1]

- Allowlist Headers Security Advanced & HSTS WP settings; probe token stays forbidden

## [0.10.0]

- Flush full-page caches through each plugin’s own API after writes
- Nav menus (create, items, locations)
- Install plugins from wordpress.org by slug only, with confirm

## [0.9.0]

- `read_pdf` extracts text from a media-library PDF (no shell)

## [0.8.0]

- Page-builder save pipelines for Elementor, Bricks, Beaver Builder, Breakdance, and Divi

## [0.7.0]

- Abilities API: discover, inspect schemas, and run plugin abilities (WordPress 6.9+)

## [0.6.0]

- Allowlisted WP-CLI-style commands run in PHP; destructive verbs issue an approval ticket

## [0.5.0]

- Draft theme sandbox: clone, preview, sandboxed file I/O, publish with backup and rollback

## [0.4.0]

- Standalone core updates; plugin/theme updates snapshot files and probe health
- Search-replace returns unified diffs; extra option keys can be added (forbidden keys stay blocked)

## [0.3.0]

- Pairing flow to link a site to a hosted multi-tenant MCP session

## [0.1.0]

- Connection, list/get posts, site health, safety layer, audit log

[0.12.0]: https://github.com/KamranWaahid/wpagent/releases/tag/v0.12.0
