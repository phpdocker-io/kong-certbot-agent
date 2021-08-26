#!/usr/bin/env bash

EXTRA_PARAMS=""
if [[ ! -z "${TEST_CERT}" ]]; then
    EXTRA_PARAMS="--test-cert"
fi;

if [[ ! -z "${ALLOW_SELF_SIGNED_CERT_KONG}" ]]; then
    EXTRA_PARAMS="--allow-self-signed-cert-kong"
fi;

exec /workdir/certbot-agent certs:update ${EXTRA_PARAMS} ${KONG_ENDPOINT} ${EMAIL} ${DOMAINS}
