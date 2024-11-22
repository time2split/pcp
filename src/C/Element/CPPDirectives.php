<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\Element;

use Time2Split\Config\Configuration;
use Time2Split\Help\Streams;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\File\Section;

final class CPPDirectives
{
    use NotInstanciable;

    private static function create(Configuration $pcpConfig, string $directive, string $text, Section $cursors): CPPDirective
    {
        if ($directive === 'define')
            return CPPDefine::createCPPDefine($text, $cursors);

        $stream = Streams::stringToStream($text);
        $first = Streams::streamGetCharsUntil($stream, \ctype_space(...));
        $pcpNames = (array)$pcpConfig['pcp.pragma.names'];

        if (\in_array($first, $pcpNames))
            return PCPPragma::createPCPPragma($pcpConfig, $directive, $text, $cursors, $stream);

        return CPPDirective::create($directive, $text, $cursors);
    }

    public static function factory(Configuration $pcpConfig): \Closure
    {
        return fn(string $directive, string $text, Section $cursors) => self::create($pcpConfig, $directive, $text, $cursors);
    }
}
