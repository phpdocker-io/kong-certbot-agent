<?php
declare(strict_types=1);

namespace PhpDockerIo\KongCertbot\Command;

use JsonException;
use PhpDockerIo\KongCertbot\Certbot\Error as CertbotError;
use PhpDockerIo\KongCertbot\Certbot\Handler as Certbot;
use PhpDockerIo\KongCertbot\Kong\Error as KongError;
use PhpDockerIo\KongCertbot\Kong\Handler as Kong;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function count;
use function explode;
use function filter_var;
use function get_class;
use function implode;
use function json_encode;
use function sprintf;
use function trim;

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

    public function __construct(private Kong $kong, private Certbot $certbot, string $certsBasePath = null)
    {
        parent::__construct(self::COMMAND_NAME);

        if ($certsBasePath !== null) {
            $this->certbot->setCertsBasePath($certsBasePath);
        }
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
     * @throws JsonException
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

        $outputDomains = implode(', ', $domains);

        /** @var bool $testCert */
        $testCert = $input->getOption('test-cert');

        $this->validateInput($email, $kongAdminUri, $domains);

        // Acquire certificates from certbot. This is not all-or-nothing, whatever certs we acquire come out here
        // and we defer error handling until they're stored
        try {
            $output->writeln(sprintf('Updating certificates config for %s', $outputDomains));
            $certificate = $this->certbot->acquireCertificate($domains, $email, $testCert);

            // Store certs into kong via the admin UI. Again, not all-or-nothing
            if ($this->kong->store($certificate, $kongAdminUri) === true) {
                $certOrCerts = count($certificate->getDomains()) > 1 ? 'Certificates' : 'Certificate';

                $output->writeln(sprintf('%s for %s correctly sent to Kong', $certOrCerts, $outputDomains));
            }
        } catch (Throwable $ex) {
            // If no errors listed, unhandled exception
            if (count($this->kong->getErrors()) === 0 && count($this->certbot->getErrors()) === 0) {
                $output->writeln(sprintf(
                    'Unexpected error %s - %s',
                    get_class($ex),
                    $ex->getMessage()
                ));

                return 1;
            }
        }

        // Capture errors for reporting - some certs might have succeeded, but we do need to
        // exit appropriately for whatever orchestrator to realise there were problems
        if (count($this->kong->getErrors()) > 0 || count($this->certbot->getErrors()) > 0) {
            $this->reportErrors($this->kong->getErrors(), $this->certbot->getErrors(), $output);

            return 1;
        }

        return 0;
    }

    /**
     * Parses the list of domains given from the command line, cleans it up and returns it as an array
     * of individual domains.
     *
     * @return string[]
     */
    private function parseDomains(string $concatDomains): array
    {
        $domains = [];
        foreach (explode(',', $concatDomains) as $domain) {
            $domain = trim($domain);
            if (empty($domain) === false) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * List kong and certbot errors.
     *
     * @param KongError[]    $kongErrors
     * @param CertbotError[] $certbotErrors
     *
     * @throws JsonException
     */
    private function reportErrors(array $kongErrors, array $certbotErrors, OutputInterface $output): void
    {
        foreach ($kongErrors as $kongError) {
            $output->writeln(sprintf(
                'Kong error: code %s, message %s, domains %s',
                $kongError->getCode(),
                $kongError->getMessage(),
                implode(', ', $kongError->getDomains())
            ));
        }

        foreach ($certbotErrors as $certbotError) {
            $output->writeln(sprintf(
                'Certbot error: command status %s, output `%s`, domains %s',
                $certbotError->getCmdStatus(),
                json_encode($certbotError->getCmdOutput(), JSON_THROW_ON_ERROR),
                implode(', ', $certbotError->getDomains())
            ));
        }
    }

    /**
     * Validates user input
     *
     * @param string[] $domains
     *
     * @throws \InvalidArgumentException
     */
    private function validateInput(string $email, string $kongAdminUri, array $domains): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== $email) {
            throw new \InvalidArgumentException(sprintf('Invalid email %s', $email));
        }

        if (filter_var($kongAdminUri, FILTER_VALIDATE_URL) !== $kongAdminUri) {
            throw new \InvalidArgumentException(sprintf('Invalid kong admin endpoint %s', $kongAdminUri));
        }

        if (count($domains) === 0) {
            throw new \InvalidArgumentException('Empty list of domains given - expect comma-separated');
        }
    }
}
