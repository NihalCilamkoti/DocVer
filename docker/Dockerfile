FROM php:8.3-fpm-alpine

# Set non-root working environment
WORKDIR /var/www/html

# Install system dependencies & build tools
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    bash \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        intl \
        bcmath \
        opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create non-root user 'docver'
RUN addgroup -g 1000 docver && adduser -u 1000 -G docver -D -s /bin/sh docver

# Copy application files
COPY --chown=docver:docver . /var/www/html

# Switch to non-root user
USER docver

EXPOSE 9000

CMD ["php-fpm"]
