<?php

namespace PhpDockerIo\KongCertbot\Command;

use GuzzleHttp\ClientInterface as Guzzle;
use PhpDockerIo\KongCertbot\Certbot\Handler as Certbot;
use PhpDockerIo\KongCertbot\CertbotAgent;
use PhpDockerIo\KongCertbot\Kong\Handler as Kong;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Handles requesting certificates for a given list of domains off Let's Encrypt via certbot's standalone method - it
 * creates its own HTTP server which you need to ensure it's exposed so that LE's challenges work.
 *
 * Once certificates have been generated, this will update Kong via its admin API with the certificates for said
 * domains.
 *
 * @author PHPDocker.io
 */
class UpdateCertificatesCommand extends Command
{
    /**
     * @var Guzzle
     */
    private $guzzle;

    public function __construct(Guzzle $guzzle)
    {
        parent::__construct(null);

        $this->guzzle = $guzzle;
    }
    /**
     * Set the arguments and options required for the command line tool.
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
            ->setName('certs:update')
            ->setDescription('Requests certificates from Let\'s Encrypt for the given domains and notifies Kong')
            ->addArgument(
                'kong-endpoint',
                InputArgument::REQUIRED,
                'Base URL to Kong Admin API; eg: https://foo:8001'
            )
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Email the set of domains is to be associated with at Let\'s Encrypt'
            )
            ->addArgument(
                'domains',
                InputArgument::REQUIRED,
                'Comma separated list of domains to request certs for; eg: bar.com,foo.bar.com'
            )
            ->addOption(
                'test-cert',
                't',
                InputOption::VALUE_NONE,
                'Require test certificate from staging-letsencrypt'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email        = $input->getArgument('email');
        $kongAdminUri = $input->getArgument('kong-endpoint');
        $domains      = $this->parseDomains($input->getArgument('domains'));
        $testCert     = $input->getOption('test-cert');

        return (int) (new CertbotAgent(
            new Kong($kongAdminUri, $this->guzzle, $output),
            new Certbot(),
            $output
        ))->execute($domains, $email, $testCert);
    }

    /**
     * Parses the list of domains given from the command line, cleans it up and returns it as an array
     * of individual domains.
     *
     * @param string $domainsRaw
     *
     * @return string[]
     */
    private function parseDomains(string $domainsRaw): array
    {
        $domains = [];
        foreach (explode(',', $domainsRaw) as $domain) {
            if (empty($domain) === false) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }
}