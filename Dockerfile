FROM phpdockerio/php73-cli
MAINTAINER https://phpdocker.io

WORKDIR /workdir

# The following environment variables are for you to set when you run the container
# Endpoint to Kong admin
ENV KONG_ENDPOINT=http://foo:8001

# Email the domains are associated with at Let's Encrypt
ENV EMAIL=foo@bar.com

# Comma separated list of domains to acquire certs for
ENV DOMAINS=foo.com,www.foo.com,bar.foo.com

# Install certbot from PPA instead of ubuntu's repos to ensure we always got the latest
RUN  echo "deb http://ppa.launchpad.net/certbot/certbot/ubuntu bionic main" > /etc/apt/sources.list.d/letsencrypt.list \
    && apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 7BF576066ADA65728FC7E70A8C47BE8E75BCA694 \
    && apt-get update \
    && apt-get -y --no-install-recommends install nano certbot \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Composer config - add early to benefit from docker build caches
COPY composer.* /workdir/
RUN composer -o install --no-dev

COPY . /workdir/

# Expose HTTP/HTTPS ports for certbot standalone
EXPOSE 80 443

# Run entrypoint
CMD ["./entrypoint.sh"]
