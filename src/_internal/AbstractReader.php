<?php

declare(strict_types=1);

namespace Time2Split\PCP\_internal;

use Time2Split\Help\CharPredicates;
use Time2Split\Help\Streams;
use Time2Split\PCP\File\CursorPosition;
use Time2Split\PCP\File\Navigator;

abstract class AbstractReader
{

    protected Navigator $fnav;

    private function __construct($stream, bool $closeStream = true)
    {
        $this->fnav = Navigator::fromStream($stream, $closeStream);
    }

    public function __destruct()
    {
        $this->close();
    }

    public static function ofStream($stream, bool $closeStream = true): static
    {
        return new static($stream, $closeStream);
    }

    public static function ofFile(string|\SplFileInfo $filePath, bool $closeStream = true): static
    {
        return new static(\fopen((string) $filePath, 'r'), $closeStream);
    }

    public static function ofString($string): static
    {
        return new static(Streams::stringToStream($string), false);
    }

    // ========================================================================

    public final function getCursorPosition(): CursorPosition
    {
        return $this->fnav->getCursorPosition();
    }

    public function close(): void
    {
        $this->fnav->close();
    }

    // ========================================================================
    // READ

    protected final function fgetc(): string|false
    {
        $c = $this->fnav->getc();

        if ($c !== '\\')
            return $c;

        $next = $this->fnav->getc();

        if ($next !== "\n") {

            if ($c !== false)
                $this->fungetc();

            return $c;
        }
        return "\\\n";
    }

    protected final function fungetc(int $nb = 1): void
    {
        $this->fnav->ungetc($nb);
    }

    protected final function nextWord(?\Closure $pred = null): string
    {
        if (!isset($pred)) {
            $pred = fn($c) =>
            ctype_alnum($c) || $c === '_';
        }
        $this->skipUselessText();
        return $this->fnav->getChars($pred);
    }

    protected final function nextChar()
    {
        $this->skipUselessText();
        return $this->fgetc();
    }

    protected final function silentChar()
    {
        $c = $this->nextChar();
        $this->fungetc();
        return $c;
    }

    // ========================================================================
    // SKIP

    protected final function skipSpaces(): bool
    {
        return 0 < $this->fnav->skipChars(\ctype_space(...));
    }

    /**
     * Skip the next comment.
     * 
     * The stream is placed directly on a comment (no char is skipped).
     * 
     * @return bool true if a comment was skipped.
     */
    protected final function skipComment(bool $checkFirstChar = true): bool
    {
        if ($checkFirstChar) {
            $c = $this->fnav->getc();

            if ($c === false)
                return false;
            if ($c !== '/') {
                $this->fungetc();
                return false;
            }
        }
        $state = 0;

        while (true) {
            $c = $this->fnav->getc();

            if ($c === false)
                goto endOfStream;

            switch ($state) {

                // Comment ?
                case 0:
                    if ('/' === $c)
                        $state = 1;
                    elseif ('*' === $c)
                        $state = 100;
                    else
                        goto endOfStream;
                    break;
                case 1:
                    if ("\n" === $c)
                        return true;
                    break;
                // Multiline comment
                case 100:
                    if ('*' === $c)
                        $state++;
                    break;
                case 101:
                    if ('/' === $c)
                        return true;
                    elseif ('*' === $c);
                    else
                        $state--;
                    break;
            }
        }
        endOfStream:

        // Inside a comment
        if ($state > 0) {
            assert($c === false);
            return true;
        } else { // Not a comment
            $this->fungetc();

            if ($checkFirstChar)
                $this->fungetc();

            return false;
        }
    }

    protected final function skipUselessText(): void
    {
        $cursor = $this->fnav->getCursorPosition();

        do {
            $lastCursor = $cursor;
            $this->skipSpaces();
            $this->skipComment(true);
            $cursor = $this->fnav->getCursorPosition();
        } while ($cursor != $lastCursor);
    }

    // ========================================================================

    /**
     * Get the next delimited text.
     * 
     * The text must begin/end properly by knowned delimiters.
     */
    protected final function getDelimitedText(string $delimiters): string|false
    {
        $buff = "";
        $skip = false;
        $endDelimiters = [];
        $endDelimiter = null;

        while (true) {
            $c = $this->fgetc();
            $buff .= $c;

            if ($c === false)
                return false;
            if ($c === '\\')
                $skip = true;
            elseif ($c == '/') {

                if ($this->skipComment(false)) {
                    $buff = \substr($buff, 0, -1);
                }
            } elseif ($c === $endDelimiter && !$skip) {
                $endDelimiter = \array_pop($endDelimiters);

                if (!isset($endDelimiter)) {
                    return $buff;
                }
            } else {

                if ($skip)
                    $skip = false;

                $end = CharPredicates::isDelimitation($c, $delimiters);

                if (false !== $end) {
                    \array_push($endDelimiters, $endDelimiter);
                    $endDelimiter = $end;
                }
            }
        }
    }
}
