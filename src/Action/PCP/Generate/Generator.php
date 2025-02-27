<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\PCP\Generate;
use Time2Split\PCP\App;
use Time2Split\PCP\File\HasFileSection;
use Time2Split\PCP\File\Section;
use Time2Split\PCP\PCP;

final class Generator
{
    private const tmpFile = 'tmp';

    public function __construct(private Configuration $appConfig) {}


    private static function includeSource(string $file): TargetsCode
    {
        return TargetsCode::array_decode(include $file);
    }

    private function areaWriter(\SplFileInfo $srcFileInfo, \SplFileInfo $targetFileInfo, array $genCodes)
    {
        $writer = App::fileInsertion((string)$targetFileInfo, self::tmpFile);
        $srcTime = $srcFileInfo->getMTime();

        $sourcesRoot = $this->appConfig['dir.root'];
        $srcFile = \substr((string)$srcFileInfo, 1 + \strlen($sourcesRoot));

        return new AreaWriter($writer, $srcTime, $genCodes, $srcFile, $this->appConfig);
    }

    public function generate(): void
    {
        $sourcesPath = $this->appConfig['dir.root'];
        $sourceCache = [];

        $dirIterator = new \RecursiveDirectoryIterator('.', \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME);
        $dirIterator = new \RecursiveIteratorIterator($dirIterator);
        $dirIterator = new \RegexIterator($dirIterator, "/^.+\.gen\.php$/");

        $maxSrcMTime = 0;

        foreach ($dirIterator as $genFilePath) {
            // Skip the './' prefix
            $genFilePath = \substr((string)$genFilePath, 2);

            $srcFile = \substr($genFilePath, 0, -8);
            $srcFilePath = "$sourcesPath/$srcFile";

            // The source file has been deleted
            if (! \is_file($srcFilePath)) {
                \unlink($genFilePath);
                continue;
            }
            $baseFileInfo = new \SplFileInfo($srcFilePath);
            $srcMTime = $baseFileInfo->getMTime();
            $targetsCode = $sourceCache[$genFilePath] ??= self::includeSource($genFilePath);

            foreach ($targetsCode as $target => $genCodes) {
                $targetFilePath = (string)$target->getFileInfo();

                if (!\str_starts_with($targetFilePath, '/'))
                    $targetFilePath = "$sourcesPath/$targetFilePath";

                // The target file has been deleted
                if (! \is_file($targetFilePath)) {
                    \fputs(STDERR, "Warning! The target file '$targetFilePath' does not exists\n");
                    continue;
                }

                $areas = $this->nextArea($targetFilePath);
                // Must read the file before its writing
                $areas = \iterator_to_array($areas);

                $targetFileInfo = new \SplFileInfo($targetFilePath);
                $targetLastMTime = $targetFileInfo->getMTime();
                $areaWriter = $this->areaWriter($baseFileInfo, $targetFileInfo, $genCodes);

                foreach ($areas as $area)
                    $areaWriter->write($area);

                $areaWriter->close();

                $maxSrcMTime = \max($maxSrcMTime, $srcMTime);

                // Reset the target mtime: that permits to detect a worthless section generation
                if ($areaWriter->noInsertion())
                    \touch($targetFilePath, $targetLastMTime);
                elseif ($targetLastMTime != $maxSrcMTime)
                    \touch($targetFilePath, $maxSrcMTime);

                \clearstatcache(filename: $targetFilePath);
            }
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
    private function nextArea(string $targetFilePath): \Iterator
    {
        $creader = PCP::creaderOf($targetFilePath, $this->appConfig);
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
                        $sectionsArguments->attach($section, $cppElement->getArguments());
                        $sections[] = $section;
                        $cppElement = $end;
                    }
                    if ($isPCPEnd)
                        break;
                }

                if (! isset($isPCPEnd))
                    throw new \Exception("$targetFilePath: waiting 'end' pcp pragma from $cppElement; reached the end of the file");

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
