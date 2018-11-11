#!/bin/bash

echo "waiting before first attempt..."
sleep 60

while true; do
    for domain in ${DOMAINS//,/ } ; do
        echo "starting domain $domain ($(date))..."
        /workdir/certbot-agent certs:update $KONG_ENDPOINT $EMAIL $domain 2>&1 \
            | tee /var/log/kong-certbot-agent/cert-update_$domain.log
    done
    echo "waiting before next attempt..."
    sleep 86400
done
