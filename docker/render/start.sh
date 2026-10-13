#!/bin/sh
# Container entrypoint on Render: prepare storage (the persistent disk is
# mounted at storage/app), cache config, migrate, import the bundled
# templates, then run nginx + php-fpm + queue worker + scheduler.
set -e

cd /var/www/html

export PORT="${PORT:-10000}"
# Render sets RENDER_EXTERNAL_URL; set APP_URL yourself for a custom domain.
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"

mkdir -p storage/app/public storage/app/private \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache
find storage bootstrap/cache ! -user www-data -exec chown www-data:www-data {} +

envsubst '${PORT}' < /etc/nginx/app.conf.template > /etc/nginx/sites-enabled/default

artisan() {
    runuser -u www-data -- php artisan "$@"
}

artisan storage:link --force
artisan optimize
artisan migrate --force
artisan db:seed --class='Database\Seeders\TemplateSeeder' --force

exec supervisord -c /etc/supervisor/supervisord.conf
