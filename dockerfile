# Use the official PHP image with Apache
FROM php:8.2-apache

# Install zip and unzip (required for Composer to work)
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli

# Copy the Composer binary from the official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy your source code into the container's web directory
COPY src/ /var/www/html/

# Expose port 80
EXPOSE 80