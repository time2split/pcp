<?php

declare(strict_types=1);

namespace Time2Split\PCP\Tests\pcp;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Time2Split\Help\Container\Sets;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\FS\FSFile;
use Time2Split\PCP\PCP;

abstract class AbstractProcessTestClass extends TestCase
{
    protected static function rcopy(FSDir $from, FSDir $to): void
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
                \copy("$fsFile", "$fsTo");
            }
        }
    }

    protected static function getCElementsResult(PCP $pcp, FSFile $fsFile, array &$remains = []): array
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

    protected static function CElement_id(CElement $a): string
    {
        if ($a instanceof CDeclaration)
            return self::CDeclaration_toString($a);
        if ($a instanceof CPPDirective) {
            return '#' . $a->getDirective() . ' ' . $a->getText();
        }
        return (string)$a;
    }

    protected static function CElementEquals(CElement $a, CElement $b): bool
    {
        if ($a instanceof CPPDirective && $b instanceof CPPDirective)
            return self::CPPDirectiveEquals($a, $b);
        return self::CDeclarationEquals($a, $b);
    }

    protected static function CPPDirectiveEquals(CPPDirective $a, CPPDirective $b): bool
    {
        return
            $a->getDirective() === $b->getDirective() &&
            $a->getText() === $b->getText();
    }

    protected static function CDeclarationEquals(CDeclaration $a, CDeclaration $b): bool
    {
        $aitems = $a['items'];
        $bitems = $b['items'];
        $c = \count($aitems);

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

    protected static function CElement_toString(?CElement $a): string
    {
        if ($a === null)
            return 'NULL';
        if ($a instanceof CDeclaration)
            return self::CDeclaration_toString($a);
        return (string)$a;
    }

    protected static function CDeclaration_toString(CDeclaration $a): string
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
}
