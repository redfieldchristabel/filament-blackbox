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

# 3. Coverage driver: phpunit.xml.dist configures coverage reports and failOnWarning=true,
# so Pest exits 1 ("No code coverage driver available") without one.
RUN pecl install pcov && docker-php-ext-enable pcov


USER $user

# The base image entrypoint runs `composer install` + `php artisan optimize`, which
# fails in a package (no artisan). Clear it; docker-compose already overrides it.
ENTRYPOINT []

EXPOSE 11111

