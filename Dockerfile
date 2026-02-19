FROM php:8.2-cli

# Instala a extensão mysqli
RUN docker-php-ext-install mysqli

WORKDIR /app
COPY . .

EXPOSE 8080

CMD php -S 0.0.0.0:$PORT -t public
