<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Certbot;

class Error
{
    /**
     * @param string[] $cmdOutput
     * @param string[] $domains
     */
    public function __construct(private array $cmdOutput, private int $cmdStatus, private array $domains)
    {
    }

    /**
     * @return string[]
     */
    public function getCmdOutput(): array
    {
        return $this->cmdOutput;
    }

    public function getCmdStatus(): int
    {
        return $this->cmdStatus;
    }

    /**
     * @return string[]
     */
    public function getDomains(): array
    {
        return $this->domains;
    }
}
