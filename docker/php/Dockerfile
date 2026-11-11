ARG PHP_BASE_IMAGE=ghcr.io/sylius/sylius-php:8.3-alpine
FROM ${PHP_BASE_IMAGE}

USER root

# PDF preflight requires Poppler's command-line utilities and Ghostscript.
# Redis backs the shared Realisaprint cache, locks and request throttle.
RUN apk add --no-cache poppler-utils ghostscript $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS
