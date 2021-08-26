<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Kong;

/**
 * Describes an error found while talking to Kong.
 *
 * @author PHPDocker.io
 */
class Error
{
    /**
     * @param string[] $domains
     */
    public function __construct(private int $code, private array $domains, private string $message)
    {
    }

    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string[]
     */
    public function getDomains(): array
    {
        return $this->domains;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
