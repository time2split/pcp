<?php

declare(strict_types=1);

namespace Time2Split\Help\Tests;

use DirectoryIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Time2Split\PCP\App;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\Help\HelpSets;
use Time2Split\PCP\PCP;

final class ProcessTest extends TestCase
{
    private const ResultFileName = 'result';

    private const TargetFileName = 'target';

    private const BaseDir =  __DIR__ . '/process';

    private const WDir =  __DIR__ . '/pcp.wd';

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
            !HelpSets::equals($a->getElementType(), $b->getElementType())
            || $a->getIdentifier() !== $b->getIdentifier()
            || ($c) !== \count($bitems)
        )
            return false;

        if (isset($a['cstatement']) && $a['cstatement'] !== $b['cstatement'])
            return false;

        for ($i = 0; $i < $c; $i++) {
            $a = $aitems[$i];
            $b = $bitems[$i];

            if (
                $a instanceof CDeclaration
                && $b instanceof CDeclaration
            ) {
                if (! self::CDeclarationEquals($a, $b))
                    return false;
            } elseif ($a === $b);
            else
                return false;
        }
        return true;
    }
    private static function CDeclaration_toString(CDeclaration $a): string
    {
        $aitems = $a['items'];
        $c = \count($aitems);
        $ret = '';

        for ($i = 0; $i < $c; $i++) {
            $item = $aitems[$i];

            if ($i > 0)
                $ret .= ' ';

            if ($item instanceof CDeclaration)
                $ret .= self::CDeclaration_toString($item);
            else
                $ret .= $item;
        }
        $ret .= ($a['cstatement'] ?? '');
        return $ret;
    }

    // ========================================================================

    /**
     * Found all files of the form "${prefix}result${suffix}".
     * @return A pattern "${prefix}%s${suffix}" for each founded result file.
     */
    private static function getTestsOfADirectory(\SplFileInfo $directory)
    {
        $result = self::ResultFileName;
        $files = new \DirectoryIterator($directory->getPathname());

        foreach ($files as $file) {
            $fname = $file->getFilename();

            if (!\preg_match("/(.*)$result(.*)/", $fname, $matches))
                continue;

            yield "$matches[1]%s$matches[2]";
        }
    }

    private static function getTests()
    {
        $it = new DirectoryIterator(self::BaseDir);

        foreach ($it as $dir) {
            if (!$dir->isDir()) continue;

            foreach (self::getTestsOfADirectory($dir) as $testPattern) {
                yield $dir->getFilename() => $testPattern;
            }
        }
    }

    public static function processProvider(): \Traversable
    {
        return (function () {
            $tests = self::getTests();

            foreach ($tests as $dir => $pattern) {
                $header = "$dir:$pattern";
                yield $header => [$dir, $pattern];
            }
        })();
    }

    // ========================================================================

    #[DataProvider("processProvider")]
    public function testProcess(string $dir, string $resultPattern)
    {
        $resultFile = \sprintf($resultPattern, self::ResultFileName);
        $targetFile = \sprintf($resultPattern, self::TargetFileName);
        $pcp = new PCP();
        $wdir = self::WDir . "/$dir";

        if (!\is_dir($wdir))
            \mkdir($wdir);

        $target = "$wdir/$targetFile";
        $config = App::defaultConfiguration();
        $config->merge([
            'generate.targets' => $target,
            'pcp.dir' => $wdir,
            'paths' => $dir,
        ]);

        $targetContentsFile = "$dir/$targetFile";

        if (\is_file($targetContentsFile))
            $targetContents = \file_get_contents($targetContentsFile);
        else
            $targetContents = "#pragma pcp generate area";

        \file_put_contents($target, $targetContents);

        $pcp->process('process', $config);
        $result = self::getCElementsResult($target);
        $expect = self::getCElementsResult("$dir/$resultFile");

        foreach ($expect as $e) {
            $r = \array_shift($result);

            if (!self::CDeclarationEquals($e, $r)) {
                $me = self::CDeclaration_toString($e);
                $mr = self::CDeclaration_toString($r);
                $msg = "Expecting $me but have $mr";
                $this->fail($msg);
            } else $this->assertTrue(true);
        }
        // $this->assertEmpty($result,  "End: The 'result' array is not empty");
    }

    public static function setUpBeforeClass(): void
    {
        \chdir(self::BaseDir);

        // Setup
        if (!\is_dir(self::WDir))
            \mkdir(self::WDir, recursive: true);
    }
}
