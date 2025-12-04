FROM registry.digitalocean.com/ulovdomov-be/php:8.4-xdebug AS build-local

USER root

RUN apk update --no-interactive

USER www-data

COPY --chown=www-data:www-data . .
