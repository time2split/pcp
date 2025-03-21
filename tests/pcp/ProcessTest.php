<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\Container\Bags;
use Time2Split\Help\Container\Sets;
use Time2Split\Help\Tests\DiffReports;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\App;
use Time2Split\PCP\App\Bootstrap;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CPPDefine;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\Diff\Algorithm\Myers;
use Time2Split\PCP\Diff\Output\DiffInstruction;
use Time2Split\PCP\Diff\Output\DiffInstructionType;
use Time2Split\PCP\Help\HelpSets;
use Time2Split\PCP\PCP;

final class ProcessTest extends TestCase
{
    private const ResultFileName = 'result';

    private const TargetFileName = 'target';

    private const BaseDir =  __DIR__ . '/process';

    private const WDir =  __DIR__ . '/../../tests.wd/process';

    private static function getCElementsResult(PCP $pcp, string $filePath, array &$remains = []): array
    {
        $result = [];
        $creader = $pcp->getCReaderOf($filePath);

        while (null !== ($celement = $creader->next())) {

            if ($celement instanceof CElement)
                $result[] = $celement;
            else
                $remains[] = $celement;
        }
        $creader->close();
        return $result;
    }

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

    private static function CElement_id(CElement $a): string
    {
        if ($a instanceof CDeclaration)
            return self::CDeclaration_toString($a);
        if ($a instanceof CPPDirective) {
            return '#' . $a->getDirective() . ' ' . $a->getText();
        }
        return (string)$a;
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

    private static function getDiffTypeChar(DiffInstructionType $type)
    {
        return match ($type) {
            DiffInstructionType::Drop => ' - ',
            DiffInstructionType::Keep => '   ',
            DiffInstructionType::Insert => ' + ',
        };
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
                // if (!str_contains($dir, 'sensibility')) continue;
                $header = "$dir:$pattern";
                yield $header => [$dir, $pattern];
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
    public function testProcess(string $dir, string $resultPattern)
    {
        $resultFile = \sprintf($resultPattern, self::ResultFileName);
        $targetFile = \sprintf($resultPattern, self::TargetFileName);
        $wdir = self::WDir . "/$dir";

        if (!\is_dir($wdir))
            \mkdir($wdir);

        $target = "$wdir/$targetFile";
        $config = App::emptyConfiguration();
        $config->merge([
            'generate.targets' => $target,
            'pcp.dir' => $wdir,
            'pcp.action' => 'process',
            'pcp.paths' => $dir,
        ]);
        $pcp = new PCP($config, Bootstrap::getProcessActions());

        $targetContentsFile = "$dir/$targetFile";

        if (\is_file($targetContentsFile))
            $targetContents = \file_get_contents($targetContentsFile);
        else
            $targetContents = "#pragma pcp generate area";

        \file_put_contents($target, $targetContents);

        $pcp->process();
        $result = self::getCElementsResult($pcp, $target);
        $remains = [];
        $expect = self::getCElementsResult($pcp, "$dir/$resultFile", $remains);
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
            \ob_start();
            foreach ($diff as $i) {
                $op = self::getDiffTypeChar($i->type);
                // $item = self::CElement_toString($i->item);
                $item = $i->item;
                echo "$op$item\n";
            }
            $msg = \ob_get_clean();
            $this->fail("Error: (diff)\n$msg");
        } else {
            $this->assertTrue(true);
        }
    }

    public static function setUpBeforeClass(): void
    {
        \chdir(self::BaseDir);

        // Setup
        if (!\is_dir(self::WDir))
            \mkdir(self::WDir, recursive: true);
    }
}
