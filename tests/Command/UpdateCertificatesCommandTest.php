<?php
declare(strict_types=1);

namespace Tests\PhpDockerIo\KongCertbot\Command;

use PhpDockerIo\KongCertbot\Certbot\Error as CertbotError;
use PhpDockerIo\KongCertbot\Certbot\Exception\CertFileNotFoundException;
use PhpDockerIo\KongCertbot\Certbot\Handler as Certbot;
use PhpDockerIo\KongCertbot\Certificate;
use PhpDockerIo\KongCertbot\Command\UpdateCertificatesCommand;
use PhpDockerIo\KongCertbot\Kong\Error as KongError;
use PhpDockerIo\KongCertbot\Kong\Handler as Kong;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateCertificatesCommandTest extends TestCase
{
    /**
     * @var Kong|MockObject
     */
    private $kong;

    /**
     * @var Certbot|MockObject
     */
    private $certbot;

    /**
     * @var CommandTester
     */
    private $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->kong    = $this->getMockBuilder(Kong::class)->disableOriginalConstructor()->getMock();
        $this->certbot = $this->getMockBuilder(Certbot::class)->disableOriginalConstructor()->getMock();

        $this->command = new CommandTester(new UpdateCertificatesCommand($this->kong, $this->certbot));
    }

    /**
     * @test
     */
    public function executeSucceeds(): void
    {
        $domains = ['foo.bar', 'bar.foo'];
        $email   = 'bar@foo.com';
        $cert    = new Certificate('foo', 'bar', $domains);

        $this->certbot
            ->expects(self::once())
            ->method('acquireCertificate')
            ->with($domains, $email, false)
            ->willReturn($cert);

        $this->kong
            ->expects(self::once())
            ->method('store')
            ->with($cert)
            ->willReturn(true);

        self::assertSame(0, $this->command->execute([
            'email'         => $email,
            'domains'       => \implode(',', $domains),
            'kong-endpoint' => 'http://foobar',
        ]));

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Updating certificates config for foo.bar, bar.foo', $output);
        self::assertStringContainsString('Certificates for foo.bar, bar.foo correctly sent to Kong', $output);
    }

    /**
     * @test
     */
    public function executeSucceedsWithSlightliWonkyListOfDomains(): void
    {
        $domains = ['foo.bar', 'bar.foo'];
        $email   = 'bar@foo.com';
        $cert    = new Certificate('foo', 'bar', $domains);

        // We have some filtering to avoid weird domain situations
        $inputDomains = 'foo.bar,,bar.foo';

        $this->certbot
            ->expects(self::once())
            ->method('acquireCertificate')
            ->with($domains, $email, false)
            ->willReturn($cert);

        $this->kong
            ->expects(self::once())
            ->method('store')
            ->with($cert)
            ->willReturn(true);

        self::assertSame(0, $this->command->execute([
            'email'         => $email,
            'domains'       => $inputDomains,
            'kong-endpoint' => 'http://foobar',
        ]));

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Updating certificates config for foo.bar, bar.foo', $output);
        self::assertStringContainsString('Certificates for foo.bar, bar.foo correctly sent to Kong', $output);
    }

    /**
     * @test
     */
    public function executeHandlesKongErrors(): void
    {
        $domains = ['foo.bar', 'bar.foo'];
        $email   = 'bar@foo.com';
        $cert    = new Certificate('foo', 'bar', $domains);

        $kongErrors = [
            new KongError(1, $domains, 'bar'),
        ];

        $this->certbot
            ->expects(self::once())
            ->method('acquireCertificate')
            ->with($domains, $email, false)
            ->willReturn($cert);

        $this->kong
            ->expects(self::once())
            ->method('store')
            ->with($cert)
            ->willReturn(false);

        $this->kong
            ->expects(self::any())
            ->method('getErrors')
            ->willReturn($kongErrors);

        self::assertSame(1, $this->command->execute([
            'email'         => $email,
            'domains'       => \implode(',', $domains),
            'kong-endpoint' => 'http://foobar',
        ]));

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Updating certificates config for foo.bar, bar.foo', $output);
        self::assertStringNotContainsString('Certificates for foo.bar, bar.foo correctly sent to Kong', $output);
        self::assertStringContainsString('Kong error: code 1, message bar, domains foo.bar, bar.foo', $output);
    }

    /**
     * @test
     */
    public function executeHandlesCertbotErrors(): void
    {
        $domains = ['foo.bar', 'bar.foo'];
        $email   = 'bar@foo.com';

        $certbotErrors = [
            new CertbotError(['doom'], 1, $domains),
        ];

        $this->certbot
            ->expects(self::once())
            ->method('acquireCertificate')
            ->with($domains, $email, false)
            ->willThrowException(new \RuntimeException());

        $this->certbot
            ->expects(self::any())
            ->method('getErrors')
            ->willReturn($certbotErrors);

        $this->kong
            ->expects(self::never())
            ->method('store');

        self::assertSame(1, $this->command->execute([
            'email'         => $email,
            'domains'       => \implode(',', $domains),
            'kong-endpoint' => 'http://foobar',
        ]));

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Updating certificates config for foo.bar, bar.foo', $output);
        self::assertStringNotContainsString('Certificates for foo.bar, bar.foo correctly sent to Kong', $output);
        self::assertStringContainsString('Certbot error: command status 1, output `["doom"]`, domains foo.bar, bar.foo', $output);
    }

    /**
     * @test
     */
    public function executeHandlesCertbotCertNotFoundExceptions(): void
    {
        $domains = ['foo.bar', 'bar.foo'];
        $email   = 'bar@foo.com';

        $expectedException = new CertFileNotFoundException('foo', $domains);

        $this->certbot
            ->expects(self::once())
            ->method('acquireCertificate')
            ->with($domains, $email, false)
            ->willThrowException($expectedException);

        $this->certbot
            ->expects(self::any())
            ->method('getErrors')
            ->willReturn([]);

        $this->kong
            ->expects(self::never())
            ->method('store');

        self::assertSame(1, $this->command->execute([
            'email'         => $email,
            'domains'       => \implode(',', $domains),
            'kong-endpoint' => 'http://foobar',
        ]));

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Updating certificates config for foo.bar, bar.foo', $output);
        self::assertStringNotContainsString('Certificates for foo.bar, bar.foo correctly sent to Kong', $output);
        self::assertStringContainsString(CertFileNotFoundException::class, $output);
    }

    /**
     * @test
     *
     * @dataProvider invalidEmailsDataProvider
     */
    public function executeFailsOnInvalidEmail(string $invalidEmail): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        self::assertSame(1, $this->command->execute([
            'email'         => $invalidEmail,
            'domains'       => 'foo.bar',
            'kong-endpoint' => 'http://foobar',
        ]));
    }

    public function invalidEmailsDataProvider(): array
    {
        return [
            ['5'],
            ['foo@Bar'],
            ['http://lalala.com'],
            [''],
        ];
    }

    /**
     * @test
     *
     * @dataProvider invalidKongEndpointsDataProvider
     */
    public function executeFailsOnInvalidKongAdminUri(string $invalidKongEndpoint): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid kong admin endpoint');

        self::assertSame(1, $this->command->execute([
            'email'         => 'foo@Bar.com',
            'domains'       => 'foo.bar',
            'kong-endpoint' => $invalidKongEndpoint,
        ]));
    }

    public function invalidKongEndpointsDataProvider(): array
    {
        return [
            ['5'],
            ['foo@Bar'],
            ['lalala.com'],
            [''],
        ];
    }

    /**
     * @test
     *
     * @dataProvider invalidListOfDomains
     */
    public function executeFailsOnInvalidListOfDomains(string $domains, string $expectedExceptionMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        self::assertSame(1, $this->command->execute([
            'email'         => 'foo@Bar.com',
            'domains'       => $domains,
            'kong-endpoint' => 'http://foo/bar',
        ]));
    }

    public function invalidListOfDomains(): array
    {
        return [
            ['', 'Empty list of domains given'],
            ['  ,', 'Empty list of domains given'],
        ];
    }
}
