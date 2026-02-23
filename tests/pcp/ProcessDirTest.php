<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Time2Split\Diff\Algorithm\Myers;
use Time2Split\Diff\DiffInstruction;
use Time2Split\Diff\DiffInstructionType;
use Time2Split\Help\Container\Sets;
use Time2Split\Help\IO;
use Time2Split\Help\Tests\DiffReports;
use Time2Split\Help\TriState;
use Time2Split\PCP\App;
use Time2Split\PCP\App\Bootstrap;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\FS\FSFile;
use Time2Split\PCP\PCP;

final class ProcessDirTest extends TestCase
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

    private static function rcopy(FSDir $from, FSDir $to): void
    {
        $sfrom = "$from";

        $files = new RecursiveDirectoryIterator(
            $sfrom,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME
        );
        $files = new RecursiveIteratorIterator($files, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $fsFile = FS::fileOf($file);
            $fsCleanFile = $fsFile->removePrefix($from)->get();
            $fsTo = $to->append($fsCleanFile);

            if ($fsFile->info()->isDir()) {

                if (!$fsTo->inFS()) {
                    \mkdir("$fsTo");
                }
            } else {
                copy("$fsFile", "$fsTo");
            }
        }
    }

    private static function getCElementsResult(PCP $pcp, FSFile $fsFile, array &$remains = []): array
    {
        $result = [];
        $creader = $pcp->getCReaderOf($fsFile);

        while (null !== ($celement = $creader->next())) {

            if ($celement instanceof CElement)
                $result[] = $celement;
            else
                $remains[] = $celement;
        }
        $creader->close();
        return $result;
    }

    // ========================================================================

    private static function CElementEquals(CElement $a, CElement $b): bool
    {
        if ($a instanceof CPPDirective && $b instanceof CPPDirective)
            return self::CPPDirectiveEquals($a, $b);
        return self::CDeclarationEquals($a, $b);
    }

    private static function CPPDirectiveEquals(CPPDirective $a, CPPDirective $b): bool
    {
        return
            $a->getDirective() === $b->getDirective() &&
            $a->getText() === $b->getText();
    }

    private static function CDeclarationEquals(CDeclaration $a, CDeclaration $b): bool
    {
        $aitems = $a['items'];
        $bitems = $b['items'];
        $c = \count($aitems);

        // var_dump($a); //->getIdentifier());
        // var_dump($b); //->getIdentifier());

        if (
            !Sets::equals($a->getElementType(), $b->getElementType())
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
    private static function CElement_toString(CElement $a): string
    {
        if ($a instanceof CDeclaration)
            return self::CDeclaration_toString($a);
        return (string)$a;
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

        // $fsTestRoot = self::BaseDir . "/$dir";
        // $fsTestExpect = self::FSBaseDiroot . "/$expectDir";

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

        $config = App::emptyConfiguration();
        $config->merge([
            // 'generate.targets' => $target,
            PCP::CONFIG_FS_ROOT => "$fsRoot",
            PCP::CONFIG_FS_WD => "$fsWD",
            PCP::CONFIG_PCP_ACTION => 'process',
            PCP::CONFIG_SOURCE_PATHS => '.',
        ]);
        $pcp = new PCP($config, Bootstrap::getProcessActions());
        $pcp->process();

        $expectFiles = new \RecursiveDirectoryIterator(
            "$dirExpect",
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME
        );
        $expectFiles = new \RecursiveIteratorIterator($expectFiles);

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
                $this->fail("Error($fsCleanFile): (diff)\n$report");
            }
        }
        $this->assertTrue(true);
    }
}
