#!/bin/sh

# On s'assure que les caches sont vidés
php artisan config:clear
php artisan route:clear
php artisan view:clear

# On lance les migrations (obligatoire pour Neon en prod)
php artisan migrate --seed --force

# Optimisation des performances
php artisan l5-swagger:generate

php artisan config:cache
php artisan route:cache
php artisan view:cache

# On démarre Apache au premier plan
apache2-foreground