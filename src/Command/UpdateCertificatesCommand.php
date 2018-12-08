<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Command;

use GuzzleHttp\ClientInterface as Guzzle;
use PhpDockerIo\KongCertbot\Certbot\Error as CertbotError;
use PhpDockerIo\KongCertbot\Certbot\Handler as Certbot;
use PhpDockerIo\KongCertbot\Certbot\ShellExec;
use PhpDockerIo\KongCertbot\Kong\Error as KongError;
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
    private const COMMAND_NAME = 'certs:update';

    /**
     * @var Guzzle
     */
    private $guzzle;

    /**
     * @var ShellExec
     */
    private $shellExec;

    public function __construct(Guzzle $guzzle, ShellExec $shellExec)
    {
        parent::__construct(self::COMMAND_NAME);

        $this->guzzle    = $guzzle;
        $this->shellExec = $shellExec;
    }
    /**
     * Set the arguments and options required for the command line tool.
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
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
        // Parse input

        /** @var string $email */
        $email = $input->getArgument('email');

        /** @var string $kongAdminUri */
        $kongAdminUri = $input->getArgument('kong-endpoint');

        /** @var string $concatDomains */
        $concatDomains = $input->getArgument('domains');

        $domains = $this->parseDomains($concatDomains);

        /** @var bool $testCert */
        $testCert = $input->getOption('test-cert');

        $this->validateInput($email, $kongAdminUri, $domains, $testCert);

        // Spawn kong and certbot handlers with config and dependencies
        $kong    = new Kong($kongAdminUri, $this->guzzle, $output);
        $certbot = new Certbot($this->shellExec);

        // Acquire certificates from certbot. This is not all-or-nothing, whatever certs we acquire come out here
        // and we defer error handling until they're stored
        $certificates = $certbot->acquireCertificate($domains, $email, $testCert);

        // Store certs into kong via the admin UI. Again, not all-or-nothing
        $kong->store($certificates);

        // Capture errors for reporting - some certs might have succeeded, but we do need to
        // exit appropriately for whatever orchestrator to realise there were problems
        if (\count($kong->getErrors()) > 0 || \count($certbot->getErrors()) > 0) {
            $this->reportErrors($kong->getErrors(), $certbot->getErrors(), $output);
            return 1;
        }

        return 0;
    }

    /**
     * Parses the list of domains given from the command line, cleans it up and returns it as an array
     * of individual domains.
     *
     * @param string $concatDomains
     *
     * @return string[]
     */
    private function parseDomains(string $concatDomains): array
    {
        $domains = [];
        foreach (\explode(',', $concatDomains) as $domain) {
            if (empty($domain) === false) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * @todo
     *
     * @param KongError[]     $kongErrors
     * @param CertbotError[]  $certbotErrors
     * @param OutputInterface $output
     */
    private function reportErrors(array $kongErrors, array $certbotErrors, OutputInterface $output): void
    {
        $output->writeln('oops');
    }

    /**
     * @todo
     *
     * @param string $email
     * @param string $kongAdminUri
     * @param array  $domains
     * @param bool   $testCert
     */
    private function validateInput(string $email, string $kongAdminUri, array $domains, bool $testCert): void
    {
    }
}
