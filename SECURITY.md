# Security

## Credential model

- **WordPress Application Passwords** authenticate the plugin REST API. They live in WordPress core’s application-password tables, not in plugin options.
- **Local stdio:** the MCP client (Claude Desktop / Cursor) holds the Application Password in its config / env. WPAgent does not receive a copy except in memory for the process lifetime.
- **Personal HTTP (ChatGPT tunnel):** same env credentials as stdio, bound to `127.0.0.1`. A public Cloudflare/ngrok URL is reachable by anyone who has it. Always set `WPAGENT_HTTP_TOKEN`. Prefer OpenAI’s Secure MCP Tunnel (outbound only) or stop the tunnel when you are done.
- **Hosted HTTP (optional):** the user verifies email, receives a session token, then links a site. Application Passwords are stored **per session**, encrypted with AES-256-GCM (`WPAGENT_SESSION_SECRET`). Sessions never share site lists. Tokens are stored as SHA-256 hashes; the raw bearer token is shown once at verify time.
- **Pairing codes** are short-lived and single-use. They exist so wp-admin can push credentials to the correct session without putting the password in the AI chat.

## Audit log

Every plugin write appends a row to `{prefix}wpagent_audit_log` (user, command, object, before/after JSON, result). Rows are not updated. Admins can view them under WPAgent → Audit log, or via `get_audit_log` (capability `manage_options`).

## Scaling later

The hosted session store is a **single-machine** file (`WPAGENT_SESSION_STORE=file` on a Docker volume). Do not run multiple MCP replicas against that file. Before horizontal scale, swap the session store for Redis (or similar) so all nodes share the same encrypted session records.

## Reporting a vulnerability

Please use [GitHub Security Advisories](https://github.com/KamranWaahid/wpagent/security/advisories/new) for this repository.

Do **not** open a public issue with exploit details, live Application Passwords, or a real site URL plus credentials. Include WPAgent version, WordPress version, and a reproduction against a disposable site.
