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
    private const DEFAULT_CERTS_BASE_PATH = '/etc/letsencrypt/live';

    /**
     * @var Error[]
     */
    private $errors = [];

    /**
     * @var ShellExec
     */
    private $shellExec;

    /**
     * @var string
     */
    private $certsBasePath;

    public function __construct(ShellExec $shellExec, string $certsBasePath = null)
    {
        $this->shellExec     = $shellExec;
        $this->certsBasePath = $certsBasePath ?? self::DEFAULT_CERTS_BASE_PATH;
    }

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
        // Domains are stored by certbot in a folder named after the first domain on the list
        $certificates = [];

        $firstDomain = \reset($domains);

        if ($firstDomain === false) {
            throw new \InvalidArgumentException('Empty list of domains provided');
        }

        $renewCmd = \sprintf(
            'certbot certonly %s --agree-tos --standalone --preferred-challenges http -n -m %s --expand %s',
            $testCert ? '--test-cert' : '',
            $email,
            '-d ' . implode(' -d ', $domains)
        );

        $cmdStatus = $this->shellExec->exec($renewCmd);
        $cmdOutput = $this->shellExec->getOutput();

        if ($cmdStatus === false) {
            $this->errors[] = new Error($cmdOutput, 1, $domains);
            return $certificates;
        }

        $basePath = sprintf('%s/%s', $this->certsBasePath, $firstDomain);

        $certificates[] = new Certificate(
            \file_get_contents(\sprintf('%s/fullchain.pem', $basePath)),
            \file_get_contents(\sprintf('%s/privkey.pem', $basePath)),
            $domains
        );

        return $certificates;
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
