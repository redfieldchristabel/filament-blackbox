FROM ghcr.io/redfieldchristabel/laravel:8.4-cli-filament


ENV DOCKER_ENV=development
ENV PHPSTAN_PRO_WEB_PORT=11111

# Switch to root to perform system-level changes
USER root

# 1. Install system dependencies for intl, zip, and build tools
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    autoconf \
    g++ \
    make \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Compile and enable the extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip


USER $user

EXPOSE 11111

