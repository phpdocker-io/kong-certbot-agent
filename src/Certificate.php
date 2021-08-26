<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot;

use InvalidArgumentException;
use function count;
use function trim;

/**
 * Certificate data for a domain.
 */
class Certificate
{
    /**
     * @var string
     */
    private string $cert;

    /**
     * @var string
     */
    private string $key;

    /**
     * @var string[]
     */
    private array $domains;

    /**
     * @param string $cert
     * @param string $key
     * @param string[]  $domains
     */
    public function __construct(string $cert, string $key, array $domains)
    {
        if (trim($cert) === '') {
            throw new InvalidArgumentException('Empty cert');
        }

        if (trim($key) === '') {
            throw new InvalidArgumentException('Empty key');
        }

        if (count($domains) === 0) {
            throw new InvalidArgumentException('Empty domains');
        }

        $this->cert    = $cert;
        $this->key     = $key;
        $this->domains = $domains;
    }

    /**
     * @return string
     */
    public function getCert(): string
    {
        return $this->cert;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string[]
     */
    public function getDomains(): array
    {
        return $this->domains;
    }
}
