<?php

declare(strict_types=1);

namespace Time2Split\PCP\Tests\pcp;

use PHPUnit\Framework\Attributes\DataProvider;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Diff\Algorithm\Myers;
use Time2Split\Diff\DiffInstruction;
use Time2Split\Diff\DiffInstructionType;
use Time2Split\Help\Container\Bags;
use Time2Split\Help\IO;
use Time2Split\Help\Tests\DiffReports;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\App;
use Time2Split\PCP\App\Bootstrap;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\PCP;

final class ProcessTest extends AbstractProcessTestClass
{
    private const ResultFileName = 'result';

    private const TargetFileName = 'target';

    private const BaseDir =  __DIR__ . '/process';

    private const WDir =  __DIR__ . '/../../tests.wd/process';

    private const FSRoot =  __DIR__ . '/../../tests.wd/process.result';

    // ========================================================================

    public static function setUpBeforeClass(): void
    {
        \chdir(self::BaseDir);

        // Setup
        if (!\is_dir(self::WDir))
            \mkdir(self::WDir, recursive: true);
        if (!\is_dir(self::FSRoot))
            \mkdir(self::FSRoot, recursive: true);
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

            if (!\preg_match("#([^/]*)$result([^/]*)#", $fname, $matches))
                continue;

            yield "$matches[1]%s$matches[2]";
        }
    }

    private static function getTests()
    {
        $it = new \DirectoryIterator(self::BaseDir);

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
                yield $header => [FS::dirOf($dir), $pattern];
            }
        })();
    }

    private static function getExpectConfiguration(array $expect): Configuration
    {
        $config = Configurations::ofTree();

        for ($i = 0, $c = \count($expect); $i < $c; $i++) {
            $e = $expect[$i];

            if ($e instanceof ActionCommand)
                $config->merge($e->getArguments());
        }
        return $config;
    }

    // ========================================================================

    #[DataProvider("processProvider")]
    public function testProcess(FSDir $dir, string $resultPattern)
    {
        $resultFile = \sprintf($resultPattern, self::ResultFileName);
        $targetFile = \sprintf($resultPattern, self::TargetFileName);

        $fsWD   = FS::dirOf(self::WDir . "/$dir")->canonical();
        $fsRoot = FS::dirOf(self::FSRoot . "/$dir")->canonical();

        if (!\is_dir("$fsWD")) {
            \mkdir("$fsWD");
        }
        if ($fsRoot->inFS()) {
            IO::rrmdir("$fsRoot", false);
        }
        if (!$fsRoot->inFS()) {
            \mkdir("$fsRoot");
        }
        self::rcopy($dir, $fsRoot);

        $config = App::emptyConfiguration();
        $config->merge([
            'generate.targets' => "/$targetFile",
            PCP::CONFIG_FS_ROOT => "$fsRoot",
            PCP::CONFIG_FS_WD => "$fsWD",
            PCP::CONFIG_PCP_ACTION => 'process',
            PCP::CONFIG_SOURCE_PATHS => '.',
        ]);
        $pcp = new PCP($config, Bootstrap::getProcessActions());

        $pcp->process();
        $fsTarget = $fsRoot->appendFile($targetFile);
        $fsResult = $fsRoot->appendFile($resultFile);

        $result = self::getCElementsResult($pcp, $fsTarget);
        $remains = [];
        $expect = self::getCElementsResult($pcp, $fsResult, $remains);
        $diff = [];

        $testConfig = self::getExpectConfiguration($remains);

        if ($testConfig['order']) {
            $diff = Myers::diffList($expect, $result, self::CElementEquals(...));
            $fail =  \array_any($diff, fn(DiffInstruction $a) => $a->type !== DiffInstructionType::Keep);
        } else {
            $emptyBag = fn() => Bags::toArrayKeys(self::CElement_id(...));
            $resultBag = $emptyBag($result);
            $expectBag = $emptyBag($expect);
            $resultBag->putFromList($result);
            $expectBag->putFromList($expect);
            $fail = !Bags::equals($expectBag, $resultBag);

            if ($fail)
                $diff = Myers::diffList($expect, $result, self::CElementEquals(...));
        }

        if ($fail) {
            $report = DiffReports::textReportOfList($diff, self::CElement_toString(...));
            $this->fail("Error: (diff)\n$report");
        } else {
            $this->assertTrue(true);
        }
    }
}
