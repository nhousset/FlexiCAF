FROM php:8.2-apache

# Installation des dépendances système requises pour l'extension ZIP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Installation et activation de l'extension PHP zip
RUN docker-php-ext-install zip

# Activation du module rewrite d'Apache (toujours utile pour les apps PHP)
RUN a2enmod rewrite
