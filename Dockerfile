FROM composer:2.8.3
RUN apk add --no-cache git g++ gcc libc-dev make zlib-dev \
    libpng-dev libzip-dev icu-dev libpq-dev php83-dev \
    libxml2-dev musl-locales linux-headers npm sqlite-dev
# Set the locale
ENV MUSL_LOCPATH /usr/share/i18n/locales/musl
ENV LANG en_AU.UTF-8  
ENV LANGUAGE en_AU:en  
ENV LC_ALL en_AU.UTF-8   
RUN docker-php-ext-install intl gd sockets zip pdo_pgsql xml sysvshm sysvsem pcntl
RUN pecl install apcu xdebug; \
    docker-php-ext-enable apcu; \
    docker-php-ext-enable xdebug; \
    echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini ;\
    echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini ;\
    echo -e "\nxdebug.mode = develop, debug\nxdebug.start_with_request = 1\nxdebug.log_level = 0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN mkdir -p /var/hazaar; \
    wget https://api.hazaar.io/databases/geodata.db -O /var/hazaar/geodata.db
RUN set -e -x \
    && curl -L https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o /usr/local/bin/php-cs-fixer \
    && chmod +x /usr/local/bin/php-cs-fixer
ENV PATH="/hazaar/bin:${PATH}"