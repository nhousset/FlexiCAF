# Utilisation de l'image officielle PHP avec Apache
FROM php:8.2-apache

# Mise à jour et installation des dépendances système requises
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Installation de l'extension PHP 'zip' (nécessaire pour le module de backup)
RUN docker-php-ext-install zip

# Activation du module rewrite d'Apache (au cas où, bonne pratique)
RUN a2enmod rewrite

# Copie de l'intégralité du code source dans le dossier par défaut d'Apache
COPY ./src /var/www/html/

# Gestion des droits : on s'assure que le serveur web (www-data) a les droits d'écriture sur le dossier db
RUN chown -R www-data:www-data /var/www/html/db \
    && chmod -R 775 /var/www/html/db

# Déclaration du volume pour le dossier contenant les JSON
VOLUME ["/var/www/html/db"]
