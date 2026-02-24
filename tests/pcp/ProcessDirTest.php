<?php

declare(strict_types=1);

namespace Time2Split\PCP\Tests\pcp;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Time2Split\Diff\Algorithm\Myers;
use Time2Split\Diff\DiffInstruction;
use Time2Split\Diff\DiffInstructionType;
use Time2Split\Help\IO;
use Time2Split\Help\Tests\DiffReports;
use Time2Split\Help\TriState;
use Time2Split\PCP\App;
use Time2Split\PCP\App\Bootstrap;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\PCP;

final class ProcessDirTest extends AbstractProcessTestClass
{
    private const ExpectFileSuffix = '.expect';

    private const BaseDir =  __DIR__ . '/processDir';

    private const WDir =  __DIR__ . '/../../tests.wd/processDir';

    private const FSRoot =  __DIR__ . '/../../tests.wd/processDir.result';

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

    public static function processProvider(): iterable
    {
        $it = new \FileSystemIterator(self::BaseDir);

        foreach ($it as $dir) {

            if (!$dir->isDir())
                continue;

            $name = $dir->getFilename();

            if (\str_ends_with($name, '.expect'))
                continue;

            yield [FS::dirOf($dir->getFilename())];
        }
    }

    #[DataProvider("processProvider")]
    public function testProcess(FSDir $dir)
    {
        $dirExpect = $dir->setLast(fn(string $dname) => "$dname" . self::ExpectFileSuffix, TriState::No);

        $fsWD   = FS::dirOf(self::WDir . "/$dir")->canonical();
        $fsRoot = FS::dirOf(self::FSRoot . "/$dir")->canonical();

        if (!\is_dir("$dirExpect"))
            $this->fail("The directory $dir has no expect directory $dirExpect");
        if (!\is_dir("$fsWD"))
            \mkdir("$fsWD");

        if ($fsRoot->inFS()) {
            IO::rrmdir("$fsRoot", false);
        }
        if (!$fsRoot->inFS()) {
            \mkdir("$fsRoot");
        }
        self::rcopy($dir, $fsRoot);

        // Detect config.php at the root
        $fsConfig = $dir->appendFile('config.php');
        $testConfig = $fsConfig->inFS() ? include "$fsConfig" : [];

        $config = App::emptyConfiguration();
        $config->merge([
            PCP::CONFIG_FS_ROOT => "$fsRoot",
            PCP::CONFIG_FS_WD => "$fsWD",
            PCP::CONFIG_PCP_ACTION => 'process',
            PCP::CONFIG_SOURCE_PATHS => '.',
        ])->merge($testConfig);
        $pcp = new PCP($config, Bootstrap::getProcessActions());
        $pcp->process();

        $expectFiles = new \RecursiveDirectoryIterator(
            "$dirExpect",
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME
        );
        $expectFiles = new \RecursiveIteratorIterator($expectFiles);
        $failures = [];

        foreach ($expectFiles as $expectFile) {
            $fsEFile = FS::fileOf($expectFile);
            $fsCleanFile = $fsEFile->removePrefix($dirExpect)->get();
            $fsResultFile = $fsRoot->append($fsCleanFile);

            if (!$fsResultFile->inFS())
                $this->fail("No result file $fsResultFile for $fsCleanFile");

            $expect = self::getCElementsResult($pcp, $fsEFile);
            $result = self::getCElementsResult($pcp, $fsResultFile);

            $diff = Myers::diffList($expect, $result, self::CElementEquals(...));
            $fail = \array_any($diff, fn(DiffInstruction $a) => $a->type !== DiffInstructionType::Keep);

            if ($fail) {
                $report = DiffReports::textReportOfList($diff, self::CElement_toString(...));
                $failures[] = "Error($dir$fsCleanFile): (diff)\n$report";
            }
        }
        if (empty($failures)) {
            $this->assertTrue(true);
        } else {
            $nb = \count($failures);
            $list = \implode(PHP_EOL, $failures);
            $this->fail("failure: $nb\n$list");
        }
    }
}
