#!/bin/bash
printenv > /etc/docker-env
cron
touch /var/log/cert-update.log
tailf /var/log/cert-update.log
