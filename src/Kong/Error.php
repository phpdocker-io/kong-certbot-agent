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
     * @var int
     */
    private int $code;

    /**
     * @var string[]
     */
    private array $domains;

    /**
     * @var string
     */
    private string $message;

    /**
     * @param int      $code
     * @param string[] $domains
     * @param string   $message
     */
    public function __construct(int $code, array $domains, string $message)
    {
        $this->code    = $code;
        $this->domains = $domains;
        $this->message = $message;
    }

    /**
     * @return int
     */
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

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
