FROM withinboredom/scalable-wordpress:base

ENV WORDPRESS_VERSION=4.7.4

USER www-data

RUN wp core download --version=$WORDPRESS_VERSION
#COPY .wordpress /var/www/html

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
