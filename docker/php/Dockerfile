ARG PHP_BASE_IMAGE=ghcr.io/sylius/sylius-php:8.3-alpine
FROM ${PHP_BASE_IMAGE}

USER root

# PDF preflight requires Poppler's command-line utilities and Ghostscript.
RUN apk add --no-cache poppler-utils ghostscript
