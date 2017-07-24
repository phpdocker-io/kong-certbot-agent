FROM phpdockerio/php71-cli
WORKDIR /workdir

# Install certbot and cron
RUN  echo "deb http://ppa.launchpad.net/certbot/certbot/ubuntu xenial main" > /etc/apt/sources.list.d/letsencrypt.list \
    && apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 7BF576066ADA65728FC7E70A8C47BE8E75BCA694 \
    && apt-get update \
    && apt-get -y --no-install-recommends install nano cron certbot \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Composer config
COPY composer.* /workdir/
RUN composer -o install

# Expose HTTP/HTTPS ports
EXPOSE 80 443

# App and crontab
COPY update.php /workdir/
COPY crontab /var/spool/cron/crontabs/root
COPY entrypoint.sh /workdir/entrypoint.sh

CMD ["./entrypoint.sh"]
