# AI Coding Assistant — image produksi untuk Coolify (Linux)
FROM php:8.3-apache-bookworm

# Installer ekstensi prebuilt (mlocati) — tanpa compile, jauh lebih cepat & anti-stuck
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

# Driver MS SQL Server (untuk DB akademik) + ekstensi PHP
RUN apt-get update && apt-get install -y \
    unzip git curl gnupg2 apt-transport-https \
    poppler-utils tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng antiword \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-tools.list \
    && apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
    && install-php-extensions gd zip intl mysqli pdo_mysql opcache sqlsrv pdo_sqlsrv \
    && a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/* /tmp/*

# Opcache: revalidasi AKTIF karena kode di-mount dari host (bind-mount).
# Tanpa ini, setiap edit file di host tidak terbaca sampai container restart.
RUN { echo 'opcache.enable=1'; echo 'opcache.validate_timestamps=1'; echo 'opcache.revalidate_freq=2'; } > /usr/local/etc/php/conf.d/opcache-prod.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN echo '<Directory ${APACHE_DOCUMENT_ROOT}>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . .

# .env hanya dibuat bila belum ada (Coolify menyuplai via Environment Variables)
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && if [ ! -f .env ]; then cp env .env; fi \
    && sed -i 's/^# CI_ENVIRONMENT = production/CI_ENVIRONMENT = production/' .env

RUN chown -R www-data:www-data /var/www/html/writable \
    && chmod -R 775 /var/www/html/writable

# Normalisasi CRLF (file ditulis dari Windows) agar entrypoint bisa dieksekusi
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && sh -n /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=3 \
    CMD curl -fsS http://localhost/ > /dev/null || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
