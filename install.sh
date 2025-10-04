#!/bin/bash
set -e

echo "🚀 Instalando/Atualizando Gateway API..."

echo "📦 Rebuilding Docker image..."
docker-compose build --no-cache

echo "🛑 Parando containers..."
docker-compose down

echo "▶️ Iniciando containers..."
docker-compose up -d

echo "⏳ Aguardando PHP-FPM..."
sleep 5

echo "📚 Instalando dependências..."
docker exec -u apiuser app composer install --no-interaction --optimize-autoloader

echo "🗄️ Rodando migrations..."
docker exec -u apiuser app php artisan migrate --force

echo "⚡ Otimizando..."
docker exec -u apiuser app php artisan config:cache
docker exec -u apiuser app php artisan route:cache
docker exec -u apiuser app php artisan view:cache

echo "🔐 Ajustando permissões..."
docker exec app chown -R apiuser:apiuser /var/www/html/storage
docker exec app chown -R apiuser:apiuser /var/www/html/bootstrap/cache

echo "✅ Instalação concluída!"
echo "📊 Status dos containers:"
docker-compose ps