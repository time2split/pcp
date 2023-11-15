<?php
namespace Help;

final class FIO
{
    public static function skipSpaces(callable $fgetc, callable $fungetc): int
    {
        return self::skipChars($fgetc, $fungetc, '\ctype_space');
    }
    
    public static function skipChars(callable $fgetc, callable $fungetc, callable $predicate): int
    {
        $nb = 0;

        while ($predicate($c = $fgetc()))
            $nb ++;

        if ($c !== false)
            $fungetc();

        return $nb;
    }

    public static function getChars(callable $fgetc, callable $fungetc, callable $predicate): ?string
    {
        $ret = '';
        
        while ($predicate($c = $fgetc()))
            $ret .= $c;

        if ($c !== false)
            $fungetc();

        return strlen($ret) > 0 ? $ret : null;
    }

    public static function skipSimpleDelimitedText(callable $fgetc, string $endDelimiter): void
    {
        $skip = false;

        while (true) {
            $c = $fgetc($fp);

            if ($c === '\\')
                $skip = true;
            elseif ($c === $endDelimiter && ! $skip)
                return;
            elseif ($skip)
                $skip = false;
        }
    }

    /**
     * Test if a character is a delimiter.
     *
     * @param string $c
     *            The caracter to test as possible delimiter
     * @param string $delimiters
     *            The list of pair of <open/close> delimiters
     * @return string|NULL The closing delimiter if $c is a delimiter or null
     */
    public static function isDelimitation(string $c, string $delimiters): ?string
    {
        for ($i = 0, $n = \strlen($delimiters); $i < $n; $i += 2) {
            
            if ($delimiters[$i] === $c)
                return $delimiters[$i + 1];
        }
        return null;
    }

//     public static function skipDelimitedText(callable $fgetc, string $delimiters)
//     {
//         $buff = "";
//         $skip = false;
//         $endDelimiters = [];
//         $endDelimiter = null;

//         while (true) {
//             $c = $fgetc();
//             $buff .= $c;

//             if ($c === false)
//                 return false;
//             if ($c === '\\')
//                 $skip = true;
//             elseif ($c == '/') {
//                 if ($this->skipComment($fp))
//                     $buff = \substr($buff, 0, - 1);
//             } elseif ($c === $endDelimiter && ! $skip) {
//                 $endDelimiter = \array_pop($endDelimiters);

//                 if (empty($endDelimiter))
//                     return $buff;
//             } else {

//                 if ($skip)
//                     $skip = false;

//                 $end = self::isDelimitation($c, $delimiters);

//                 if (null !== $end) {
//                     \array_push($endDelimiters, $endDelimiter);
//                     $endDelimiter = $end;
//                 }
//             }
//         }
//     }
}