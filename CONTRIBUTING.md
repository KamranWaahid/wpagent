# Contributing

Thanks for helping with WPAgent.

## Setup

```bash
cd plugin && composer install
cd ../server && npm install
```

## Checks

Run both suites before opening a pull request:

```bash
cd plugin && composer test
cd server && npm test && npm run build
```

Optional live WordPress loop (Docker): `./scripts/e2e-wp-env.sh`.

## Guidelines

- Keep new write tools **draft-first** and **confirm-gated** when they can destroy data.
- Enforce safety in PHP (`plugin/`), not only in MCP tool text.
- Add or update a unit test next to the change.
- Do not commit `.env`, Application Passwords, real site URLs, or other credentials.
- Use `example.com` and placeholder usernames in tests and docs.

## Pull requests

1. Fork [KamranWaahid/wpagent](https://github.com/KamranWaahid/wpagent).
2. Branch from `main`.
3. Open a PR with a short “why” and how you tested.

By contributing you agree that plugin changes are GPL-2.0-or-later and server changes are MIT, matching this repository.
