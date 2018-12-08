<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Certbot\Exception;

/**
 * Thrown when a certificate file exists, but could not be read for whatever reason.
 *
 * @author PHPDocker.io
 */
class InvalidCertFileException extends \RuntimeException
{
    public function __construct(string $file, array $domains)
    {
        $message = \sprintf(
            '%s could not be read for domains [%s] - this should not happen at all',
            $file,
            \implode(', ', $domains)
        );

        parent::__construct($message, 0);
    }
}
