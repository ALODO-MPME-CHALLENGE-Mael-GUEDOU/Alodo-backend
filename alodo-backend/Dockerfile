# --- Fichier : Dockerfile ---
# Emplacement : Racine du projet (/Dockerfile)
# BASÉ SUR VOTRE TEMPLATE APACHE (ADAPTÉ POUR PHP 8.4 + POSTGRES)

# 1. On part de l'image Apache avec PHP 8.4 (Debian)
FROM php:8.4-apache

# 2. On installe les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. On installe les extensions PHP (Note : pdo_pgsql pour Neon)
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# 4. On active le module "rewrite" d'Apache
RUN a2enmod rewrite

# 5. On configure Apache pour pointer sur /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. On installe Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Dossier de travail
WORKDIR /var/www/html

# 8. Copie du code
COPY . /var/www/html

# 9. Installation des dépendances (SANS dev)
RUN composer install --no-dev --optimize-autoloader

# 10. Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# 11. Script de démarrage
COPY start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

# 12. On expose le port 80 (Apache par défaut)
EXPOSE 80

CMD ["/usr/local/bin/start"]

# ⚡ Points d'attention :
# - Ce Dockerfile utilise Apache au lieu du serveur intégré.
# - Assurez-vous que le fichier 'start.sh' existe à la racine.
