<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Kong;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
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
            'headers' => [
                'accept' => 'application/json',
            ],
            'json'    => [
                'cert' => $certificate->getCert(),
                'key'  => $certificate->getKey(),
                'snis' => $certificate->getDomains(),
            ],
        ];

        // Unfortunately for us, PUT is not UPSERT
        try {
            $this->guzzle->request('post', \sprintf('%s/certificates', $kongAdminUri), $payload);
        } catch (BadResponseException $ex) {
            // Update certificates only on conflict
            if ($this->isConflict($ex) === false) {
                $this->handleUnknownErrors($ex, $certificate);

                return false;
            }

            // Remove SNIs from PATCH as we will be patching into each domain individually
            unset($payload['json']['snis']);

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
     * @param BadResponseException $ex
     *
     * @return bool
     */
    private function isConflict(BadResponseException $ex): bool
    {
        $response         = $ex->getResponse();
        $responseCode     = $response !== null ? $response->getStatusCode() : null;
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

    /**
     * Parses an unhandled guzzle bad response exception into an error object and adds to the list
     *
     * @param BadResponseException $ex
     * @param Certificate          $certificate
     */
    private function handleUnknownErrors(BadResponseException $ex, Certificate $certificate): void
    {
        $request  = $ex->getRequest();
        $response = $ex->getResponse();
        $message  = $ex->getMessage();

        if ($response === null) {
            $message = 'empty response';
        }

        $responseCode = $ex->getResponse() !== null ? $ex->getResponse()->getStatusCode() : $ex->getCode();

        $summary = \sprintf(
            'Kong error %s: %s. Request method `%s`, headers %s, body %s',
            $responseCode,
            $message,
            $request->getMethod(),
            \json_encode($request->getHeaders()),
            \json_encode($request->getBody()->getContents())
        );

        $this->errors[] = new Error($responseCode, $certificate->getDomains(), $summary);
    }
}
