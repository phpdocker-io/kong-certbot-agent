#!/usr/bin/env bash

EXTRA_PARAMS=""
if [[ ! -z "${TEST_CERT}" ]]; then
    EXTRA_PARAMS="--test-cert"
fi;

exec /workdir/certbot-agent certs:update ${EXTRA_PARAMS} ${KONG_ENDPOINT} ${EMAIL} ${DOMAINS}
