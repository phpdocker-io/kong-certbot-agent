<?php
declare(strict_types=1);

namespace Tests\PhpDockerIo\KongCertbot;

use PhpDockerIo\KongCertbot\Certificate;
use PHPUnit\Framework\TestCase;

class CertificateTest extends TestCase
{
    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Empty cert
     * @dataProvider             emptyStringsDataProvider
     */
    public function constructorHandlesEmptyCert(string $emptyString): void
    {
        new Certificate($emptyString, 'foo', ['bar']);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Empty key
     * @dataProvider             emptyStringsDataProvider
     */
    public function constructorHandlesEmptyKey(string $emptyString): void
    {
        new Certificate('foo', $emptyString, ['bar']);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Empty domains
     */
    public function constructorHandlesEmptyListOfDomains(): void
    {
        new Certificate('bar', 'foo', []);
    }

    public function emptyStringsDataProvider(): array
    {
        return [
            [''],
            [' '],
            ['  '],
            [
                '
            ',
            ],
            ['     '],
        ];
    }
}
