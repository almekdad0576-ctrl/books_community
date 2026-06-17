# 1. Use an official PHP image with Apache built-in, matching your local PHP version
FROM php:8.5-apache

# 2. Install required system tools and PHP extensions for Laravel & SQLite
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libsqlite3-dev \
    libpq-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql pdo_pgsql pgsql

# 3. Enable Apache's mod_rewrite module for clean API routing (e.g., /api/users)
RUN a2enmod rewrite

# 4. Point Apache's root directory to Laravel's public folder where index.php lives
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Download and install Composer globally inside the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Set the working directory and copy all project files into the container
WORKDIR /var/www/html
COPY . .

# Install production dependencies using Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 7. Grant Apache permission to read/write to Laravel's storage and cache folders
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 8. Open up port 80 for incoming web/API requests
EXPOSE 80

# 9. Run migrations and start Apache when the container launches
# 9. Run discovery, migrations, and start Apache when the container launches
CMD ["sh", "-c", "php artisan package:discover && php artisan migrate --force --seed && apache2-foreground"]