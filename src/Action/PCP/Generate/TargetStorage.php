<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\PCP\App;
use Time2Split\PCP\Action\PCP\Generate;
use Time2Split\PCP\C\CElements;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\File\Section;

final class TargetStorage
{

    private array $targets = [];

    public function __construct()
    {
        $this->targets = [];
    }

    // ========================================================================
    public function getTarget(string $path): Target
    {
        $target = $this->targets[$path] ?? null;

        if (isset($target))
            return $target;

        $finfos = new \SplFileInfo($path);
        return $this->targets[$path] = self::createTarget($finfos);
    }

    // ========================================================================
    private static function createTarget(\SplFileInfo $finfo): Target
    {
        return new class($finfo) implements Target {

            private array $areas = [];

            function __construct(private readonly \SplFileInfo $finfo)
            {}

            public function getAreas(): array
            {}

            public function getFileInfo(): \SplFileInfo
            {
                return $this->finfo;
            }
        };
    }

    // ========================================================================
    public static function areasIterator(\SplFileInfo $target, int $srcMTime, CReader $creader): \Iterator
    {
        $next = null;

        while (true) {

            if (isset($next)) {
                $elem = $next;
                $next = null;
            } else
                $elem = $creader->next();

            if (! isset($elem))
                break;
            if (! Generate::isPCPGenerate($elem, 'area'))
                continue;

            $args = App::configShift($elem->getArguments());
            $begin = $creader->next();

            if (isset($begin)) {

                if (CElements::isPCPCommand($begin, 'begin')) {
                    // Wait for end
                    while (null !== ($end = $creader->next()) && ! CElements::isPCPCommand($end, 'end'));

                    if (! isset($end))
                        throw new \Exception("$target: waiting 'end' pcp pragma from $begin; reached the end of the file");

                    $args['mtime'] = $begin->getArguments()['mtime'] ?? $srcMTime;
                    $fileSection = new Section($begin->getFileSection()->begin, $end->getFileSection()->end);
                    yield Areas::create($fileSection, $args);
                    continue;
                } else {
                    $next = $begin;
                }
            }
            noBegin:
            // No begin/end area: return the cursor after the pragma
            $c = $elem->getFileSection()->end;

            // The next element to check is $begin
            $next = $begin;
            $args['mtime'] = $srcMTime;
            yield Areas::create(new Section($c, $c), $args);
        }
    }
}