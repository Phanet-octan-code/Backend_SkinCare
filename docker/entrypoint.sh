#!/bin/sh
set -e

# Substitute dynamic PORT from Render into nginx config
TARGET_PORT="${PORT:-80}"
echo "[Render Startup] Configuring Nginx to listen on port ${TARGET_PORT}..."
sed -i "s/listen 80;/listen ${TARGET_PORT};/g" /etc/nginx/http.d/default.conf

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

echo "[Render Startup] Starting PHP-FPM and Nginx via Supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

