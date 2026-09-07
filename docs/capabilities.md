# What WPAgent can do

Capabilities run on **your** WordPress site. Application Passwords and tool calls stay local (stdio or a tunnel you start). Nothing is sent to a third-party MCP host.

## Theme workflow

1. `create_draft_theme` — clone active theme to `{slug}-wpagent-draft`
2. `list_theme_files` / `read_theme_file` / `write_theme_file` / `search_theme_files` — draft only for writes. List accepts `glob` / `prefix`. Search includes `inc/` and excludes `samples/**` by default.
3. `get_theme_preview_url` — `?wpagent_preview=…` renders the draft; live theme stays. Draft PHP cannot trash/delete posts or pages during preview.
4. `publish_draft_theme` with `confirm: true` — backup live, copy draft over, health-probe, rollback on fatal
5. `delete_draft_theme` with `confirm: true` — drop the sandbox

Commercial parent themes (Divi, Avada, …) are refused. Child themes are allowed. PHP writes are parsed, not executed. `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` block writes; reads still work.

## WP-CLI style (no SSH)

`list_wp_cli_commands` then `run_wp_cli` with a line like `plugin list --status=active`. PHP dispatch only — `;`, `|`, and backticks are rejected. `db query` is still read-only (mutating SQL is refused, not approval-gated). `post delete <id>` (and `post update <id> --post_status=trash`) moves posts **or pages** to trash. `--force` is refused.

Destructive verbs (`plugin delete`, `user delete`):

1. First call returns `approval_required`, a preview, and `approval_id` (10 minutes, single-use).
2. Same command again with `confirm: true` and that `approval_id`.

`user delete` also requires `--reassign=<id>` and will not delete the connected account. WPAgent cannot deactivate or delete itself. Draft themes cannot be `theme activate`’d — use `publish_draft_theme`.

## Abilities API (WordPress 6.9+)

`discover_abilities` → `get_ability_info` → `run_ability`. Runs `WP_Ability::execute()` on **this** site (the plugin’s own permission callback and schemas still apply). Sites older than 6.9 get a clear error. Abilities that are not marked read-only need `confirm: true`.

## Page builders

`list_page_builders` → `list_builder_catalog` → `get_builder_page` → `save_builder_page`. Supported: Elementor, Bricks, Beaver Builder, Breakdance, Divi. Saves call that builder’s own writer. Status stays **draft** unless `explicit_publish: true`. Elementor also accepts `widget_id` + `settings` to merge one widget without replacing the layout. Missing builders return a clear “not active” error.

`delete_page` / `update_page` with `status: trash` move a page to trash (do not use `delete_post` for pages). `inspect_rendered_html` accepts `add_to_cart` so `/checkout/` can be fetched with a WooCommerce session.

## PDF text

`read_pdf` with an attachment `id`, an uploads-relative `path`, or a same-origin uploads `url`. The file must sit under `wp-content/uploads`, start with `%PDF-`, and stay under 10 MiB. Text is extracted in PHP (Tj / TJ, FlateDecode). Scanned PDFs return an empty body plus `empty_reason`. No `pdftotext` shell.

## Page cache, menus, plugin install

- `flush_page_cache` calls each known cache plugin’s own purge (LiteSpeed, WP Rocket, Super Cache, W3TC, Autoptimize, …) plus the object cache. The same flush runs after builder saves, theme publish, plugin/theme/core updates, search-replace apply, and menu writes.
- `list_nav_menus` / `get_nav_menu` / `manage_nav_menu` / `delete_nav_menu` use WordPress menu APIs. Deleting a menu needs `confirm: true`.
- `install_plugin` takes a wordpress.org **slug** only. The zip must come from `https://downloads.wordpress.org/plugin/`. It does not activate. `confirm: true` required. `DISALLOW_FILE_MODS` blocks it. CLI: `plugin install akismet` (approval ticket).

## Clients

| Client | How it reaches WordPress |
| --- | --- |
| Cursor, Claude Desktop | stdio → `server/dist/index.js` on this computer |
| ChatGPT | personal `--http` on localhost + a tunnel you start (`docs/chatgpt.md`). Requires `WPAGENT_HTTP_TOKEN`. |
