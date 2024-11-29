<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Kong;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use PhpDockerIo\KongCertbot\Certificate;
use function count;
use function json_encode;
use function sprintf;

/**
 * Handles communication with Kong given a bunch of certificates.
 *
 * @author PHPDocker.io
 */
class Handler
{

    /** @var Error[] */
    private array $errors = [];

    public function __construct(private ClientInterface $guzzle)
    {
    }

    /**
     * Stores the given certificate in Kong.
     */
    public function store(Certificate $certificate, string $kongAdminUri, bool $allowSelfSignedCert): bool
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
            'verify'  => !$allowSelfSignedCert,
        ];

        // From Kong 0.14, they finally fixed PUT as UPSERT
        // Any domain can be used on the endpoint, as they're aliased internally to the single
        // certificate object within Kong
        try {
            $this->guzzle->request(
                method: 'put',
                uri: sprintf('%s/certificates/%s', $kongAdminUri, $certificate->getDomains()[0]),
                options: $payload,
            );
        } catch (BadResponseException $ex) {
            $request = $ex->getRequest();
            $message = $ex->getMessage();

            $responseCode = $ex->getResponse()->getStatusCode();

            $summary = sprintf(
                'Kong error %s: %s. Request method `%s`, headers %s, body %s',
                $ex->getResponse()->getStatusCode(),
                $message,
                $request->getMethod(),
                json_encode($request->getHeaders(), JSON_THROW_ON_ERROR),
                json_encode($request->getBody()->getContents(), JSON_THROW_ON_ERROR)
            );

            $this->errors[] = new Error($responseCode, $certificate->getDomains(), $summary);
        }

        return count($this->errors) === 0;
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
