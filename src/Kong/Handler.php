<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Kong;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
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
     * Stores the given list of certificates grouped by root
     *
     * @param array $certificates
     *
     * @return bool
     */
    public function store(array $certificates): bool
    {
        foreach ($certificates as $certificate) {
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
            } catch (ClientException|GuzzleException $ex) {
                // 409 we can handle, certs for snis already exist and we just need to update them
                if ($ex->getCode() !== 409) {
                    $this->errors[] = $ex;
                    continue;
                }

                // Remove SNIs from PATCH as we will be patching into each PATCH individually
                unset($payload['form_params']['snis']);

                // @todo: use asynchronous here
                foreach ($certificate->getDomains() as $domain) {
                    try {
                        $this->guzzle->request(
                            'patch',
                            \sprintf('%s/certificates/%s', $this->kongAdminUri, $domain),
                            $payload
                        );
                    } catch (ClientException|GuzzleException $patchException) {
                        $this->errors[] = $ex;
                        continue;
                    }
                }
            }

            $this->output->writeln(\sprintf('Certificate for domain %s correctly sent to Kong', $outputDomains));
        }

        return !(\count($this->errors) > 0);
    }

    /**
     * @return ClientException[]|GuzzleException[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}