#!/usr/bin/env bash
# Met à jour l'application sur le serveur après un « git push » :
#   cd /var/www/gestion-ecole && bash deploy/mise-a-jour.sh
# Étapes : sauvegarde de la base, récupération du code, dépendances, migrations, caches, interface.
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

echo "==> 1/6 Sauvegarde de la base avant mise à jour"
php backend/artisan ecole:sauvegarde

echo "==> 2/6 Récupération du code"
git pull --ff-only

echo "==> 3/6 Dépendances PHP"
cd "$APP_DIR/backend"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> 4/6 Base de données (ajoute uniquement ce qui manque, n'efface rien)"
php artisan migrate --force

echo "==> 5/6 Caches Laravel"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> 6/6 Interface React"
cd "$APP_DIR/frontend"
npm ci --no-audit --no-fund
npm run build

sudo systemctl reload php8.3-fpm
echo "Mise à jour terminée."
