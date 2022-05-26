FROM phpdockerio/php:8.1-cli
MAINTAINER https://phpdocker.io

WORKDIR /workdir

# The following environment variables are for you to set when you run the container
# Endpoint to Kong admin
ENV KONG_ENDPOINT=http://foo:8001

# Email the domains are associated with at Let's Encrypt
ENV EMAIL=foo@bar.com

# Comma separated list of domains to acquire certs for
ENV DOMAINS=foo.com,www.foo.com,bar.foo.com

RUN apt-get update; \
  apt-get -y --no-install-recommends install certbot; \
  apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Composer config - add early to benefit from docker build caches
COPY composer.* /workdir/
RUN composer -o install --no-dev

COPY . /workdir/

# Expose HTTP/HTTPS ports for certbot standalone
EXPOSE 80 443

# Run entrypoint
CMD ["./entrypoint.sh"]
