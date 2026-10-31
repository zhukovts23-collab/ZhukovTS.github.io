FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libxslt1-dev libonig-dev libcurl4-openssl-dev && docker-php-ext-install pdo_mysql xsl mbstring curl && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . .
RUN printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-enabled/gameverse.conf
