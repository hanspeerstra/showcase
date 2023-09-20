FROM php:8.2-cli AS development

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    libicu-dev \
    libzip-dev \
    zip \
    unzip

RUN docker-php-ext-install pdo_mysql bcmath zip intl

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

FROM development AS production

# Copy application code
COPY . .

RUN composer install \
    --no-dev \
    --no-progress \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader
