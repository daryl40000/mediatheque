#!/usr/bin/env bash
# Serveur de développement avec limites adaptées aux PDF magazines (350 Mo).
cd "$(dirname "$0")/.." || exit 1
HOST="${1:-localhost:8080}"
SESSION_DIR="$(pwd)/data/sessions"
mkdir -p "$SESSION_DIR"
echo "Médiathèque — serveur de développement sur http://${HOST}/"
echo "Limites PDF : upload 350 Mo, post 360 Mo (fichier www/php-dev.ini)"
echo "Sessions PHP : ${SESSION_DIR}"
echo "Arrêt : Ctrl+C"
exec php -c www/php-dev.ini \
  -d session.save_path="${SESSION_DIR}" \
  -S "${HOST}" -t www www/router.php
