<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Time2Split\Help\Arrays;
use Time2Split\Help\TriState;
use Time2Split\PCP\FS\FS;

class FSPathTest extends TestCase
{
    private const DELIMITER = '/';

    public static function provideOf(): array
    {
        return [
            'src' => [
                'src',
                TriState::Maybe,
                TriState::Maybe,
            ],
            '/src' => [
                '/src',
                TriState::Yes,
                TriState::Maybe,
            ],
            'src/' => [
                'src/',
                TriState::Maybe,
                TriState::No,
            ],
            '/src/' => [
                '/src/',
                TriState::Yes,
                TriState::No,
            ],
            [
                'src/.',
                TriState::Maybe,
                TriState::No,
                'strexpect' => 'src/./',
            ],
            [
                'src/./',
                TriState::Maybe,
                TriState::No,
            ],
            [
                '/./src',
                TriState::Yes,
                TriState::Maybe,
            ],
            [
                './src',
                TriState::Maybe,
                TriState::Maybe,
            ],
            '/' => [
                '/',
                TriState::Yes,
                TriState::No,
            ],
            [
                '.',
                TriState::Maybe,
                TriState::No,
                'strexpect' => './',
            ],
            [
                './',
                TriState::Maybe,
                TriState::No,
            ],
            [
                '/./',
                TriState::Yes,
                TriState::No,
            ],
            [
                '/.',
                TriState::Yes,
                TriState::No,
                'strexpect' => '/./',
            ],
            [
                '..',
                TriState::Maybe,
                TriState::No,
                'strexpect' => '../',
            ],
            [
                '../',
                TriState::Maybe,
                TriState::No,
            ],
            [
                '/../',
                TriState::Yes,
                TriState::No,
            ],
            [
                '/..',
                TriState::Yes,
                TriState::No,
                'strexpect' => '/../',
            ],
        ];
    }

    #[DataProvider("provideOf")]
    public function testOf(
        string $strpath,
        TriState $isRoot = TriState::Maybe,
        TriState $isLeaf = TriState::Maybe,
        ?string $strexpect = null
    ) {
        $parts = FS::explode($strpath, self::DELIMITER);
        $path = FS::pathOf($parts);

        $this->assertSame($isRoot, $path->rooted(), 'root');
        $this->assertSame($isLeaf, $path->leafed(), 'leaf');

        $expect = $parts;

        if (Arrays::firstValue($expect) === '')
            \array_shift($expect);
        if (Arrays::lastValue($expect) === '')
            \array_pop($expect);

        $pathE = $path->toListOfElements();
        $this->assertSame($expect, $pathE);

        $this->assertSame(
            $strexpect ?? $strpath,
            "$path",
            'string'
        );
    }

    public function testOfNotString()
    {
        $this->expectException(\Error::class);
        FS::pathOf(['a', false]);
    }

    // ========================================================================

    static function provideFSPathCanon(): array
    {
        return [
            [
                './a',
                'a'
            ],
            [
                '/./a',
                '/a'
            ],
            [
                'a/.',
                'a/'
            ],
            [
                'a/./',
                'a/'
            ],
            [
                '../a',
                '../a'
            ],
            [
                '/../a',
                '/../a'
            ],
            [
                'a/b/..',
                'a/'
            ],
            [
                'a/b/../',
                'a/'
            ],
            [
                'a/../b',
                'b'
            ],
            [
                'a/../b/',
                'b/'
            ],
            [
                '/a/b/..',
                '/a/'
            ],
            [
                '/a/b/../',
                '/a/'
            ],
            [
                '/a/../b',
                '/b'
            ],
            [
                '/a/../b/',
                '/b/'
            ],
        ];
    }

    #[DataProvider("provideFSPathCanon")]
    public function testFSPathCanon(
        string $strPath,
        string $strCanon
    ) {
        $parts = FS::pathOf(FS::explode($strPath, self::DELIMITER));
        $path = FS::pathOf($parts);

        $this->assertSame($strCanon, (string)($path->canonical()));
    }
}
