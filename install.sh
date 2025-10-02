#!/usr/bin/env bash
set -euo pipefail
docker compose build
docker compose up -d db
# aguarda o postgres subir
for i in {1..20}; do
  docker exec db pg_isready -U gateway_api -d gateway_api && break || sleep 1
done
docker compose up -d app proxy
docker exec app composer install --no-interaction || true
docker exec app php artisan key:generate
# cria tabela de jobs antes das migrações gerais (se precisar)
docker exec app php artisan queue:table || true
docker exec app php artisan migrate --force || true
echo "OK em http://localhost:${APP_PORT:-8080}/api/health"
