#!/usr/bin/env bash
# Repeatable live loop against the repo's .wp-env.json:
# connect (Application Password) → create_post (must stay draft) → audit log → search-replace dry-run.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1; then
	echo "Docker is not installed or not on PATH." >&2
	echo "Install Docker Desktop (or Engine) and re-run: ./scripts/e2e-wp-env.sh" >&2
	echo "I will not skip this check — the live WordPress loop needs wp-env." >&2
	exit 1
fi

if ! docker info >/dev/null 2>&1; then
	echo "Docker is installed but the daemon is not reachable. Start Docker and retry." >&2
	exit 1
fi

if ! command -v npx >/dev/null 2>&1; then
	echo "npx is required (Node.js)." >&2
	exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
	echo "python3 is required to assert JSON responses." >&2
	exit 1
fi

WP_ENV=(npx --yes @wordpress/env)

last_line() {
	# wp-env prints container chatter; the command result is the last non-empty line.
	awk 'NF { line=$0 } END { print line }' | tr -d '\r'
}

echo "==> Starting wp-env (Docker)…"
"${WP_ENV[@]}" start

SITE_URL="$("${WP_ENV[@]}" run cli wp option get siteurl | last_line)"
if [[ -z "${SITE_URL}" || "${SITE_URL}" != http* ]]; then
	echo "Could not read siteurl from wp-env (got: ${SITE_URL:-empty})." >&2
	exit 1
fi

echo "==> Site: ${SITE_URL}"
echo "==> Activating WPAgent and enabling Application Passwords over HTTP…"
"${WP_ENV[@]}" run cli wp plugin activate wpagent
"${WP_ENV[@]}" run cli wp option update wpagent_allow_insecure_app_passwords 1

APP_PASSWORD="$("${WP_ENV[@]}" run cli wp user application-password create admin "WPAgent E2E" --porcelain | last_line)"
if [[ -z "${APP_PASSWORD}" ]]; then
	echo "Failed to create an Application Password." >&2
	exit 1
fi

AUTH_HEADER="Authorization: Basic $(printf '%s' "admin:${APP_PASSWORD}" | base64 | tr -d '\n')"
API="${SITE_URL%/}/wp-json/wpagent/v1"
TOKEN="e2e-$(date +%s)"

api() {
	local method="$1"
	local path="$2"
	local data="${3:-}"
	if [[ -n "${data}" ]]; then
		curl -fsS -X "${method}" \
			-H "${AUTH_HEADER}" \
			-H "Content-Type: application/json" \
			-d "${data}" \
			"${API}${path}"
	else
		curl -fsS -X "${method}" \
			-H "${AUTH_HEADER}" \
			"${API}${path}"
	fi
}

echo "==> create_post (status=publish without explicit_publish — must land as draft)…"
CREATE="$(api POST /posts "{\"title\":\"WPAgent E2E ${TOKEN}\",\"content\":\"Hello ${TOKEN} world\",\"status\":\"publish\"}")"
echo "${CREATE}"
STATUS="$(printf '%s' "${CREATE}" | python3 -c "import json,sys; print(json.load(sys.stdin).get('status',''))")"
if [[ "${STATUS}" != "draft" ]]; then
	echo "Expected draft, got status=${STATUS}" >&2
	exit 1
fi

echo "==> audit log should contain create_post…"
AUDIT="$(api GET '/audit-log?command=create_post&per_page=5')"
echo "${AUDIT}"
printf '%s' "${AUDIT}" | python3 -c "
import json, sys
data = json.load(sys.stdin)
items = data.get('items') or []
if not items:
    raise SystemExit('audit log had no create_post rows')
print('audit rows:', len(items))
"

echo "==> run_search_replace dry-run (no confirm)…"
SR="$(api POST /search-replace "{\"search\":\"${TOKEN}\",\"replace\":\"REPLACED\"}")"
echo "${SR}"
printf '%s' "${SR}" | python3 -c "
import json, sys
data = json.load(sys.stdin)
if data.get('dry_run') is not True:
    raise SystemExit('search-replace was not a dry-run')
if int(data.get('matches') or 0) < 1:
    raise SystemExit('expected at least one search-replace match')
preview = data.get('preview') or []
if not preview:
    raise SystemExit('expected a diff preview')
diff = preview[0].get('diff') or {}
field = next(iter(diff.values()), None)
if not field:
    raise SystemExit('preview missing field diffs')
snippet = field[0] if isinstance(field, list) else field
unified = snippet.get('unified', '')
if '- ' not in unified or '+ ' not in unified:
    raise SystemExit('diff preview is not a unified snippet: %r' % snippet)
print('dry-run diffs look sane')
"

echo
echo "E2E passed against ${SITE_URL}"
echo "Stop later with: npx @wordpress/env stop"
