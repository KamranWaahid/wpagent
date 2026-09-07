# WPAgent

Open-source WordPress plugin + MCP server so Cursor, Claude, and ChatGPT can manage a self-hosted WordPress site in natural language.

Commands are **default-deny**, capability-checked in PHP, and written to an append-only audit log. Credentials stay on your machine — the MCP server never phones home.

[![Version](https://img.shields.io/github/v/release/KamranWaahid/wpagent?label=version)](https://github.com/KamranWaahid/wpagent/releases/tag/v0.12.0)
[![CI](https://github.com/KamranWaahid/wpagent/actions/workflows/ci.yml/badge.svg)](https://github.com/KamranWaahid/wpagent/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Plugin: GPLv2+](https://img.shields.io/badge/plugin-GPLv2%2B-green.svg)](plugin/LICENSE)

**Current version: [0.12.0](https://github.com/KamranWaahid/wpagent/releases/tag/v0.12.0)** — first public release (8 September 2026). See [CHANGELOG.md](CHANGELOG.md).

## Architecture

```
Cursor / Claude Desktop  ──stdio──►  server/dist/index.js   (your machine)
ChatGPT (Developer mode) ──HTTPS──►  tunnel ──► same --http (your machine)
        │
        ▼  WordPress Application Password (your env only)
WordPress plugin  (/plugin, REST namespace wpagent/v1)
```

You host everything. There is no cloud MCP and no third-party credential store.

## Safety model

Enforced in PHP, not only in tool descriptions:

1. **Draft-first** — creates stay `draft` unless `explicit_publish: true`.
2. **Trash, not delete** — permanent delete needs its own `confirm: true` call.
3. **Command allowlist** — unknown commands are rejected.
4. **Read-only SQL** — single `SELECT`, blocked keywords, row limit.
5. **Dry-run** — search-replace previews until `confirm: true`.
6. **Capability checks** on every REST request.
7. **Append-only audit log**.
8. **Encrypted secrets** — plugin API keys and hosted session Application Passwords use AES-256-GCM. The plugin never stores Application Passwords; WordPress core does.

## Requirements

- WordPress 6.0+ and PHP 8.1+
- Node.js 20+ (to run the MCP server)
- A WordPress [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) for the connecting user

## Connect Cursor or Claude Desktop (stdio)

1. Copy `plugin/` into `wp-content/plugins/wpagent` (or run `./scripts/build-plugin-zip.sh` and install the zip).
2. Activate **WPAgent**. On HTTP-only local sites, enable **Allow Application Passwords over HTTP**.
3. Generate an Application Password in wp-admin → WPAgent.
4. Build the server and point your MCP client at it:

```bash
cd server
npm install
npm run build
```

Cursor / Claude Desktop snippet (use placeholders — never commit real values):

```json
{
  "mcpServers": {
    "wpagent": {
      "command": "node",
      "args": ["/absolute/path/to/wpagent/server/dist/index.js"],
      "env": {
        "WPAGENT_URL": "https://your-wordpress.example",
        "WPAGENT_USERNAME": "admin",
        "WPAGENT_APP_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

Copy `.env.example` to `.env` for local shells. `.env` is gitignored.

## Connect ChatGPT (personal tunnel)

ChatGPT cannot spawn a local process. Run the same server over HTTP and put a tunnel in front:

```bash
export WPAGENT_URL="https://your-wordpress.example"
export WPAGENT_USERNAME="admin"
export WPAGENT_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
export WPAGENT_HTTP_TOKEN="a-long-random-string"
./scripts/chatgpt-personal.sh
./scripts/chatgpt-tunnel.sh
```

In ChatGPT Developer mode, add `https://<tunnel>/mcp` with **Bearer `WPAGENT_HTTP_TOKEN`**. Details: [`docs/chatgpt.md`](docs/chatgpt.md). Prefer OpenAI’s [Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels) if you do not want a public URL.

## What it can do

Content, draft themes, WP-CLI-style commands (PHP, no shell), Abilities API, page builders, PDFs, cache flush, menus, and wordpress.org plugin install. Full list: [`docs/capabilities.md`](docs/capabilities.md).

## Hosted multi-tenant (optional)

Email sign-in, pairing codes, and `deploy/` (VPS + Caddy) are in the repo for later. They are **not** required for your own Cursor / Claude / ChatGPT. Enable only with `WPAGENT_HTTP_MODE=hosted` if other people will connect to *your* MCP server.

## Repository layout

| Path | What it is |
| --- | --- |
| `plugin/` | WordPress plugin (GPL-2.0-or-later) |
| `server/` | MCP server, stdio + Streamable HTTP (MIT) |
| `deploy/` | Optional VPS + Caddy stack |
| `scripts/` | Plugin zip, ChatGPT HTTP/tunnel, wp-env E2E |
| `docs/` | ChatGPT, capabilities, E2E notes |
| `.wp-env.json` | Local WordPress for live E2E (needs Docker) |

## Tests

```bash
cd plugin && composer install && composer test
cd server && npm install && npm test && npm run build
```

Live WordPress loop (Docker):

```bash
./scripts/e2e-wp-env.sh
```

See [`docs/e2e-testing.md`](docs/e2e-testing.md).

## Version

| Field | Value |
| --- | --- |
| Version | **0.12.0** (first public release) |
| Released | 8 September 2026 |
| Requires | WordPress 6.0+, PHP 8.1+, Node.js 20+ |
| Tag | [`v0.12.0`](https://github.com/KamranWaahid/wpagent/releases/tag/v0.12.0) |

Full history: [CHANGELOG.md](CHANGELOG.md).

## License

- MCP server (`server/`): [MIT](LICENSE)
- WordPress plugin (`plugin/`): [GPL-2.0-or-later](plugin/LICENSE)

## Security

Do not open public issues that include live Application Passwords, site URLs with credentials, or `.env` contents. See [SECURITY.md](SECURITY.md).
