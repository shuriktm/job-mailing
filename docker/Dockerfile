FROM alpine:latest

WORKDIR /var/www/mailing

# Essentials
RUN echo "UTC" > /etc/timezone
RUN apk add --no-cache curl \
    nginx \
    supervisor \
    mysql \
    mysql-client \
    mariadb-connector-c

# Install bash
RUN apk add bash
RUN sed -i 's/bin\/ash/bin\/bash/g' /etc/passwd

# Install PHP
RUN apk add --no-cache php81 \
    php81-common \
    php81-fpm \
    php81-phar \
    php81-iconv \
    php81-mbstring \
    php81-openssl \
    php81-pdo \
    php81-opcache \
    php81-cli \
    php81-json

# Configure supervisor
RUN mkdir -p /run/supervisord/
RUN mkdir -p /etc/supervisor.d/
COPY ./docker/supervisor/supervisord.ini /etc/supervisor.d/supervisord.ini

# Configure PHP
RUN ln -s /usr/bin/php81 /usr/bin/php
RUN mkdir -p /run/php/
RUN touch /run/php/php8.1-fpm.pid

COPY ./docker/php/php-fpm.conf /etc/php81/php-fpm.conf
COPY ./docker/php/php-custom.ini /etc/php81/conf.d/10_custom.ini

# Configure nginx
COPY ./docker/nginx/nginx.conf /etc/nginx/
COPY ./docker/nginx/nginx-mailing.conf /etc/nginx/modules/

RUN mkdir -p /run/nginx/
RUN touch /run/nginx/nginx.pid

RUN ln -sf /dev/stdout /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

# Install Composer
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN rm -rf composer-setup.php

# Entrypoint
COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

CMD ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]
