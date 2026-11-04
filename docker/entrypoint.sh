#!/bin/sh
set -e

# Substitute dynamic PORT from Render into nginx config
TARGET_PORT="${PORT:-80}"
echo "[Render Startup] Configuring Nginx to listen on port ${TARGET_PORT}..."
sed -i "s/listen 80;/listen ${TARGET_PORT};/g" /etc/nginx/http.d/default.conf

# Provide reliable production environment defaults if not set in Render UI
export APP_NAME="${APP_NAME:-Élixir Botanique}"
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export APP_KEY="${APP_KEY:-base64:2q3DHGrkMArI5CTCycloL6Ir2zRkUZ8OXa4PQEqb+K8=}"
export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_HOST="${DB_HOST:-aws-0-ap-southeast-1.pooler.supabase.com}"
export DB_PORT="${DB_PORT:-6543}"
export DB_DATABASE="${DB_DATABASE:-postgres}"
export DB_USERNAME="${DB_USERNAME:-postgres.rsaxtgzmyzinvyuimthi}"
export DB_PASSWORD="${DB_PASSWORD:-Skincare2026!}"
export DB_SSLMODE="${DB_SSLMODE:-require}"
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

# Ensure storage directories exist with proper write permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create public storage symlink if not already created
if [ ! -L /var/www/html/public/storage ]; then
    echo "[Render Startup] Creating storage symlink..."
    php artisan storage:link || true
fi

# Run database migrations safely
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[Render Startup] Running database migrations..."
    php artisan migrate --force || true
fi

# Optimize and cache configuration for fast production response
echo "[Render Startup] Optimizing caches..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "[Render Startup] Starting PHP-FPM and Nginx via Supervisord on port ${TARGET_PORT}..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
