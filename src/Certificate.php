<?php

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

    public function __construct(string $cert, string $key, array $domain)
    {
        $this->cert    = $cert;
        $this->key     = $key;
        $this->domains = $domain;
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