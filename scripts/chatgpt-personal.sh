#!/usr/bin/env bash
# Start personal Streamable HTTP for ChatGPT (localhost). Pair with scripts/chatgpt-tunnel.sh.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/server"

if [[ -z "${WPAGENT_URL:-}" || -z "${WPAGENT_USERNAME:-}" || -z "${WPAGENT_APP_PASSWORD:-}" ]]; then
	if [[ -f "$ROOT/.env" ]]; then
		# shellcheck disable=SC1091
		set -a
		# shellcheck disable=SC1091
		source "$ROOT/.env"
		set +a
	fi
fi

if [[ -z "${WPAGENT_URL:-}" || -z "${WPAGENT_USERNAME:-}" || -z "${WPAGENT_APP_PASSWORD:-}" ]]; then
	echo "Set WPAGENT_URL, WPAGENT_USERNAME, and WPAGENT_APP_PASSWORD (same as Cursor stdio)." >&2
	exit 1
fi

export WPAGENT_HTTP=1
export WPAGENT_HTTP_MODE="${WPAGENT_HTTP_MODE:-personal}"
export WPAGENT_HTTP_HOST="${WPAGENT_HTTP_HOST:-127.0.0.1}"
export WPAGENT_HTTP_PORT="${WPAGENT_HTTP_PORT:-3333}"

if [[ -z "${WPAGENT_HTTP_TOKEN:-}" ]]; then
	if command -v openssl >/dev/null 2>&1; then
		WPAGENT_HTTP_TOKEN="$(openssl rand -hex 24)"
	else
		WPAGENT_HTTP_TOKEN="$(node -e "console.log(require('crypto').randomBytes(24).toString('hex'))")"
	fi
	export WPAGENT_HTTP_TOKEN
	echo "Generated WPAGENT_HTTP_TOKEN (save in .env to reuse):"
	echo "  ${WPAGENT_HTTP_TOKEN}"
fi

if [[ ! -f dist/index.js ]]; then
	npm run build
fi

echo "Personal MCP on http://${WPAGENT_HTTP_HOST}:${WPAGENT_HTTP_PORT}/mcp"
echo "ChatGPT must send: Authorization: Bearer ${WPAGENT_HTTP_TOKEN}"
echo "Then tunnel it: ./scripts/chatgpt-tunnel.sh"
exec node dist/index.js --http
