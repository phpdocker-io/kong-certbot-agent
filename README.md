# Kong Certbot agent
Let's Encrypt integration with Kong

This repository provides with a cron-based certbot agent that will attempt to acquire Let's Encrypt certificates you control
for a list of subdomains you provide, and provision Kong with them.

There's an example [Kubernetes deployment configuration](kubernetes/certbot-cron.yml) you can use as a guide to deploy wherever you need.

## How to

### Run the container
  - The container takes 3 environment variables to operate:
    - KONG_ENDPOINT: this will be the http endpoint your kong admin is at, without its path. ie `http://kong:8001`
    - EMAIL: this is the email address linked to your let's encrypt certificates.
    - DOMAINS: this is a comma-separated list of domains we'll be asking certificates for.
  - Deploy container in your environment.
  - It will automagically run the updater script every 24th of the month.
  - Profit!
  
### Kong configuration

In order for the challenge to work correctly, you need to open an API in Kong pointing to the container at a very 
specific URL path. It MUST respond on every domain you're requesting certs for. 

When it comes the time to run certbot, it will open an HTTP server, put some stuff on a specific path, then ping 
Let's Encrypt, which will attempt to read that from the domain requested. If successful, a certificate is generated.

This is an API definition example in Kong admin:

```json
{
  "methods": [
    "GET",
    "OPTIONS"
  ],
  "uris": [
    "/.well-known/acme-challenge"
  ],
  "id": "asdasdasnd.asd",
  "upstream_read_timeout": 60000,
  "preserve_host": false,
  "created_at": 1500911044000,
  "upstream_connect_timeout": 60000,
  "upstream_url": "http://kong-certbot-agent/.well-known/acme-challenge/",
  "strip_uri": true,
  "https_only": false,
  "name": "certbot",
  "http_if_terminated": true,
  "upstream_send_timeout": 60000,
  "retries": 5
}
```

This assumes that `http://kong-certbot-agent` is correctly pointing to the agent's container.

## Kubernetes

Head off to the [Kubernetes deployment configuration](kubernetes) for examples, using a Kubernetes service
plus either a [deployment](kubernetes/certbot-cron.yml), or a [kubernetes cronjob](kubernetes/certbot-cronjob.yml). 

Cronjobs (formerly `scheduledjob`) are a relatively new thing in Kubernetes and won't be available unless you're on 
Kubernetes 1.4+.

Note: your k8s service will always time out since there's nothing listening on HTTP except for when certbot itself is 
running and requesting certs from LE.

## Command line tool

You can, alternatively, simply run the actual command yourself. This will allow you to use your own scheduling around
it, as it's done on the [kubernetes cronjob example](kubernetes/certbot-cronjob.yml).

```bash
# Get a certificate for three subdomains, and submit to kong
docker run -it --rm phpdockerio/kong-certbot \
    ./certbot-agent certs:update \
    http://kong-admin:8001 \
    foo@bar.com \
    bar.com,foo.bar.com,www.bar.com

# Get a TEST certificate for three subdomains, and submit to kong
docker run -it --rm phpdockerio/kong-certbot \
    ./certbot-agent certs:update -t \
    http://kong-admin:8001 \
    foo@bar.com \
    bar.com,foo.bar.com,www.bar.com

```
