<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Time2Split\PCP\Help\HelpFilePath;

final class HelpFilePathTest extends TestCase
{

    public static function canonicalProvider(): \Traversable
    {
        $data = [
            ['/a', '/a'],
            ['a', 'a'],
            ['a/', 'a/'],
            ['/a/', '/a/'],

            ['.', ''],
            ['./', ''],
            ['/.', '/'],
            ['/./', '/'],

            ['/././', '/'],
            ['././', ''],

            ['./a', 'a'],
            ['/./a', '/a'],
            ['a/./', 'a/'],

            ['..', '..'],
            ['../', '../'],
            ['/..', '/..'],
            ['/../', '/../'],

            ['/a/b/c', '/a/b/c'],

            ['/a/b/c/.', '/a/b/c'],
            ['/a/b/./c', '/a/b/c'],

            ['/a/b/../c', '/a/c'],
            ['/../a/b/../c/.', '/../a/c'],
            ['/../a/b/../c/./', '/../a/c/'],
        ];

        return (function () use ($data) {
            foreach ($data as [$dir, $expect])
                yield $dir => [$dir, $expect];
        })();
    }

    #[Test]
    #[DataProvider("canonicalProvider")]
    public function canonical(string $path, string $expect)
    {
        $this->assertSame($expect, HelpFilePath::canonical($path));
    }
}
