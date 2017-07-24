<?php

include __DIR__ . '/vendor/autoload.php';

const CERTS_BASE_PATH = '/etc/letsencrypt/live';

// Config from environment
$kongAdminUri = $_SERVER['KONG_ADMIN_ENDPOINT'] ?? null;
$email = $_SERVER['LETSENCRYPT_EMAIL'] ?? null;
$domains = explode(',', $_SERVER['SUPPORTED_DOMAINS'] ?? '');

if (empty($kongAdminUri)) {
    throw new \InvalidArgumentException('KONG_ADMIN_ENDPOINT environment variable is required');
}

if (empty($email)) {
    throw new \InvalidArgumentException('LETSENCRYPT_EMAIL environment variable is required');
}

if (empty($domains)) {
    throw new \InvalidArgumentException('SUPPORTED_DOMAINS environment variable is required (comma separated list)');
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
