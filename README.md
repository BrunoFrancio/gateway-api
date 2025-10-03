## Instalação rápida (Docker)

Pré-requisitos: Docker Desktop com WSL integrado; `.env` preenchido; `env.db` em `/docker/conf/gateway_api/env.db`.

```bash
chmod +x install.sh
./install.sh


O script faz:

Build do app (php-fpm) e sobe db, app, proxy.

composer install dentro do container.

Gera APP_KEY se estiver vazio.

Roda migrações (php artisan migrate --force).

Ajusta permissões de storage/ e bootstrap/cache/.

Cacheia config; tenta cachear rotas (se falhar por Closure, mantém sem cache).

Testa GET /api/health (esperado 200).