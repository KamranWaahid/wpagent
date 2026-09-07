#!/usr/bin/env bash
# HTTPS front for the personal MCP server (must already be listening on 127.0.0.1:3333).
set -euo pipefail

PORT="${WPAGENT_HTTP_PORT:-3333}"
TARGET="http://127.0.0.1:${PORT}"

echo "Tunneling ${TARGET} → HTTPS. Paste the printed https URL + /mcp into ChatGPT."
echo "Treat the URL as a secret. Ctrl+C when finished."
echo

if command -v cloudflared >/dev/null 2>&1; then
	exec cloudflared tunnel --url "${TARGET}"
fi

if command -v npx >/dev/null 2>&1; then
	exec npx --yes cloudflared tunnel --url "${TARGET}"
fi

echo "Install cloudflared, or run: ngrok http ${PORT}" >&2
echo "Or use OpenAI's tunnel-client: https://github.com/openai/tunnel-client" >&2
exit 1
