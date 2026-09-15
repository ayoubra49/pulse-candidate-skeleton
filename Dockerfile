# TODO — complète ce Dockerfile pour le service Pulse (PHP/Symfony).
#
# Contrat à respecter (voir README) :
#   - l'application écoute sur le port 8080
#   - GET /health répond 200 quand l'app est réellement prête

FROM php:8.3-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# TODO installer les dépendances (ex. composer install --no-dev --optimize-autoloader)
RUN APP_ENV=prod composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

RUN mkdir -p /data

EXPOSE 8080

# Sert le point d'entrée standard de Symfony (public/index.php)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
