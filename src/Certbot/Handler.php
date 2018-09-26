<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Certbot;

use PhpDockerIo\KongCertbot\Certificate;

/**
 * Runs certbot to acquire certificate files for the list of domains, and returns a list of Certificates.
 *
 * @author PHPDocker.io
 */
class Handler
{
    private const CERTS_BASE_PATH = '/etc/letsencrypt/live';

    /**
     * @var Error[]
     */
    private $errors = [];

    /**
     * Separate all domains by root domain, acquire certificates grouped per root domain and return.
     *
     * Gracefully handle certbot errors - we do not want not to update certs we did acquire successfully.
     *
     * @param string[] $domains
     * @param string   $email
     * @param bool     $testCert
     *
     * @return Certificate[]
     */
    public function acquireCertificates(array $domains, string $email, bool $testCert): array
    {
        $sortedDomains = $this->sortDomainsByRootDomain($domains);
        $certificates  = [];

        foreach ($sortedDomains as $rootDomain => $effectiveDomains) {
            $renewCmd = escapeshellcmd(sprintf(
                'certbot certonly %s --agree-tos --standalone --preferred-challenges http -n -m %s --expand %s',
                $testCert ? '--test-cert' : '',
                $email,
                '-d ' . implode(' -d ', $effectiveDomains)
            ));

            $cmdStatus = null;
            $cmdOutput = [];

            \exec($renewCmd, $cmdOutput, $cmdStatus);

            if ($cmdStatus !== 0) {
                $this->errors[] = Error($cmdOutput, $cmdStatus, $effectiveDomains);
            }

            $basePath = sprintf('%s/%s', self::CERTS_BASE_PATH, $rootDomain);

            $certificates[] = new Certificate(
                file_get_contents(sprintf('%s/fullchain.pem', $basePath)),
                file_get_contents(sprintf('%s/privkey.pem', $basePath)),
                $effectiveDomains
            );
        }

        return $certificates;
    }

    /**
     * Given a list of domains, determine their root domain and return as a list indexed by root domains.
     *
     * @todo
     *
     * @param array $domains
     *
     * @return array
     */
    private function sortDomainsByRootDomain(array $domains): array
    {
        return [];
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
