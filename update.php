<?php

include __DIR__ . '/vendor/autoload.php';

const CERTS_BASE_PATH = '/etc/letsencrypt/live';

// Config from environment
$kongAdminUri = trim($_SERVER['KONG_ADMIN_ENDPOINT'] ?? null);
$email = trim($_SERVER['LETSENCRYPT_EMAIL'] ?? null);
$domains = explode(',', trim($_SERVER['SUPPORTED_DOMAINS'] ?? ''));

$errors = [];
if ($kongAdminUri === '') {
    $errors[] = 'KONG_ADMIN_ENDPOINT environment variable is required';
}

if ($email === '') {
    $errors[] = 'LETSENCRYPT_EMAIL environment variable is required';
}

if (($domains[0] ?? '') === '') {
    $errors[] = 'SUPPORTED_DOMAINS environment variable is required (comma separated list)';
}

if (count($errors) > 0) {
    $message = "\n
        ## Certbot kong agent

        Usage:

           export KONG_ADMIN_ENDPOINT=http://foo:8001
           export LETSENCRYPT_EMAIL=foo@bar.com
           export SUPPORTED_DOMAINS=foo.com,bar.foo.com

           php update.php
        
        Errors: ";

    $message .= implode('; ', $errors);

    echo $message, "\n\n";
    exit(1);
}

// Compose cerbot command
$domainsParsed = '-d ' . implode(' -d ', $domains);
$renewCmd = escapeshellcmd(sprintf("certbot certonly --test-cert --agree-tos --standalone -n -m %s --expand %s", $email, $domainsParsed));
dump($renewCmd);

// ... and execute
$output = [];
$cmdStatus = exec($renewCmd, $output);

dump($cmdStatus);

// Update kong admin with the new certificates foreach domain
$guzzle = new \GuzzleHttp\Client();
$request = new \GuzzleHttp\Psr7\Request(
    'PUT',
    sprintf('%s/certificates', $kongAdminUri),
    [
        'content-type' => 'application/json',
        'accept' => 'application/json',
    ]
);

foreach ($domains as $domain) {
    $basePath = sprintf('%s/%s', CERTS_BASE_PATH, $domain);
    $payload = [
        'cert' => file_get_contents(sprintf('%s/cert.pem', $basePath)),
        'key' => file_get_contents(sprintf('%s/privkey.pem', $basePath)),
        'snis' => [$domain],
    ];

    $request = $request->withBody(stream_for(json_encode($payload)));
    
    dump(sprintf('Updating certificates config for %s', $domain));
    
    $response = $guzzle->send($request);
    dump($response);
}
