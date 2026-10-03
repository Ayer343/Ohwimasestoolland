#!/bin/bash
set -e

echo "==> Clearing config cache..."
php artisan config:clear

echo "==> Rebuilding config cache..."
php artisan config:cache

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Done."