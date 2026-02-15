# Use the official PHP 8.5 CLI Alpine image for a small footprint
FROM php:8.5-cli-alpine

# Install system dependencies for Composer and PHP extensions
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    libzip-dev

# Install PHP extensions needed for networking/zip
RUN docker-php-ext-install zip pcntl

# Install Composer from the official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory inside the container
WORKDIR /var/www

# Copy composer files first to leverage Docker cache
COPY composer.json ./

# Install dependencies (no-dev skips testing tools for a smaller image)
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of your application code
COPY . .

# Ensure the bot script is executable
RUN chmod +x Main.php

# Run the bot when the container starts
CMD ["php", "Main.php"]
