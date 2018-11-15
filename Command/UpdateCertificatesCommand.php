<?php

namespace PhpDockerIo\KongCertbot\Command;

use GuzzleHttp\Exception\ClientException;
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
    const CERTS_BASE_PATH = '/etc/letsencrypt/live';

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

        // Compose cerbot command & execute
        $renewCmd = escapeshellcmd(sprintf(
            'certbot certonly %s --agree-tos --standalone --preferred-challenges http -n -m %s --expand %s',
            $testCert ? '--test-cert' : '',
            $email,
            '-d ' . implode(' -d ', $domains)
        ));

        $cmdStatus = null;
        $cmdOutput = [];
        exec($renewCmd, $cmdOutput, $cmdStatus);

        if ($cmdStatus !== 0) {
            $output->writeln('Error when executing certbot');
            $output->write($cmdOutput);
            return $cmdStatus;
        }

        // Update kong admin with the new certificates foreach domain
        $guzzle = new \GuzzleHttp\Client();

        foreach ($domains as $domain) {
            $output->writeln(sprintf('Updating certificates config for %s', $domain));

            $basePath = sprintf('%s/%s', self::CERTS_BASE_PATH, $domain);
            $payload  = [
                'headers' => [
                    'accept' => 'application/json',
                ],
                'form_params' => [
                    'cert' => file_get_contents(sprintf('%s/fullchain.pem', $basePath)),
                    'key'  => file_get_contents(sprintf('%s/privkey.pem', $basePath)),
                    'snis[]' => $domain,
                ],
            ];

            // Unfortunately for us, PUT is not UPSERT
            try {
                $guzzle->post(sprintf('%s/certificates', $kongAdminUri), $payload);
            } catch (ClientException $ex) {
                if (!in_array($ex->getCode(), [400, 409])) {
                    throw $ex;
                }

                unset($payload['form_params']['snis']);
                $guzzle->patch(sprintf('%s/certificates/%s', $kongAdminUri, $domain), $payload);
            }

            $output->writeln(sprintf('Certificate for domain %s correctly sent to Kong', $domain));
        }

        $output->writeln(sprintf('%s certificates correctly sent to Kong', count($domains)));

        return 0;
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
