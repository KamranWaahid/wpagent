# Live WordPress E2E (wp-env)

This is the repeatable check for the real plugin loop: connect with an Application Password, create a post (must stay draft), confirm the audit row, then dry-run search-replace and inspect the diff preview.

It uses the repo-root `.wp-env.json` (PHP 8.2, plugin mounted from `./plugin`). **Docker is required.**

## Run

```bash
./scripts/e2e-wp-env.sh
```

The script:

1. Fails immediately if Docker is missing or the daemon is down (it does not skip).
2. Starts `npx @wordpress/env start`.
3. Activates WPAgent and turns on **Allow Application Passwords over HTTP** (wp-env is `http://localhost:8888` by default).
4. Creates an Application Password for `admin` — that is the local equivalent of authorizing from wp-admin.
5. Calls the same REST routes the MCP tools use:
   - `POST /wp-json/wpagent/v1/posts` with `status=publish` and **no** `explicit_publish` — asserts `status` is `draft`.
   - `GET /wp-json/wpagent/v1/audit-log?command=create_post` — asserts a row exists.
   - `POST /wp-json/wpagent/v1/search-replace` without `confirm` — asserts `dry_run: true`, at least one match, and a unified `-` / `+` snippet.

Stop the environment when you are done:

```bash
npx @wordpress/env stop
```

## What this does not cover

- Hosted email-code sign-in and pairing (`POST /auth/start` → wp-admin **Authorize AI connection**) are covered by `server` Vitest (`tests/auth-flow.test.ts`).
- `update_core` / plugin updates are not applied against wp-env (that would mutate core). Health snapshots and the standalone `update_core` tool are unit-tested plus exercised only when you point at a disposable site.

## If Docker is not available

Install Docker Desktop (macOS/Windows) or Docker Engine (Linux), then re-run the script. Do not treat a mocked PHPUnit/Vitest run as a substitute for this loop.
