FROM php:8.2-fpm AS development

# Install system dependencies
# ...

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

RUN chown -R www-data:www-data /app

FROM development AS production

# Copy application code
COPY . .

RUN composer install \
    --no-dev \
    --no-progress \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader
