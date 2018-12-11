<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Kong;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use PhpDockerIo\KongCertbot\Certificate;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string
     */
    private $kongAdminUri;

    /**
     * @var ClientException[]|GuzzleException[]
     */
    private $errors = [];

    public function __construct(string $kongAdminUri, ClientInterface $guzzle, OutputInterface $output)
    {
        $this->kongAdminUri = $kongAdminUri;
        $this->guzzle       = $guzzle;
        $this->output       = $output;
    }

    /**
     * Stores the given certificate in Kong.
     *
     * @param Certificate $certificate
     *
     * @return bool
     * @throws GuzzleException
     */
    public function store(Certificate $certificate): bool
    {
        $outputDomains = \implode(', ', $certificate->getDomains());
        $this->output->writeln(\sprintf('Updating certificates config for %s', $outputDomains));

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
            $this->guzzle->request('post', \sprintf('%s/certificates', $this->kongAdminUri), $payload);
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
                        \sprintf('%s/certificates/%s', $this->kongAdminUri, $domain),
                        $payload
                    );
                } catch (ClientException|GuzzleException $patchException) {
                    $this->errors[] = new Error($patchException->getCode(), [$domain], $patchException->getMessage());
                }
            }
        }

        $certOrCerts = \count($certificate->getDomains()) > 1 ? 'Certificates' : 'Certificate';

        $this->output->writeln(\sprintf('%s for %s correctly sent to Kong', $certOrCerts, $outputDomains));

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
        $response = $ex->getResponse();
        switch (true) {
            case $response === null:
                return false;

            case $response->getStatusCode() === 409:
                return true;

            case $response->getStatusCode() === 400:
                $decoded = json_decode($response->getBody()->getContents());

                return \preg_match('/already associated with existing certificate/', $decoded->message ?? '') > 0;

            default:
                return false;
        }
    }
}
