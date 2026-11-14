#!/usr/bin/env bash
set -euo pipefail
: "${APP_KEY:?Set a stable application key}"
: "${APP_URL:?Set the public HTTPS URL}"
: "${DB_HOST:?Set TiDB host}"
: "${DB_DATABASE:?Set TiDB database}"
: "${DB_USERNAME:?Set TiDB username}"
: "${DB_PASSWORD:?Set TiDB password}"
: "${CHURN_API_KEY:?Set the inference API key}"
: "${CHURN_API_URL:?Set the inference HTTPS URL}"
[[ "$APP_URL" == https://* && "$CHURN_API_URL" == https://* ]]
port="${PORT:-10000}"
[[ "$port" =~ ^[0-9]+$ ]] && ((port >= 1024 && port <= 65535))
sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/\*:10000/*:${port}/" /etc/apache2/sites-available/000-default.conf
mkdir -p /run/krayin storage/framework/{cache/data,sessions,views} storage/logs
umask 077
printf '%s' "$CHURN_API_KEY" > /run/krayin/api-key
chown www-data:www-data /run/krayin/api-key
export CHURN_CLIENT_KEY_FILE=/run/krayin/api-key
export CHURN_CLIENT_CA_FILE=/etc/ssl/certs/ca-certificates.crt
export MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
php artisan package:discover --no-interaction
php artisan config:cache
touch storage/installed
chown -R www-data:www-data storage bootstrap/cache
apache2ctl configtest
exec /usr/bin/supervisord -c /var/www/html/deploy/render/supervisord.conf
