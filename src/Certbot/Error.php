<?php

namespace PhpDockerIo\KongCertbot\Certbot;

class Error
{
    /**
     * @var array
     */
    private $cmdOutput;

    /**
     * @var int
     */
    private $cmdStatus;

    /**
     * @var array
     */
    private $domains;

    /**
     * @param string[] $cmdOutput
     * @param int      $cmdStatus
     * @param array    $domains
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
     * @return array
     */
    public function getDomains(): array
    {
        return $this->domains;
    }
}