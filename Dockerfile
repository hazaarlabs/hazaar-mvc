FROM php:8.4-alpine

# Copy composer from the official composer image
COPY --from=composer:2.8.3 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache git g++ gcc autoconf libc-dev make zlib-dev \
    libpng-dev libzip-dev icu-dev libpq-dev envsubst \
    libxml2-dev musl-locales linux-headers npm sqlite-dev

# Set the locale
ENV MUSL_LOCPATH /usr/share/i18n/locales/musl
ENV LANG en_AU.UTF-8  
ENV LANGUAGE en_AU:en  
ENV LC_ALL en_AU.UTF-8   

# Install PHP extensions
RUN docker-php-ext-install intl gd sockets zip pdo_pgsql xml sysvshm sysvsem pcntl

RUN pecl install apcu xdebug; \
    docker-php-ext-enable apcu; \
    docker-php-ext-enable xdebug; \
    echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini ;\
    echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini ;\
    echo -e "\nxdebug.mode = develop, debug\nxdebug.start_with_request = 1\nxdebug.log_level = 0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN mkdir -p /var/hazaar && \
    wget https://api.hazaar.io/databases/geodata.db -O /var/hazaar/geodata.db

RUN set -e -x \
    && curl -L https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o /usr/local/bin/php-cs-fixer \
    && chmod +x /usr/local/bin/php-cs-fixer

RUN wget https://github.com/micromata/dave/releases/download/v0.5.0/dave-linux-amd64.zip && \
    unzip dave-linux-amd64.zip -d /usr/local/bin/ && \
    rm dave-linux-amd64.zip && \
    chmod +x /usr/local/bin/dave && \
    mkdir -p /var/hazaar/webdav

ENV PATH="/hazaar/bin:${PATH}"