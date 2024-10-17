<?php

declare(strict_types=1);

namespace Time2Split\Help\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Time2Split\Help\IO;
use Time2Split\PCP\App;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\PCP;

final class ProcessTest extends TestCase
{
    private const ResultFileName = 'result';

    private static function isProcessTestDir(\SplFileInfo $dir): bool
    {
        $result = self::ResultFileName;
        return $dir->isDir() && \is_file("$dir/$result");
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
            !($a->getGroup() === $b->getGroup()
                && $a->getElementType() === $b->getElementType()
                && $a->getIdentifier() === $b->getIdentifier())
            && ($c) === \count($bitems)
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
    public function testProcess()
    {
        $baseDir = __DIR__ . '/process';
        $wdir = __DIR__ . '/pcp.wd';
        $target = "$wdir/target";

        IO::wdPush($baseDir);
        $it = new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS);
        $it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
        $pcp = new PCP();
        $config = App::defaultConfiguration();
        $config->merge([
            'generate.targets' => $target,
            'pcp.dir' => $wdir
        ]);

        foreach ($it as $dirInfos) {

            if (!self::isProcessTestDir($dirInfos))
                continue;

            \file_put_contents($target, "#pragma pcp generate area");

            $config['paths'] = \substr((string)$dirInfos, 2);
            $pcp->process('process', $config);
            $result = self::getCElementsResult($target);
            $expect = self::getCElementsResult("$dirInfos/" . self::ResultFileName);

            foreach ($expect as $e) {
                $r = \array_shift($result);

                if (!self::CDeclarationEquals($e, $r)) {
                    $me = \print_r($e->getArrayCopy()['items'], true);
                    $mr = \print_r($r->getArrayCopy()['items'], true);
                    $msg = "Expecting $me but have $mr";
                    $this->assertTrue(false, $msg);
                }
            }
            $this->assertEmpty($result, \print_r($result, true));
        }
        IO::wdPop();
    }
}
