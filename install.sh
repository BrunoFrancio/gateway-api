#!/usr/bin/env bash
set -Eeuo pipefail

info(){ echo -e "\033[1;34m[INFO]\033[0m $*"; }
warn(){ echo -e "\033[1;33m[WARN]\033[0m $*"; }
err(){  echo -e "\033[1;31m[ERRO]\033[0m $*"; }

# 1) Build e subir serviços
info "Build do app (php-fpm)…"
docker compose build app

info "Subindo banco…"
docker compose up -d db

info "Aguardando Postgres ficar pronto…"
for i in {1..40}; do
  if docker compose exec -T db pg_isready -h localhost -p 5432 >/dev/null 2>&1; then
    info "Postgres OK"; break
  fi
  sleep 1
  [ "$i" -eq 40 ] && warn "pg_isready não confirmou; seguindo mesmo assim."
done

info "Subindo app e proxy…"
docker compose up -d app proxy

# 2) Composer (no container)
info "Instalando dependências (composer)…"
docker compose exec -T app composer install --no-interaction --prefer-dist

# 3) APP_KEY (gera só se estiver vazio)
if docker compose exec -T app sh -lc "grep -qE '^APP_KEY=\s*$' .env"; then
  info "Gerando APP_KEY…"
  docker compose exec -T app php artisan key:generate
else
  info "APP_KEY já definido — ok."
fi

# 4) Migrações
info "Executando migrações…"
docker compose exec -T app php artisan migrate --force

# 5) Permissões para FPM
info "Ajustando permissões (storage/ e bootstrap/cache)…"
docker compose exec -T app sh -lc '
  mkdir -p storage/framework/{cache,views,sessions} bootstrap/cache &&
  chown -R www-data:www-data storage bootstrap/cache &&
  find storage -type d -exec chmod 775 {} \; &&
  find storage -type f -exec chmod 664 {} \; &&
  chmod -R 775 bootstrap/cache
'

info "Limpando caches…"
docker compose exec -T app php artisan optimize:clear

info "Gerando cache de config…"
docker compose exec -T app php artisan config:cache

info "Tentando cachear rotas (pode falhar se usar Closure)…"
if ! docker compose exec -T app php artisan route:cache; then
  warn "route:cache falhou (provável uso de Closure). Mantendo sem cache."
  docker compose exec -T app php artisan route:clear
fi

APP_PORT_LOCAL=$(grep -E '^APP_PORT=' .env | cut -d= -f2- | tr -d '"')
APP_PORT_LOCAL=${APP_PORT_LOCAL:-8080}

info "Verificando /api/health em http://localhost:${APP_PORT_LOCAL}/api/health …"
if curl -fsS "http://localhost:${APP_PORT_LOCAL}/api/health" >/dev/null; then
  info "Tudo certo! 🚀"
else
  warn "Health falhou. Veja logs:  docker compose logs --tail=200 proxy app db"
fi
