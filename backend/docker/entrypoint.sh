#!/bin/sh
set -e

# Le code est monté depuis l'hôte : on installe les dépendances au premier lancement.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Crée / met à jour la base SQLite (var/quiz_dev.db). Les fixtures ne sont pas chargées
# automatiquement car elles VIDENT la base : voir docs/fixtures.md.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
