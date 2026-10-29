ARG YOOWII_PHP_IMAGE
FROM ${YOOWII_PHP_IMAGE} AS app

FROM nginx:1.27-alpine
COPY docker/preprod/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /srv/sylius/public/ /srv/sylius/public/
