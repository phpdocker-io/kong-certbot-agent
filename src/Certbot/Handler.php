<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Certbot;

use InvalidArgumentException;
use PhpDockerIo\KongCertbot\Certbot\Exception\CertFileNotFoundException;
use PhpDockerIo\KongCertbot\Certificate;
use RuntimeException;
use function file_exists;
use function file_get_contents;
use function reset;
use function sprintf;

/**
 * Runs certbot to acquire certificate files for the list of domains, and returns a list of Certificates.
 *
 * @author PHPDocker.io
 */
class Handler
{
    private const DEFAULT_CERTS_BASE_PATH = '/etc/letsencrypt/live';

    /** @var Error[] */
    private array $errors = [];

    private string $certsBasePath;

    public function __construct(private ShellExec $shellExec)
    {
        $this->certsBasePath = self::DEFAULT_CERTS_BASE_PATH;
    }

    public function setCertsBasePath(string $certsBasePath): self
    {
        $this->certsBasePath = $certsBasePath;

        return $this;
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
     * @return Certificate
     * @throws CertFileNotFoundException
     */
    public function acquireCertificate(array $domains, string $email, bool $testCert): Certificate
    {
        // Domains are stored by certbot in a folder named after the first domain on the list
        $firstDomain = reset($domains);

        if ($firstDomain === false) {
            throw new InvalidArgumentException('Empty list of domains provided');
        }

        $renewCmd = sprintf(
            'certbot certonly %s --agree-tos --standalone --preferred-challenges http -n -m %s --expand %s',
            $testCert ? '--test-cert' : '',
            $email,
            '-d ' . implode(' -d ', $domains)
        );

        $cmdStatus = $this->shellExec->exec($renewCmd);

        if ($cmdStatus === false) {
            $this->errors[] = new Error($this->shellExec->getOutput(), 1, $domains);
            throw new RuntimeException('Certbot execution failed');
        }

        $basePath = sprintf('%s/%s', $this->certsBasePath, $firstDomain);

        // Ensure certs have actually been created
        $fullChainPath = sprintf('%s/fullchain.pem', $basePath);
        if (file_exists($fullChainPath) === false) {
            throw new CertFileNotFoundException($fullChainPath, $domains);
        }

        $privateKeyPath = sprintf('%s/privkey.pem', $basePath);
        if (file_exists($privateKeyPath) === false) {
            throw new CertFileNotFoundException($privateKeyPath, $domains);
        }

        // Ensure certs are readable
        $fullChain  = file_get_contents($fullChainPath);
        $privateKey = file_get_contents($privateKeyPath);

        return new Certificate(
            $fullChain !== false ? $fullChain : '',
            $privateKey !== false ? $privateKey : '',
            $domains
        );
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
