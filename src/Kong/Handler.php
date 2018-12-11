<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Kong;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use PhpDockerIo\KongCertbot\Certificate;

/**
 * Handles communication with Kong given a bunch of certificates.
 *
 * @author PHPDocker.io
 */
class Handler
{
    /**
     * @var ClientInterface
     */
    private $guzzle;

    /**
     * @var Error[]
     */
    private $errors = [];

    public function __construct(ClientInterface $guzzle)
    {
        $this->guzzle = $guzzle;
    }

    /**
     * Stores the given certificate in Kong.
     *
     * @param Certificate $certificate
     * @param string      $kongAdminUri
     *
     * @return bool
     */
    public function store(Certificate $certificate, string $kongAdminUri): bool
    {
        $payload = [
            'headers'     => [
                'accept' => 'application/json',
            ],
            'form_params' => [
                'cert'   => $certificate->getCert(),
                'key'    => $certificate->getKey(),
                'snis[]' => $certificate->getDomains(),
            ],
        ];

        // Unfortunately for us, PUT is not UPSERT
        try {
            $this->guzzle->request('post', \sprintf('%s/certificates', $kongAdminUri), $payload);
        } catch (ClientException $ex) {
            // Update certificates only on conflict
            if ($this->isConflict($ex) === false) {
                $this->errors[] = new Error($ex->getCode(), $certificate->getDomains(), $ex->getMessage());

                return false;
            }

            // Remove SNIs from PATCH as we will be patching into each domain individually
            unset($payload['form_params']['snis[]']);

            foreach ($certificate->getDomains() as $domain) {
                try {
                    $this->guzzle->request(
                        'patch',
                        \sprintf('%s/certificates/%s', $kongAdminUri, $domain),
                        $payload
                    );
                } catch (ClientException $patchException) {
                    $this->errors[] = new Error($patchException->getCode(), [$domain], $patchException->getMessage());
                }
            }
        }

        return \count($this->errors) === 0;
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Preexisting certificates can generate either a 409, or a 400 leaking from Kong's database with some stuff on it.
     *
     * @param ClientException $ex
     *
     * @return bool
     */
    private function isConflict(ClientException $ex): bool
    {
        $response         = $ex->getResponse();
        $responseCode     = $response !== null ? $response->getStatusCode() : false;
        $responseContents = $response !== null ? $response->getBody()->getContents() : '';

        switch (true) {
            case $response === null:
                return false;

            case $responseCode === 409:
                return true;

            case $responseCode === 400:
                $decoded = json_decode($responseContents);

                return \preg_match('/already associated with existing certificate/', $decoded->message ?? '') > 0;

            default:
                return false;
        }
    }
}
