<?php
declare(strict_types=1);


namespace Tests\PhpDockerIo\KongCertbot\Certbot;

use PhpDockerIo\KongCertbot\Certbot\Error;
use PhpDockerIo\KongCertbot\Certbot\Handler;
use PhpDockerIo\KongCertbot\Certbot\ShellExec;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HandlerTest extends TestCase
{
    /**
     * @var ShellExec|MockObject
     */
    private $shellExec;

    /**
     * @var Handler
     */
    private $handler;

    private $certsBasePath = __DIR__ . '/fixtures';

    public function setUp()
    {
        parent::setUp();

        $this->shellExec = $this->getMockBuilder(ShellExec::class)->getMock();

        $this->handler = new Handler($this->shellExec, $this->certsBasePath);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function acquireCertificatesHandlesEmptyListOfDomains(): void
    {
        $this->handler->acquireCertificates([], 'foo', true);
    }

    /**
     * @test
     * @dataProvider booleanDataProvider
     */
    public function acquireCertificatesHandlesCerbotError(bool $testCert): void
    {
        $domains = ['foo.bar'];
        $email = 'foo@bar';

        $execOutput = ['doom'];

        $expectedErrors = [
            new Error($execOutput, 1, $domains),
        ];

        $this->shellExec
            ->expects(self::once())
            ->method('exec')
            ->willReturn(false);

        $this->shellExec
            ->expects(self::once())
            ->method('getOutput')
            ->willReturn($execOutput);

        self::assertEquals([], $this->handler->acquireCertificates($domains, $email, $testCert));
        self::assertEquals($expectedErrors, $this->handler->getErrors());
    }

    /**
     * @test
     * @dataProvider booleanDataProvider
     */
    public function acquireCertificatesSucceedsWithTestCert(bool $testCert): void
    {
        $domains = ['foo.bar', 'lala.foo.bar'];
        $email = 'foo@bar';

        $execOutput = ['doom'];

        $this->shellExec
            ->expects(self::once())
            ->method('exec')
            ->willReturn(true);

        $this->shellExec
            ->expects(self::never())
            ->method('getOutput');

        self::assertEquals([], $this->handler->acquireCertificates($domains, $email, $testCert));
        self::assertEquals($expectedErrors, $this->handler->getErrors());
    }

    public function booleanDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }
}