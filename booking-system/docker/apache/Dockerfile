FROM httpd:2.4.48-alpine

RUN apk update; \
    apk upgrade

# Copy apache vhost file to proxy php requests to php-fpm container
COPY docker/apache/site.conf /usr/local/apache2/conf/site.conf

RUN echo "Include /usr/local/apache2/conf/site.conf" \
    >> /usr/local/apache2/conf/httpd.conf
