<?php

declare(strict_types=1);

namespace Time2Split\Help\Tests;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Time2Split\PCP\App;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\PCP;

final class ProcessTest extends TestCase
{
    private const ResultFileName = 'result';

    private static function isProcessTestDir(string $dir): bool
    {
        return \is_dir($dir) && \is_file("$dir/" . self::ResultFileName);
    }

    private static function getCElementsResult(string $filePath): array
    {
        $result = [];
        $creader = CReader::fromFile($filePath);

        while (null !== ($celement = $creader->next())) {

            if ($celement instanceof CDeclaration)
                $result[] = $celement;
        }
        return $result;
    }

    private static function CDeclarationEquals(CDeclaration $a, CDeclaration $b): bool
    {
        $aitems = $a['items'];
        $bitems = $b['items'];
        $c = \count($aitems);

        if (
            $a->getElementType() !== $b->getElementType()
            || $a->getIdentifier() !== $b->getIdentifier()
            || ($c) !== \count($bitems)
        )
            return false;

        for ($i = 0; $i < $c; $i++) {
            $a = $aitems[$i];
            $b = $bitems[$i];

            if (
                $a instanceof CDeclaration
                && $b instanceof CDeclaration
                && self::CDeclarationEquals($a, $b)
            );
            elseif ($a === $b);
            else return false;
        }
        return true;
    }


    private const BaseDir =  __DIR__ . '/process';

    private const WDir =  __DIR__ . '/pcp.wd';

    public static function processProvider(): \Traversable
    {
        // Setup
        if (!\is_dir(self::WDir))
            \mkdir(self::WDir, recursive: true);

        return (function () {
            $it = new RecursiveDirectoryIterator(self::BaseDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME);
            $it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($it as $d) {

                if (!self::isProcessTestDir($d))
                    continue;

                $d = \substr($d, \strlen(self::BaseDir) + 1);
                yield [(string)$d, $d];
            }
        })();
    }

    #[DataProvider("processProvider")]
    public function testProcess(string $dir)
    {
        \chdir(self::BaseDir);

        $pcp = new PCP();
        $target = self::WDir . "/target";
        $config = App::defaultConfiguration();
        $config->merge([
            'generate.targets' => $target,
            'pcp.dir' => self::WDir,
            'paths' => $dir,
        ]);

        $targetContentsFile = "$dir/target";

        if (\is_file($targetContentsFile))
            $targetContents = \file_get_contents($targetContentsFile);
        else
            $targetContents = "#pragma pcp generate area";

        \file_put_contents($target, $targetContents);

        $pcp->process('process', $config);
        $result = self::getCElementsResult($target);
        $expect = self::getCElementsResult("$dir/" . self::ResultFileName);

        foreach ($expect as $e) {
            $r = \array_shift($result);

            if (!self::CDeclarationEquals($e, $r)) {
                $me = \print_r($e->getArrayCopy()['items'], true);
                $mr = \print_r($r->getArrayCopy()['items'], true);
                $msg = "Expecting $me but have $mr";
                $this->fail($msg);
            }
        }
        $this->assertEmpty($result, \print_r($result, true));
    }
}
