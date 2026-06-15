#!/bin/bash
# Post-deploy script — run from project root after git pull
set -e

echo "[deploy] Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "[deploy] Setting storage permissions..."
chmod -R 775 storage/
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;

echo "[deploy] Creating storage subdirectories..."
mkdir -p storage/logs storage/cache/mpdf storage/sessions
mkdir -p storage/urban-tickets storage/tickets storage/pdf
mkdir -p storage/media storage/qr storage/temp storage/voyages

echo "[deploy] Clearing PHP cache..."
find . -name "*.php~" -delete 2>/dev/null || true

echo "[deploy] Done. Don't forget to:"
echo "  1. Copy .env.example → .env and fill in DB credentials"
echo "  2. Run the migration SQL: database/migrations/*.sql"
echo "  3. Configure APP_KEY (php -r \"echo 'base64:'.base64_encode(random_bytes(32));\")"
