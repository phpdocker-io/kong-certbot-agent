<?php
declare(strict_types=1);

namespace Tests\PhpDockerIo\KongCertbot\Kong;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use PhpDockerIo\KongCertbot\Certificate;
use PhpDockerIo\KongCertbot\Kong\Handler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HandlerTest extends TestCase
{
    private const KONG_ADMIN_URI = 'http://foo/bar';

    /**
     * @var Handler
     */
    private $handler;

    /**
     * @var Client|MockObject
     */
    private $httpClient;

    public function setUp()
    {
        parent::setUp();

        /** @var OutputInterface|MockObject $output */
        $output = $this->getMockBuilder(OutputInterface::class)->getMock();

        $this->httpClient = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();

        $this->handler = new Handler(self::KONG_ADMIN_URI, $this->httpClient, $output);
    }

    /**
     * @test
     */
    public function storeSucceedsWithOneDomain(): void
    {
        $certificate = new Certificate('foo', 'bar', ['foo.bar']);

        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with('post', self::KONG_ADMIN_URI . '/certificates', [
                'headers'     => [
                    'accept' => 'application/json',
                ],
                'form_params' => [
                    'cert'   => 'foo',
                    'key'    => 'bar',
                    'snis[]' => ['foo.bar'],
                ],
            ])
            ->willReturn($response);

        self::assertTrue($this->handler->store($certificate));
        self::assertEmpty($this->handler->getErrors());
    }

    /**
     * @test
     */
    public function storeSucceedsWithMultipleDomains(): void
    {
        $certificate = new Certificate('foo', 'bar', ['foo.bar', 'bar.foo', 'doom.bar']);

        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with('post', self::KONG_ADMIN_URI . '/certificates', [
                'headers'     => [
                    'accept' => 'application/json',
                ],
                'form_params' => [
                    'cert'   => 'foo',
                    'key'    => 'bar',
                    'snis[]' => ['foo.bar', 'bar.foo', 'doom.bar'],
                ],
            ])
            ->willReturn($response);

        self::assertTrue($this->handler->store($certificate));
        self::assertEmpty($this->handler->getErrors());
    }

    /**
     * @test
     * @dataProvider unknownKongErrors
     */
    public function storeHandlesUnknownKongError(int $statusCode): void
    {
        $certificate = new Certificate('foo', 'bar', ['foo.bar', 'bar.foo', 'doom.bar']);

        $response  = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $body      = $this->getMockBuilder(StreamInterface::class)->getMock();
        $exception = $this->getMockBuilder(ClientException::class)->disableOriginalConstructor()->getMock();

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->willThrowException($exception);

        $exception
            ->expects(self::any())
            ->method('getResponse')
            ->willReturn($response);

        $response
            ->expects(self::any())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $response
            ->expects(self::any())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::any())
            ->method('rewind');

        $body
            ->expects(self::any())
            ->method('getContents')
            ->willReturn(\json_encode(['foo']));

        self::assertFalse($this->handler->store($certificate));
        self::assertEquals([$exception], $this->handler->getErrors());
    }

    public function unknownKongErrors(): array
    {
        return [
            [400],
            [500],
        ];
    }

    /**
     * @test
     */
    public function storeHadlesKongEmptyResponse(): void
    {
        $certificate = new Certificate('foo', 'bar', ['foo.bar', 'bar.foo', 'doom.bar']);

        $exception = $this->getMockBuilder(ClientException::class)->disableOriginalConstructor()->getMock();

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->willThrowException($exception);

        $exception
            ->expects(self::any())
            ->method('getResponse')
            ->willReturn(null);

        self::assertFalse($this->handler->store($certificate));
        self::assertEquals([$exception], $this->handler->getErrors());
    }

    /**
     * @test
     */
    public function storeHandlesHttpConflictInOneDomain(): void
    {
        $certificate = new Certificate('foo', 'bar', ['foo.bar']);

        $response  = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $exception = $this->getMockBuilder(ClientException::class)->disableOriginalConstructor()->getMock();

        $this->httpClient
            ->expects(self::at(0))
            ->method('request')
            ->with('post')
            ->willThrowException($exception);

        $this->httpClient
            ->expects(self::at(1))
            ->method('request')
            ->with('patch', self::KONG_ADMIN_URI . '/certificates/foo.bar', [
                'headers'     => [
                    'accept' => 'application/json',
                ],
                'form_params' => [
                    'cert' => 'foo',
                    'key'  => 'bar',
                ],
            ])
            ->willReturn($response);

        $exception
            ->expects(self::any())
            ->method('getResponse')
            ->willReturn($response);

        $response
            ->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(409);

        self::assertTrue($this->handler->store($certificate));
        self::assertEmpty($this->handler->getErrors());
    }

    /**
     * @test
     */
    public function storeHandlesBadRequestConflictInOneDomain(): void
    {
        $certificate = new Certificate('foo', 'bar', ['foo.bar']);

        $response  = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $body      = $this->getMockBuilder(StreamInterface::class)->getMock();
        $exception = $this->getMockBuilder(ClientException::class)->disableOriginalConstructor()->getMock();

        $this->httpClient
            ->expects(self::at(0))
            ->method('request')
            ->with('post')
            ->willThrowException($exception);

        $this->httpClient
            ->expects(self::at(1))
            ->method('request')
            ->with('patch', self::KONG_ADMIN_URI . '/certificates/foo.bar', [
                'headers'     => [
                    'accept' => 'application/json',
                ],
                'form_params' => [
                    'cert' => 'foo',
                    'key'  => 'bar',
                ],
            ])
            ->willReturn($response);

        $exception
            ->expects(self::any())
            ->method('getResponse')
            ->willReturn($response);

        $response
            ->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(400);

        $response
            ->expects(self::any())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::any())
            ->method('rewind');

        $json = <<<JSON
{
    "strategy": "postgres",
    "message": "schema violation (snis: foo.bar already associated with existing certificate '9f62c9f4-f80c-11e8-8786-bb62c1494a3b')",
    "name": "schema violation",
    "fields": {
        "snis": "foo.bar already associated with existing certificate '9f62c9f4-f80c-11e8-8786-bb62c1494a3b'"
    },
    "code": 2
}
JSON;

        $body
            ->expects(self::any())
            ->method('getContents')
            ->willReturn($json);

        self::assertTrue($this->handler->store($certificate));
        self::assertEmpty($this->handler->getErrors());
    }

    /**
     * @test
     */
    public function storeHandlesHttpConflictInSeveralDomains(): void
    {
        $domains     = ['foo.bar', 'bar.foo'];
        $certificate = new Certificate('foo', 'bar', $domains);

        $response  = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $exception = $this->getMockBuilder(ClientException::class)->disableOriginalConstructor()->getMock();

        $this->httpClient
            ->expects(self::at(0))
            ->method('request')
            ->with('post')
            ->willThrowException($exception);

        foreach ($domains as $key => $domain) {
            $this->httpClient
                ->expects(self::at($key + 1))
                ->method('request')
                ->with('patch', self::KONG_ADMIN_URI . '/certificates/' . $domain, [
                    'headers'     => [
                        'accept' => 'application/json',
                    ],
                    'form_params' => [
                        'cert' => 'foo',
                        'key'  => 'bar',
                    ],
                ])
                ->willReturn($response);
        }

        $exception
            ->expects(self::any())
            ->method('getResponse')
            ->willReturn($response);

        $response
            ->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(409);

        self::assertTrue($this->handler->store($certificate));
        self::assertEmpty($this->handler->getErrors());
    }

    /**
     * @test
     */
    public function storeHandlesHttpExceptionOnPatch(): void
    {
        $certificate = new Certificate('foo', 'bar', ['foo.bar']);

        $response         = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $exception        = $this->getMockBuilder(ClientException::class)->disableOriginalConstructor()->getMock();
        $anotherException = $this->getMockBuilder(ClientException::class)->disableOriginalConstructor()->getMock();

        $this->httpClient
            ->expects(self::at(0))
            ->method('request')
            ->with('post')
            ->willThrowException($exception);

        $exception
            ->expects(self::any())
            ->method('getResponse')
            ->willReturn($response);

        $response
            ->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(409);

        $this->httpClient
            ->expects(self::at(1))
            ->method('request')
            ->with('patch')
            ->willThrowException($anotherException);

        self::assertFalse($this->handler->store($certificate));
        self::assertEquals([$anotherException], $this->handler->getErrors());
    }
}
