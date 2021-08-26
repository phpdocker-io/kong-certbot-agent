<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Certbot;

use RuntimeException;
use function escapeshellcmd;
use function exec;

/**
 * Runs a command on the shell.
 *
 * @package PhpDockerIo\KongCertbot\Certbot
 * @codeCoverageIgnore
 */
class ShellExec
{
    /** @var string[] */
    private array $output = [];

    public function exec(string $command): bool
    {
        $cmdStatus = 1;

        exec(escapeshellcmd($command), $this->output, $cmdStatus);

        return $cmdStatus === 0;
    }

    /**
     * Returns the command output
     *
     * @return string[]
     */
    public function getOutput(): array
    {
        if ($this->output === []) {
            throw new RuntimeException('Command not yet run');
        }

        return $this->output;
    }
}
