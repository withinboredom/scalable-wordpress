FROM php:7.1.3-apache

# install the PHP extensions we need
RUN set -ex; \
	\
	apt-get update; \
	apt-get install -y \
		libjpeg-dev \
		libpng12-dev \
		libmemcached-dev \
		libmagickwand-6.q16-dev \
		git \
		less \
		unzip \
	; \
	rm -rf /var/lib/apt/lists/*; \
	\
	docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr; \
	docker-php-ext-install gd mysqli \
	&& git clone https://github.com/igbinary/igbinary.git /usr/src/php/ext/igbinary \
	&& cd /usr/src/php/ext/igbinary \
	&& docker-php-ext-configure igbinary \
	&& docker-php-ext-install igbinary \
	&& docker-php-ext-install opcache \
	&& git clone https://github.com/php-memcached-dev/php-memcached /usr/src/php/ext/memcached \
    && cd /usr/src/php/ext/memcached && git checkout -b php7 origin/php7 \
    && docker-php-ext-configure memcached --enable-memcached-igbinary \
    && docker-php-ext-install memcached \
    && ln -s /usr/lib/x86_64-linux-gnu/ImageMagick-6.8.9/bin-Q16/MagickWand-config /usr/bin \
    && pecl install imagick \
    && echo "extension=imagick.so" > /usr/local/etc/php/conf.d/ext-imagick.ini \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=2'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN a2enmod rewrite expires

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

ENV WORDPRESS_VERSION=4.7.3

USER www-data

RUN wp core download --version=$WORDPRESS_VERSION

COPY wordpress-entrypoint.sh /usr/local/bin/
COPY healthcheck.sh /usr/local/bin/
COPY php.ini /usr/local/etc/php/php.ini
COPY wp-cli.yml /var/www/html/wp-cli.yml

COPY object-cache.php /var/www/html/wp-content/object-cache.php
COPY advanced-cache.php /var/www/html/wp-content/advanced-cache.php

COPY plugins/ /var/www/html/wp-content/mu-plugins
COPY wordpress.dockerfile /var/www/html/wp-content/Dockerfile

USER root

HEALTHCHECK CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["wordpress-entrypoint.sh"]
CMD ["apache2-foreground"]
