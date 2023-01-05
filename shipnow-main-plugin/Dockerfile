FROM wordpress:5.7.0

ARG WOOCOMMERCE_VERSION=5.2.1

RUN apt-get update
RUN apt-get install --no-install-recommends unzip

# Setup Woocommerce.
WORKDIR /tmp
RUN curl -O https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip
RUN unzip woocommerce.${WOOCOMMERCE_VERSION}.zip -d /usr/src/wordpress/wp-content/plugins/
RUN rm woocommerce.${WOOCOMMERCE_VERSION}.zip

# Setup Shipnow Woocommerce plugin.
WORKDIR /usr/src/wordpress/wp-content/plugins/
RUN mkdir shipnow-shipping
COPY *.php ./shipnow-shipping/
COPY css img js html languages ./shipnow-shipping/

# Change plugins ownership to www-data.
RUN chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins/

WORKDIR /var/www/html
