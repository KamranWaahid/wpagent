# Changelog

All notable changes to WPAgent are documented here. The WordPress plugin and MCP server share the same version.

## [0.15.0] — 2026-09-08

More store operations, still default-deny.

- Customers, revenue report, low stock, reviews, variations, tax rates
- Create/update coupons; toggle Woo transactional emails (no sending, no recipient changes)
- Staff-only order notes; refunds recorded without calling the payment gateway
- Yoast / Rank Math title, description, and canonical per post
- Order status: pending, on-hold, processing, completed, cancelled, trash

Secrets, payment-gateway writes, and mail to arbitrary customers stay blocked.

## [0.14.0] — 2026-09-08

Famous-plugin coverage without secrets.

- WooCommerce products (list / get / update name, status, stock, prices) and coupons
- Shipping zones (titles and costs; no carrier keys)
- `get_integrations_status` — Google Site Kit, Listings & Ads, MonsterInsights, Meta pixel/catalog, Yoast / Rank Math
- Payment flags: PayPal, Square, COD, BACS, cheque (plus existing WooPayments / Stripe)
- More store options: address, units, reviews, stock, shop/cart/checkout pages
- Still forbidden: OAuth tokens, API keys, licenses, webhook secrets, bank account numbers, customer mail

## [0.13.0] — 2026-09-08

WooCommerce store ops and mail probes without exposing payment or SMTP secrets.

- `send_test_mail` (`confirm: true`) — `admin_email`, Woo From/Reply-To, or same host as the site
- `get_mail_status` — `mail()` vs `disable_functions`, SMTP plugins, FluentSMTP host/port, recent logs
- `get_payment_status` — WooPayments / Stripe enabled and test-mode flags only
- `list_orders` / `get_order` — HPOS-safe; no card data
- `update_order` — `cancelled` or `trash` only (`confirm: true`)
- `update_option` — guest checkout, tax display, force SSL checkout, Woo email identity
- `get_site_health` includes a `mail` block
- `query_db` accepts `SHOW TABLES` and `SHOW TABLES LIKE`

Payment-gateway settings, SMTP passwords, and mail to arbitrary customers stay forbidden.

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

[0.15.0]: https://github.com/KamranWaahid/wpagent/releases/tag/v0.15.0
[0.14.0]: https://github.com/KamranWaahid/wpagent/releases/tag/v0.14.0
[0.13.0]: https://github.com/KamranWaahid/wpagent/releases/tag/v0.13.0
[0.12.0]: https://github.com/KamranWaahid/wpagent/releases/tag/v0.12.0
