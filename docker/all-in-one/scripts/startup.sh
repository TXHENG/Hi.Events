#!/bin/sh

cd /app/backend

if ! php artisan migrate --force; then
    echo "============================================"
    echo "ERROR: Migrations could not complete. Check the error above."
    echo "Ensure DATABASE_URL is set."
    echo "Aborting startup to avoid running a half-migrated application."
    echo "============================================"
    exit 1
fi

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan storage:link

# Only Laravel's runtime directories need to be writable by the web and queue
# processes. Avoid recursively walking the application, dependencies, and any
# persistent upload volume on every container start.
chown -R www-data:www-data /app/backend/storage /app/backend/bootstrap/cache
chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisord.conf
