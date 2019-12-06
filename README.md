[![Build status](https://ci.auronconsulting.co.uk/api/v1/teams/main/pipelines/kong-certbot-agent-master/jobs/analyze-master/badge)](https://ci.auronconsulting.co.uk/teams/main/pipelines/kong-certbot-agent-master)
[![Code coverage](https://codecov.io/gh/luispabon/kong-certbot-agent/branch/master/graph/badge.svg)](https://codecov.io/gh/luispabon/kong-certbot-agent)

# Kong Certbot agent
Let's Encrypt integration with Kong

This repository provides with a cron-based certbot agent that will attempt to acquire Let's Encrypt certificates you control
for a list of subdomains you provide, and provision Kong with them.

Ideal for integrating a Kong deployment in Kubernetes with Let's Encrypt.

There's an example [kubernetes cronjob](kubernetes/certbot-cronjob.yml) you can use as a guide to deploy wherever you need.

## Compatibility

  * Kong Certbot Agent 3.x: compatible with Kong 0.14 and 1.x. Kubernetes examples compatible with Kubernetes >= 1.8
  * Kong Certbot Agent 2.x: compatible with Kong 0.14 and 1.x.  Kubernetes examples compatible with Kubernetes <= 1.8
  * Kong Certbot Agent 1.x: compatible with Kong <= 0.13.  Kubernetes examples compatible with Kubernetes <= 1.8

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

In order for the challenge to work correctly, you need to open up a service and a route in Kong pointing to the container at a very
specific URL path. It MUST respond on every domain you're requesting certs for.

When it comes the time to run certbot, it will open an HTTP server, put some stuff on a specific path, then ping
Let's Encrypt, which will attempt to read that from the domain requested. If successful, a certificate is generated.

This is a service definition example in Kong admin:

```json
{
    "host": "kong-certbot-agent",
    "created_at": 1543512083,
    "connect_timeout": 60000,
    "id": "service-id-foo",
    "protocol": "http",
    "name": "KongCertbot",
    "read_timeout": 60000,
    "port": 80,
    "updated_at": 1543513810,
    "retries": 5,
    "write_timeout": 60000
}
```

This assumes that `http://kong-certbot-agent` is correctly pointing to the agent's container.

Then, associate this route to it:

```json
{
    "created_at": 1543512115,
    "strip_path": false,
    "hosts": [
        "your.list",
        "of.domains",
        "for.the",
        "same.certificate"
    ],
    "preserve_host": false,
    "regex_priority": 0,
    "updated_at": 1543513584,
    "paths": [
        "/.well-known/acme-challenge"
    ],
    "service": {
        "id": "service-id-foo"
    },
    "methods": [
        "GET"
    ],
    "protocols": [
        "http"
    ],
    "id": "route-id-foo"
}
```

## Kubernetes

Here's a [kubernetes cronjob example](kubernetes/certbot-cronjob.yml).

### Note

Your k8s service SHOULD always time out since there's nothing listening on HTTP except for when certbot itself is
running and requesting certs from LE.

## Command line tool

You can, alternatively, run the actual command yourself. This will allow you to use your own scheduling around
it, as it's done on the [kubernetes cronjob example](kubernetes/certbot-cronjob.yml).

```bash
# Get a certificate for three subdomains, and submit to kong
docker run -it --rm \
    -e KONG_ENDPOINT=http://kong-admin:8001 \
    -e EMAIL=foo@bar.com \
    -e DOMAINS=bar.com,foo.bar.com,www.bar.com \
    phpdockerio/kong-certbot-agent


# Get a TEST certificate for three subdomains, and submit to kong
docker run -it --rm \
    -e KONG_ENDPOINT=http://kong-admin:8001 \
    -e EMAIL=foo@bar.com \
    -e DOMAINS=bar.com,foo.bar.com,www.bar.com \
    -e TEST_CERT=true \
    phpdockerio/kong-certbot-agent

```

## FAQ

### How many domains can I get certs for?

You can give the agent a pretty big list of domains to acquire certificates for (100), but bear in mind it will be one certificate
shared among all of them. You might want to set up different cronjobs for different sets of certificates, grouped in a manner
that makes sense to you. Also, if one of the domains you're getting a certificate from fails the HTTP challenge, cert acquisition
for the whole group fails.

### How about wildcard certs?

Unfortunately, certbot does not support http challenges on wildcard certs, needing to resort to other types (like DNS).
Due to the way certbot agent works, this will never be supported by the agent.

### Any considerations on a first time set up?

Yes. Certbot has a limit of [50 certificate requests per domain per week](https://letsencrypt.org/docs/rate-limits/) - it is very easy to go over this limit during
your initial set up while you manage to get all your stuff lined up together nicely:

  * Use test certs initially, allowances are more generous. You can modify the command to `command: [ "/workdir/certbot-agent", "certs:update", "$(KONG_ENDPOINT)", "$(EMAIL)", "$(DOMAINS)", "--test-cert" ]` until you have everything right.
  * Ensure your scheduling does not retry a failed command. It's very unlikely it will succeed a second time with the same parameters
  and you'll go over the limit quicker than fast, especially in Kubernetes which by default will retry until your cluster goes down. The
  [example kubernetes cronjob](kubernetes/certbot-cronjob.yml) specifically stops this from happening

### How often should I renew my certs?

By default, certbot has a limit of 50 certificate requests per domain per week as mentioned earlier, so bear this in mind. Also, certs are good for 3 months. Let's Encrypt themselves recommend once every 60 days. [The example kubernetes cronjob](kubernetes/certbot-cronjob.yml)
is setup like so.

You can certainly do it more often, but there's no point in spamming Let's Encrypt with extra requests - remember this is a shared resource, free as in freedom and beer, and someone surely pays for it. Be considerate.
