<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot;

/**
 * Certificate data for a domain.
 */
class Certificate
{
    /**
     * @var string
     */
    private $cert;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string[]
     */
    private $domains;

    public function __construct(string $cert, string $key, array $domains)
    {
        if (\trim($cert) === '') {
            throw new \InvalidArgumentException('Empty cert');
        }

        if (\trim($key) === '') {
            throw new \InvalidArgumentException('Empty cert');
        }

        if (\count($domains) === 0) {
            throw new \InvalidArgumentException('Empty domains');
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
