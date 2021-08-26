<?php
declare(strict_types=1);

namespace Tests\PhpDockerIo\KongCertbot;

use InvalidArgumentException;
use PhpDockerIo\KongCertbot\Certificate;
use PHPUnit\Framework\TestCase;

class CertificateTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider emptyStringsDataProvider
     */
    public function constructorHandlesEmptyCert(string $emptyString): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty cert');

        new Certificate($emptyString, 'foo', ['bar']);
    }

    /**
     * @test
     *
     * @dataProvider emptyStringsDataProvider
     */
    public function constructorHandlesEmptyKey(string $emptyString): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty key');

        new Certificate('foo', $emptyString, ['bar']);
    }

    /**
     * @test
     */
    public function constructorHandlesEmptyListOfDomains(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty domains');

        new Certificate('bar', 'foo', []);
    }

    public function emptyStringsDataProvider(): array
    {
        return [
            'empty string' => [''],
            'space'        => [' '],
            'tab'          => ['  '],
            'newline'      => [
                '
            ',
            ],
            'many spaces' => ['     '],
        ];
    }
}
