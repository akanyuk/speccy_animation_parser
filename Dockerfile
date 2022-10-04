FROM ubuntu:18.04

RUN apt-get update \
    && apt-get install -y nginx php-fpm tzdata php-mbstring php-iconv php-json php-zip php-simplexml php-gd \
    && ln -s /usr/sbin/php-fpm7 /usr/sbin/php-fpm \
    && rm -rf /etc/nginx/conf.d/* /etc/php7/conf.d/* /etc/php7/php-fpm.d/*

COPY docker-files /
COPY --chown=www-data:www-data src/ /www/src
COPY --chown=www-data:www-data vendor/ /www/vendor
COPY --chown=www-data:www-data index.php /www

WORKDIR /www
ENTRYPOINT ["/start.sh"]
EXPOSE 80
