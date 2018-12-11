<?php
declare(strict_types=1);

namespace Tests\PhpDockerIo\KongCertbot\Command;

use GuzzleHttp\ClientInterface;
use PhpDockerIo\KongCertbot\Certbot\ShellExec;
use PhpDockerIo\KongCertbot\Command\UpdateCertificatesCommand;
use PHPStan\Testing\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End to end functional test.
 *
 * Boundaries lay at shell exec and http client.
 */
class UpdateCertificatesCommandEndToEndTest extends TestCase
{
    private const CERTS_BASE_PATH = __DIR__ . '/fixtures';

    private const MAIN_DOMAIN = 'foo.bar';

    private const KONG_ENDPOINT = 'http://foo/bar';

    /**
     * @var ClientInterface|MockObject
     */
    private $httpClient;

    /**
     * @var ShellExec|MockObject
     */
    private $shellExec;

    /**
     * @var CommandTester
     */
    private $command;

    public function setUp()
    {
        parent::setUp();

        $this->httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();
        $this->shellExec  = $this->getMockBuilder(ShellExec::class)->disableOriginalConstructor()->getMock();

        $this->command = new CommandTester(new UpdateCertificatesCommand($this->httpClient, $this->shellExec, self::CERTS_BASE_PATH));
    }

    /**
     * @test
     */
    public function certificateIsCreatedSuccessfullyForOneDomain(): void
    {
        $email    = 'foo@bar';
        $domains  = self::MAIN_DOMAIN;
        $endpoint = self::KONG_ENDPOINT . '/certificates';

        $expectedCertbotCommand = sprintf(
            'certbot certonly  --agree-tos --standalone --preferred-challenges http -n -m %s --expand -d %s',
            $email,
            $domains
        );

        $this->shellExec
            ->expects(self::once())
            ->method('exec')
            ->with($expectedCertbotCommand)
            ->willReturn(true);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with('post', $endpoint)
            ->willReturn(true);

        $this->command->execute([
            'email'         => 'foo@bar',
            'domains'       => self::MAIN_DOMAIN,
            'kong-endpoint' => self::KONG_ENDPOINT,
        ]);
    }
}