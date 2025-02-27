<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Time2Split\PCP\App;
use Time2Split\PCP\PCP;

final class CleanTest extends TestCase
{
    private const BaseDir =  __DIR__ . '/clean';

    private const WDir =  __DIR__ . '/../tests.wd/clean';

    private const PCPDir =  __DIR__ . '/../tests.wd/clean.wd';

    private static function getTests(): \Traversable
    {
        $it = new \FilesystemIterator(self::BaseDir);

        foreach ($it as $dir) {
            if (!$dir->isDir()) continue;
            yield $dir->getFilename();
        }
    }

    public static function cleanProvider(): \Traversable
    {
        return (function () {
            foreach (self::getTests() as $dir)
                yield [$dir, [$dir]];
        })();
    }

    // ========================================================================

    private static function mkdir(string $dir): void
    {
        if (!\is_dir($dir))
            \mkdir($dir, recursive: true);
    }

    private static function files(string|\SplFileInfo $dir): \Traversable
    {
        $dir = (string)$dir;
        $from_offset = \strlen($dir) + 1;

        $it = new \RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $it = new \RecursiveIteratorIterator($it);

        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            yield \substr($file->getPathname(), $from_offset);
        }
    }

    private static function copy(string|\SplFileInfo $from, string|\SplFileInfo $to): void
    {
        $from = (string)$from;
        $to = (string)$to;

        foreach (self::files($from) as $file) {
            $toFile = "$to/$file";
            self::mkdir(\dirname($toFile));
            copy("$from/$file", $toFile);
        }
    }

    #[Test]
    #[DataProvider("cleanProvider")]
    public function clean(string $dir)
    {
        $basedir = self::BaseDir . "/$dir";
        $wd = self::WDir . "/$dir";
        $pcpdir = self::PCPDir . "/$dir";

        self::mkdir($pcpdir);

        \chdir(self::BaseDir);
        self::copy($dir, $wd);

        $config = App::emptyConfiguration()
            ->mergeTree([
                'pcp' => [
                    'dir' => $pcpdir,
                    'action' => 'process',
                    'paths' => $dir,
                ]
            ]);

        \chdir(self::WDir);
        (new PCP($config))->process();

        $changes = [];
        foreach (self::files($wd) as $file) {
            $src = \file_get_contents("$basedir/$file");
            $target = \file_get_contents("$wd/$file");

            if ($src !== $target)
                $changes[] = $file;
        }
        $this->assertNotEmpty($changes);
        \clearstatcache();

        $config['pcp.action'] = 'clean';
        (new PCP($config))->process();

        foreach ($changes as $file) {
            $src = \file_get_contents("$basedir/$file");
            $target = \file_get_contents("$wd/$file");
            $this->assertSame($src, $target, $file);
        }
    }

    public static function setUpBeforeClass(): void
    {
        self::mkdir(self::WDir);
    }
}
