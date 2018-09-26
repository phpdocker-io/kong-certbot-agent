<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot;

use GuzzleHttp\Exception\ClientException;
use PhpDockerIo\KongCertbot\Certbot\Handler as Certbot;
use PhpDockerIo\KongCertbot\Kong\Handler as Kong;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Orchestrates Kong and Certbot to do their thing and acquire and store certs into Kong.
 *
 * @author PHPDocker.io
 */
class CertbotAgent
{
    /**
     * @var Kong
     */
    private $kong;

    /**
     * @var Certbot
     */
    private $certbot;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(Kong $kong, Certbot $certbot, OutputInterface $output)
    {
        $this->kong    = $kong;
        $this->certbot = $certbot;
    }

    /**
     * Reaches out to Let's Encrypt for certificates for the given list of $domains belonging to $email and stores
     * them into Kong.
     *
     * @param array  $domains  List of domains to acquire certs for
     * @param string $email    Email these domains belong to at Let's Encrypt
     * @param bool   $testCert Whether to acquire test certificates instead of the real deal
     *
     * @return bool
     */
    public function execute(array $domains, string $email, bool $testCert): bool
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
