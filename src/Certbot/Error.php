<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Certbot;

class Error
{
    /**
     * @var string[]
     */
    private array $cmdOutput;

    /**
     * @var int
     */
    private int $cmdStatus;

    /**
     * @var string[]
     */
    private array $domains;

    /**
     * @param string[] $cmdOutput
     * @param int      $cmdStatus
     * @param string[]    $domains
     */
    public function __construct(array $cmdOutput, int $cmdStatus, array $domains)
    {
        $this->cmdOutput = $cmdOutput;
        $this->cmdStatus = $cmdStatus;
        $this->domains   = $domains;
    }

    /**
     * @return string[]
     */
    public function getCmdOutput(): array
    {
        return $this->cmdOutput;
    }

    /**
     * @return int
     */
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
