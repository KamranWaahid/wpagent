# Deploy WPAgent on a VPS + Caddy (shelved)

This stack is for **other people** signing into your MCP server. For your own ChatGPT, use `docs/chatgpt.md` (localhost + tunnel) instead.

Single-machine deployment. The MCP session file store lives on a Docker volume so sessions survive `docker compose up --build`. This is **not** multi-host: if you add a second machine, replace the file store with Redis first (see root `SECURITY.md`).

## What you need on the box

- Ubuntu 22.04/24.04 or Debian 12 (2 GB RAM is enough)
- A domain `A`/`AAAA` record pointing at the VPS public IP
- Ports **80** and **443** open (Let's Encrypt HTTP-01)
- Docker Engine + Compose plugin
- A repo checkout (git clone or `scp -r`)

## First-time setup

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl git
# install Docker: https://docs.docker.com/engine/install/ubuntu/
sudo usermod -aG docker "$USER"   # then log out/in
```

Clone the repo, then:

```bash
cd wpagent
cp .env.example .env
chmod 600 .env
# edit .env: WPAGENT_DOMAIN, WPAGENT_PUBLIC_URL, WPAGENT_SESSION_SECRET,
# RESEND_API_KEY, WPAGENT_EMAIL_FROM
```

`.env` is gitignored. Never commit it or paste secrets into issues.

```bash
chmod +x deploy/deploy.sh
./deploy/deploy.sh
# equivalent: git pull && docker compose -f deploy/docker-compose.prod.yml --env-file .env up -d --build
```

Caddy issues a Let's Encrypt cert for `WPAGENT_DOMAIN`. Check:

```bash
curl -fsS https://YOUR_DOMAIN/healthz
```

## Updates

```bash
git pull
./deploy/deploy.sh
```

The `wpagent-sessions` volume is reused, so existing sign-in sessions remain.

## Email

Production defaults to **Resend**. Set `RESEND_API_KEY` and a verified `WPAGENT_EMAIL_FROM`. Local/dev without a key falls back to the console adapter.

Required `.env` values (never commit them):

- `WPAGENT_DOMAIN` / `WPAGENT_PUBLIC_URL` (e.g. `mcp.example.com`)
- `WPAGENT_SESSION_SECRET` (long random string)
- `RESEND_API_KEY`
- `WPAGENT_EMAIL_FROM`
