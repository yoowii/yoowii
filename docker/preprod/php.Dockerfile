# Build context: repository root
FROM ghcr.io/sylius/sylius-php:8.3-alpine AS vendor
WORKDIR /srv/sylius
ENV APP_ENV=prod APP_DEBUG=0
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader

FROM node:22-alpine AS assets
WORKDIR /srv/sylius
COPY --from=vendor /srv/sylius /srv/sylius
# Use the committed Yarn v1 lockfile; npm 10 fails resolving Sylius file: dependencies.
RUN yarn --version \
    && yarn install --frozen-lockfile --non-interactive \
    && yarn build:prod

FROM ghcr.io/sylius/sylius-php:8.3-alpine
USER root
WORKDIR /srv/sylius
RUN apk add --no-cache poppler-utils ghostscript $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS
COPY --from=vendor /srv/sylius /srv/sylius
COPY --from=assets /srv/sylius/public/build /srv/sylius/public/build
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
ENV APP_ENV=prod APP_DEBUG=0
RUN mkdir -p var/cache var/log public/media && chown -R www-data:www-data var public/media
USER www-data
