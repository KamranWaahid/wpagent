# ChatGPT: personal HTTPS tunnel (no hosting)

Cursor and Claude Desktop spawn `server/dist/index.js` over **stdio**. ChatGPT cannot. It only talks to a remote MCP URL (Streamable HTTP).

You do **not** need the VPS / Caddy / Resend / email-code / pairing stack for that. Run the same credentials as stdio, listen on localhost, and give ChatGPT an HTTPS path via a tunnel.

A Cloudflare/ngrok URL is **internet-reachable** while the tunnel is up. Personal HTTP therefore **requires** `WPAGENT_HTTP_TOKEN`. URL obscurity is not authentication.

## 1. Start personal HTTP

Same Application Password you already paste into Cursor, plus a Bearer token:

```bash
cd server
export WPAGENT_URL="https://your-wordpress.example"
export WPAGENT_USERNAME="admin"
export WPAGENT_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
export WPAGENT_HTTP_TOKEN="a-long-random-string"   # 16+ chars; put this in .env
npm run build
npm run start:http
```

Or from the repo root (`./scripts/chatgpt-personal.sh` generates a token if unset and prints it):

```bash
./scripts/chatgpt-personal.sh
```

This binds `127.0.0.1:3333` by default. Mode is **personal**: `/mcp` uses those env sites and **rejects requests without** `Authorization: Bearer <WPAGENT_HTTP_TOKEN>`. No email, no sessions. Hosted routes (`/auth/*`, `/connect`) return 404. `GET /healthz` reports `"mode":"personal"` (no token; used as a liveness check).

## 2. Give ChatGPT HTTPS

Pick one. Leave the HTTP server running.

### Cloudflare Tunnel (quick public URL)

```bash
./scripts/chatgpt-tunnel.sh
# or: npx --yes cloudflared tunnel --url http://127.0.0.1:3333
```

Copy the `https://….trycloudflare.com` URL and add **`/mcp`** in ChatGPT.

The tunnel forwards the **whole localhost port** (`/healthz`, 404s on `/auth/*`, and `/mcp`). Only `/mcp` runs tools. Kill the tunnel when you are done.

### ngrok

```bash
ngrok http 3333
```

Paste `https://….ngrok-free.app/mcp`. Same Bearer requirement.

### OpenAI Secure MCP Tunnel (no public URL)

OpenAI’s [tunnel-client](https://github.com/openai/tunnel-client) opens an **outbound** path to ChatGPT so the MCP server never has to be on the public internet.

1. Create a tunnel in the OpenAI Platform tunnel settings.
2. Run `tunnel-client` with `MCP_SERVER_URL=http://127.0.0.1:3333/mcp` (see `tunnel-client help quickstart`).
3. In ChatGPT Developer mode, create an app with **Connection: Tunnel**.
4. The connector still needs the Bearer token if the client can send headers.

Docs: [Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels) and [Developer mode](https://developers.openai.com/api/docs/guides/developer-mode).

## 3. Add the connector in ChatGPT

1. Settings → Security and login → **Developer mode** (plan-dependent).
2. Create a developer-mode app / connector.
3. **Connection:** URL (`https://your-tunnel/mcp`).
4. **Authentication:** send `Authorization: Bearer <WPAGENT_HTTP_TOKEN>` (custom header / API key — not “No authentication”).
5. In a chat, enable Developer mode and select the app.

If a given ChatGPT build cannot attach a Bearer header, do **not** set `WPAGENT_HTTP_ALLOW_ANON_ENV=1` on a public tunnel. Use OpenAI’s Secure MCP Tunnel (no public URL) or Cursor/Claude stdio instead.

## What this is not

`deploy/` (VPS + Caddy + Resend + pairing) is **shelved**. Turn it on later with `WPAGENT_HTTP_MODE=hosted` only if other people will sign in to *your* MCP server. `WPAGENT_HTTP_ALLOW_ANON_ENV=1` is a localhost test escape hatch, not a production setting.
