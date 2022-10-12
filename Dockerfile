FROM php:8.1-alpine
RUN apk update
RUN apk add git g++ gcc libc-dev make zlib-dev libpng-dev libzip-dev icu-dev libpq-dev php81-dev libxml2-dev musl-locales
# Set the locale
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV MUSL_LOCPATH /usr/share/i18n/locales/musl
ENV LANG en_AU.UTF-8  
ENV LANGUAGE en_AU:en  
ENV LC_ALL en_AU.UTF-8   
RUN docker-php-ext-install intl gd sockets zip pdo_pgsql xml 
RUN pecl install apcu
RUN docker-php-ext-enable apcu
RUN echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
