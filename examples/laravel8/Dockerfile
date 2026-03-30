FROM php:7.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    autoconf \
    gcc \
    make \
    unzip \
    git \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install zip pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Build and install the opentelemetry_php74 extension
COPY ext/ /usr/src/ext-otel/
WORKDIR /usr/src/ext-otel
RUN phpize \
    && ./configure --enable-opentelemetry_php74 \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable opentelemetry_php74
RUN docker-php-ext-install pdo_mysql \
    && docker-php-ext-enable pdo_mysql

WORKDIR /app

# Copy entrypoint script
COPY examples/laravel8/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
