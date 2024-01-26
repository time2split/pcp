<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\Config\Configuration;
use Time2Split\Help\FIO;
use Time2Split\Help\IO;
use Time2Split\Help\Classes\NotInstanciable;

final class CPPDirectives
{
    use NotInstanciable;

    public static function create(Configuration $pcpConfig, string $directive, string $text, array $cursors): CPPDirective
    {
        assert(\count($cursors) === 2 && \array_is_list($cursors));

        if ($directive === 'define')
            return CPPDefine::createCPPDefine($text, $cursors);

        $stream = IO::stringToStream($text);
        $first = FIO::streamGetCharsUntil($stream, \ctype_space(...));
        $pcpNames = $pcpConfig['pcp.name'];

        if (\in_array($first, $pcpNames))
            return PCPPragma::createPCPPragma($pcpConfig, $directive, $text, $cursors, $stream);

        return CPPDirective::create($directive, $text, $cursors);
    }

    public static function factory(Configuration $pcpConfig): \Closure
    {
        return fn ($directive, $text, $cursors) => self::create($pcpConfig, $directive, $text, $cursors);
    }
}