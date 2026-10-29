FROM php:8.2-cli-alpine
WORKDIR /srv
COPY shared/php/src ./shared/php/src
COPY docker/mcp/public/index.php ./public/index.php
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
