FROM composer as composer

FROM php

RUN apt update && apt install -y git curl unzip && \
    docker-php-ext-install mysqli pdo_mysql

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN composer global require hirak/prestissimo

WORKDIR /var/www

CMD ["sleep", "infinity"]