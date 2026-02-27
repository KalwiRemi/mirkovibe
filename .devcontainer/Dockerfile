FROM mcr.microsoft.com/devcontainers/php:1-8.3

RUN rm -f /etc/apt/sources.list.d/yarn.list /etc/apt/sources.list.d/yarn*.list \
    && apt-get update && apt-get install -y libpq-dev postgresql postgresql-client \
    && echo 'vscode ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/90-vscode-nopasswd \
    && chmod 0440 /etc/sudoers.d/90-vscode-nopasswd \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
