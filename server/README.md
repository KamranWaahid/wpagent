# WPAgent MCP server

TypeScript MCP server that turns tool calls into authenticated requests against the WPAgent WordPress plugin (`/wp-json/wpagent/v1/`).

## Requirements

- Node.js 20+
- A WordPress site with the WPAgent plugin active

## Install

```bash
cd server
npm install
npm run build
```

## Local development (stdio)

Claude Desktop and Cursor:

```bash
export WPAGENT_URL="https://example.com"
export WPAGENT_USERNAME="admin"
export WPAGENT_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
node dist/index.js
```

See the root README for client JSON snippets. The same `WPAGENT_*` env vars are used for **personal HTTP** (ChatGPT tunnel).

## Personal HTTP (ChatGPT)

Default for `--http`. No email, no sessions. Bind localhost and tunnel:

```bash
export WPAGENT_HTTP=1
export WPAGENT_URL="https://example.com"
export WPAGENT_USERNAME="admin"
export WPAGENT_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
node dist/index.js --http
```

Then `docs/chatgpt.md`. `GET /healthz` reports `mode: personal`. `/mcp` needs no Bearer unless you set `WPAGENT_HTTP_TOKEN`.

## Hosted Streamable HTTP (shelved)

Only if other people sign in to your server: `WPAGENT_HTTP_MODE=hosted`, plus session secret and Resend. See `deploy/README.md`.

## Tools

Same allowlisted set as the plugin. Destructive tools need `confirm: true`. Creates stay draft unless `explicit_publish: true`.

Errors are JSON with `code` of `auth` | `capability` | `validation` | `network` | `not_found` | `safety` | `error` | `rate_limited`.

## Tests

```bash
npm test
```
