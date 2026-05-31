FROM php:8.3-cli

# Install system packages AND PHP extension dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip curl intl

# Install Node.js (Version 20)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP dependencies (ignore platform reqs to prevent Docker OS mismatches)
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-reqs

# Install Node dependencies and build Vue assets
RUN npm install
RUN npm run build

# Create storage link for your uploaded covers
RUN php artisan storage:link

# Give PHP permission to write logs and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Start Laravel
CMD php artisan serve --host=0.0.0.0 --port=8000