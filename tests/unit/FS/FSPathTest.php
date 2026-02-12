<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Time2Split\Help\Arrays;
use Time2Split\Help\TriState;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSPath;

final class FSPathTest extends TestCase
{
    private const DELIMITER = '/';

    private static function pathOf(string $strPath): FSPath
    {
        return FS::pathOf(FS::explode($strPath, self::DELIMITER));
    }

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
        $path = self::pathOf($strPath);
        $this->assertSame($strCanon, (string)($path->canonical()));
    }

    // ========================================================================

    static function provideAppend(): array
    {
        return [
            ['a', 'b', 'a/b'],
            ['/a', 'b', '/a/b'],
            ['a', 'b/', 'a/b/'],
            ['/a', 'b/', '/a/b/'],
        ];
    }

    #[DataProvider("provideAppend")]
    public function testAppend(
        string $strPatha,
        string $strPathb,
        string $strExpect,
    ) {
        $patha =  self::pathOf($strPatha);
        $pathb =  self::pathOf($strPathb);
        $expect = self::pathOf($strExpect);

        $this->assertSame("$expect", (string)$patha->append($pathb));
    }

    // ========================================================================

    static function providePrefix(): array
    {
        return [
            ['', '', true, ''],
            ['a', '', true, 'a'],
            ['a', 'a', true, ''],
            ['a', 'a/', true, ''],
            ['a/', 'a', true, ''],

            ['a/b', 'a', true, 'b'],
            ['a/b', 'a/', true, 'b'],
            ['a/b/', 'a', true, 'b/'],
            ['a/b/', 'a/', true, 'b/'],
            ['a/b', 'a/b', true, ''],

            ['a/b/c', 'a/', true, 'b/c'],
            ['a/b/c', 'a/b', true, 'c'],
            ['a/b/c', 'a/b/c', true, ''],

            // false Prefix
            ['', 'x', false],
            ['a', 'x', false],
            ['a', 'a/x', false],
            ['a', 'x/a', false],

            ['a/b', 'x/b', false],
            ['a/b', 'a/x', false],
            ['a/b', 'a/b/x', false],
        ];
    }

    #[DataProvider("providePrefix")]
    public function testPrefix(
        string $strPath,
        string $strPrefix,
        bool $isPrefix,
        ?string $strRemovedPrefix = null
    ) {
        $fsPath =   self::pathOf($strPath);
        $fsPrefix = self::pathOf($strPrefix);

        $this->assertSame($isPrefix, $fsPath->hasPrefix($fsPrefix), 'has prefix');
        $fsRremoved = $fsPath->removePrefix($fsPrefix);

        if ($isPrefix) {
            $this->assertTrue($fsRremoved->isPresent(), 'has prefix');
            $fsExpect = self::pathOf($strRemovedPrefix);
            $this->assertSame((string)$fsExpect, (string)$fsRremoved->get(), 'remove prefix');
        } else
            $this->assertFalse($fsRremoved->isPresent(), 'has not prefix');
    }

    // ========================================================================

    public function testFSFile_setFile(): void
    {
        $fsFile = FS::fileOf('a/file.txt');
        $this->assertSame(['a', 'upd.txt'], $fsFile->setFile('upd.txt')->toListOfElements());
    }
}
