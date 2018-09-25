<?php

namespace PhpDockerIo\KongCertbot;

use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Orchestrates Kong and Certbot to do their thing and acquire and store certs into Kong.
 *
 * @author PHPDocker.io
 */
class CertbotAgent
{
    /**
     * @var Kong\Handler
     */
    private $kong;

    /**
     * @var Certbot\Handler
     */
    private $certbot;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(Kong\Handler $kong, Certbot\Handler $certbot, OutputInterface $output)
    {
        $this->kong    = $kong;
        $this->certbot = $certbot;
    }

    public function execute(string $email, array $domains, bool $testCert): bool
    {
        $this->kong->store($this->certbot->acquireCertificates($domains, $email, $testCert));

        if (\count($this->kong->getErrors()) > 0 || \count($this->certbot->getErrors()) > 0) {
            $this->reportErrors($this->kong->getErrors(), $this->certbot->getErrors());
            return false;
        }

        return true;
    }

    /**
     * @todo
     *
     * @param ClientException[] $kongErrors
     * @param Certbot\Error[]   $certbotErrors
     */
    public function reportErrors(array $kongErrors, array $certbotErrors): void
    {
        $this->output->writeln('oops');
    }
}
