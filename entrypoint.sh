#!/bin/bash

# Dump environment on to file so that we can load it up on the crontab
printenv > /etc/docker-env

# Run cron & tail logs
cron
touch /var/log/cert-update.log
tailf /var/log/cert-update.log
