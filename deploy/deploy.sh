#!/usr/bin/env bash
# Rebuild and restart the production stack on this machine.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
	echo "Missing $ROOT/.env — copy .env.example and fill secrets first." >&2
	exit 1
fi

docker compose -f deploy/docker-compose.prod.yml --env-file .env up -d --build
docker compose -f deploy/docker-compose.prod.yml --env-file .env ps
