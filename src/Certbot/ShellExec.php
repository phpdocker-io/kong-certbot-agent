<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Certbot;

/**
 * Runs a command on the shell.
 *
 * @package PhpDockerIo\KongCertbot\Certbot
 * @codeCoverageIgnore
 */
class ShellExec
{
    /**
     * @var array
     */
    private $output;

    public function exec(string $command): bool
    {
        $cmdStatus = 1;

        \exec(\escapeshellcmd($command), $this->output, $cmdStatus);

        return $cmdStatus === 0;
    }

    /**
     * Returns the command output
     *
     * @return array
     */
    public function getOutput(): array
    {
        if ($this->output === null) {
            throw new \RuntimeException('Command not yet run');
        }

        return $this->output;
    }
}
