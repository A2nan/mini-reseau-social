#!/bin/sh
set -e

echo "[entrypoint] Attente de la base de données et mise à jour du schéma..."

n=0
until php bin/console doctrine:schema:update --force --complete --no-interaction 2>/dev/null; do
    n=$((n + 1))
    if [ "$n" -ge 30 ]; then
        echo "[entrypoint] La base n'est toujours pas joignable après $n tentatives, on continue quand même."
        break
    fi
    echo "[entrypoint] Base indisponible, nouvelle tentative dans 2s ($n/30)..."
    sleep 2
done

echo "[entrypoint] Nettoyage du cache..."
php bin/console cache:clear --no-interaction || true
chown -R www-data:www-data var || true

echo "[entrypoint] Démarrage : $*"
exec "$@"
