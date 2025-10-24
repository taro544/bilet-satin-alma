FROM php:8.2-cli

# Install necessary extensions
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer manually
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/db

# Expose port
EXPOSE 8080

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/var/www/html"]
