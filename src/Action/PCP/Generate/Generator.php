<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate;

use RegexIterator;
use Time2Split\Config\Configuration;
use Time2Split\Help\IO;
use Time2Split\Help\TriState;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\PCP\Generate;
use Time2Split\PCP\App;
use Time2Split\PCP\File\HasFileSection;
use Time2Split\PCP\File\Section;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\FS\FSFile;
use Time2Split\PCP\PCP;

final class Generator
{
    private const tmpFile = 'tmp';

    public function __construct(
        private FSDir $fsSrcRoot,
        private FSDir $fsGenRoot,
        private Configuration $appConfig
    ) {}


    private static function includeSource(string $file): TargetsCode
    {
        return TargetsCode::array_decode(include $file);
    }

    private function areaWriter(FSFile $fsSrc, FSFile $fsTarget, array $genCodes): AreaWriter
    {
        $writer = App::fileInsertion((string)$fsTarget, self::tmpFile);

        return new AreaWriter($writer, $genCodes, $this->fsSrcRoot, $fsSrc, $this->appConfig);
    }

    private array $fsSourceCache;

    public function generate(): void
    {
        IO::wdPush((string)$this->fsGenRoot);

        $dirIterator = new \RecursiveDirectoryIterator('.', \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME);
        $dirIterator = new \RecursiveIteratorIterator($dirIterator);

        $genFileExtension = \preg_quote(Generate::FILE_EXTENSION);
        $dirIterator = new \RegexIterator(
            $dirIterator,
            "/^\.[\\/]((.+)\.$genFileExtension)\$/",
            RegexIterator::GET_MATCH
        );

        $maxSrcMTime = 0;
        foreach ($dirIterator as $match) {
            $fsGen = FS::fileOf($match[1]);
            $fsSrc = FS::fileOf($match[2]);
            $fsRootedSrc = $this->fsSrcRoot->appendFile($fsSrc);

            // The source file has been deleted
            if (! $fsRootedSrc->inFS()) {
                \fputs(STDERR, "Warning! The source file '$fsSrc' does not exists\n");
                \unlink("$fsGen");
                continue;
            }
            $this->processGenFile($fsSrc, $fsRootedSrc->info(), $fsGen, $maxSrcMTime);
        }
        IO::wdPop();
    }

    private function processGenFile(
        FSFile $fsSrc,
        \SplFileInfo $fsSrcInfo,
        FSFile $fsGen,
        int &$maxSrcMTime
    ): void {
        $srcMTime = $fsSrcInfo->getMTime();
        $targetsCode = $this->fsSourceCache["$fsGen"] ??= self::includeSource("$fsGen");

        foreach ($targetsCode as $target => $genCodes) {
            $fsTarget = $target->getFSFile();
            $fsTarget = PCP::resolveFSPath($fsTarget, $this->fsSrcRoot);

            // The target file has been deleted
            if (!$fsTarget->inFS()) {
                \fputs(STDERR, "Warning! The target file '$fsTarget' does not exists\n");
                continue;
            }
            $areas = $this->nextArea($fsTarget);
            // Must read the file before its writing
            $areas = \iterator_to_array($areas);

            $targetLastMTime = $fsTarget->info()->getMTime();
            $areaWriter = $this->areaWriter($fsSrc, $fsTarget, $genCodes);

            foreach ($areas as $area)
                $areaWriter->write($area);

            $areaWriter->close();

            $maxSrcMTime = \max($maxSrcMTime, $srcMTime);

            // Reset the target mtime: that permits to detect a worthless section generation
            if ($areaWriter->noInsertion())
                \touch("$fsTarget", $targetLastMTime);
            elseif ($targetLastMTime != $maxSrcMTime)
                \touch("$fsTarget", $maxSrcMTime);

            \clearstatcache(filename: "$fsTarget");
        }
    }

    private static function checkActionCommand($command, string $firstArg): bool
    {
        return $command instanceof ActionCommand
            && Generate::checkActionCommand($command, $firstArg);
    }

    /**
     * @return \Iterator<Area>
     */
    private function nextArea(FSFile $fsFile): \Iterator
    {
        assert($fsFile->rooted() === TriState::Yes);

        $creader = PCP::creaderOf($fsFile, $this->appConfig);
        $next = null;

        while (true) {

            if (isset($next)) {
                $area = $next;
                $next = null;
            } else
                $area = $creader->next();

            if (! isset($area))
                break;
            if (! self::checkActionCommand($area, 'area'))
                continue;

            /**
             * @var ActionCommand $area
             */
            $arguments = App::configShift($area->getArguments());
            $sectionsArguments = new \SplObjectStorage();

            $cppElement = $creader->next();
            $sections = [];

            if (! isset($cppElement));
            elseif (self::checkActionCommand($cppElement, 'begin')) {
                /**
                 * @var ActionCommand&HasFileSection $cppElement
                 */

                while (true) {
                    $end = $creader->next();

                    if (! isset($end))
                        break;

                    $isPCPEnd = self::checkActionCommand($end, 'end');

                    if ($isPCPEnd || self::checkActionCommand($end, 'begin')) {
                        /**
                         * @var ActionCommand&HasFileSection $end
                         */
                        $section = new Section(
                            $cppElement->getFileSection()->begin,
                            $end->getFileSection()->begin
                        );
                        $sectionsArguments->offsetSet($section, $cppElement->getArguments());
                        $sections[] = $section;
                        $cppElement = $end;
                    }
                    if ($isPCPEnd)
                        break;
                }

                if (! isset($isPCPEnd))
                    throw new \Exception("$fsFile: waiting for '#pragma pcp {$cppElement->getName()} end' (reached the end of the file)");

                /**
                 * @var \Time2Split\PCP\C\CPPDirective $end
                 */
                if (isset($end))
                    $sections[] = $end->getFileSection();
            } else
                $next = $cppElement;

            yield Areas::create($area, $arguments, $sectionsArguments, ...$sections);
        }
        $creader->close();
    }
}
