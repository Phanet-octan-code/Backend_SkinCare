#!/bin/sh
set -e

# 1. Substitute dynamic PORT from Render into nginx config
TARGET_PORT="${PORT:-80}"
echo "[Render Startup] Configuring Nginx to listen on port ${TARGET_PORT}..."
sed -i "s/listen 80;/listen ${TARGET_PORT};/g" /etc/nginx/http.d/default.conf

# 2. Provide reliable production environment defaults
ACTIVE_APP_NAME="${APP_NAME:-Élixir Botanique}"
ACTIVE_APP_ENV="${APP_ENV:-production}"
ACTIVE_APP_KEY="${APP_KEY:-base64:2q3DHGrkMArI5CTCycloL6Ir2zRkUZ8OXa4PQEqb+K8=}"
ACTIVE_APP_DEBUG="${APP_DEBUG:-false}"
ACTIVE_APP_URL="${APP_URL:-http://localhost}"

ACTIVE_DB_CONNECTION="${DB_CONNECTION:-pgsql}"
ACTIVE_DB_HOST="${DB_HOST:-aws-0-ap-southeast-1.pooler.supabase.com}"
ACTIVE_DB_PORT="${DB_PORT:-6543}"
ACTIVE_DB_DATABASE="${DB_DATABASE:-postgres}"
ACTIVE_DB_USERNAME="${DB_USERNAME:-postgres.rsaxtgzmyzinvyuimthi}"
ACTIVE_DB_PASSWORD="${DB_PASSWORD:-Skincare2026!}"
ACTIVE_DB_SSLMODE="${DB_SSLMODE:-require}"

ACTIVE_SESSION_DRIVER="${SESSION_DRIVER:-file}"
ACTIVE_CACHE_STORE="${CACHE_STORE:-file}"
ACTIVE_QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

# 3. Write active configuration to /var/www/html/.env so PHP-FPM and Artisan have full access
echo "[Render Startup] Writing /var/www/html/.env configuration..."
cat <<EOF > /var/www/html/.env
APP_NAME="${ACTIVE_APP_NAME}"
APP_ENV="${ACTIVE_APP_ENV}"
APP_KEY="${ACTIVE_APP_KEY}"
APP_DEBUG=${ACTIVE_APP_DEBUG}
APP_URL="${ACTIVE_APP_URL}"

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stderr
LOG_LEVEL=debug

DB_CONNECTION="${ACTIVE_DB_CONNECTION}"
DB_HOST="${ACTIVE_DB_HOST}"
DB_PORT="${ACTIVE_DB_PORT}"
DB_DATABASE="${ACTIVE_DB_DATABASE}"
DB_USERNAME="${ACTIVE_DB_USERNAME}"
DB_PASSWORD="${ACTIVE_DB_PASSWORD}"
DB_SSLMODE="${ACTIVE_DB_SSLMODE}"

SESSION_DRIVER="${ACTIVE_SESSION_DRIVER}"
SESSION_LIFETIME=120

CACHE_STORE="${ACTIVE_CACHE_STORE}"
QUEUE_CONNECTION="${ACTIVE_QUEUE_CONNECTION}"
EOF

# 4. Ensure storage directories exist with proper write permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/.env
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
chmod 644 /var/www/html/.env

# 5. Create public storage symlink if not already created
if [ ! -L /var/www/html/public/storage ]; then
    echo "[Render Startup] Creating storage symlink..."
    php artisan storage:link || true
fi

# 6. Run database migrations safely
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[Render Startup] Running database migrations..."
    php artisan migrate --force || true
fi

# 7. Optimize and cache configuration for fast production response
echo "[Render Startup] Optimizing caches..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "[Render Startup] Starting PHP-FPM and Nginx via Supervisord on port ${TARGET_PORT}..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
