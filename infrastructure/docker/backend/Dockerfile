FROM php:8.3-fpm-alpine

# Install system dependencies & Tesseract OCR
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    tesseract-ocr \
    tesseract-ocr-data-eng \
    poppler-utils \
    mariadb-client \
    shadow

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd zip opcache bcmath pcntl

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create non-root application user
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
