#!/bin/bash
set -e

echo "==> Dumping optimized autoloader..."
composer dump-autoload --optimize --no-dev

echo "==> Clearing all caches..."
php artisan optimize:clear

echo "==> Rebuilding config cache..."
php artisan config:cache

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Creating storage symlink..."
php artisan storage:link || true

echo "==> Deploy complete."