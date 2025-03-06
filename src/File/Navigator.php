<?php

declare(strict_types=1);

namespace Time2Split\PCP\File;

use Time2Split\Help\Arrays;
use Time2Split\Help\Streams;

final class Navigator
{

    private $fp;

    private int $offset;

    private int $nl = 0;

    private int $nlc = 0;

    private int $nc = 0;

    private array $cache = [];

    private bool $closeStream;

    private function __construct($fp, bool $closeStream)
    {
        if (!Streams::isSeekableStream($fp))
            throw new \Exception(__class__ . " The stream must be seekable (has: $fp)");

        $this->fp = $fp;
        $this->offset = \ftell($fp);
        $this->closeStream = $closeStream;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function getStream()
    {
        return $this->fp;
    }

    public static function fromStream($stream, bool $closeStream = true)
    {
        return new self($stream, $closeStream);
    }

    public function close(): void
    {
        if ($this->closeStream) {
            \fclose($this->fp);
            $this->closeStream = false;
        }
    }

    // ========================================================================
    public function getCursorPosition(): CursorPosition
    {
        return new CursorPosition($this->nl + 1, $this->nlc, $this->nc);
    }

    public function getc()
    {
        $c = \fgetc($this->fp);

        if (false === $c)
            return false;

        $this->nlc++;
        $this->nc++;

        if ($c === "\n") {

            if (!isset($this->cache[$this->nl])) {
                list($i, $j) = \iterator_to_array(Arrays::lastValue($this->cache, [
                    0,
                    0
                ]));

                $this->cache[] = [
                    $i + $j, // pos
                    $this->nlc // size
                ];
            }
            $this->nl++;
            $this->nlc = 0;
        }
        return $c;
    }

    public function ungetc(int $nb = 1): void
    {
        $this->nc -= $nb;

        if ($this->nlc - $nb < 0)
            $this->ungetcUpdate($nb);
        else {
            $this->nlc -= $nb;
        }

        if (-1 === \fseek($this->fp, $this->offset + $this->nc, SEEK_SET))
            throw new \Error("Cannot seek file");
    }

    private function ungetcUpdate(int $nb): void
    {
        if (-1 === \fseek($this->fp, $this->offset + $this->nc, SEEK_SET))
            throw new \Error("Cannot seek file");

        $contents = \fgets($this->fp, $nb + 1);

        if (false === $contents)
            return;

        $nblines = \substr_count($contents, "\n");

        $this->nl -= $nblines;

        // Get the pos in the line
        list($lpos,) = $this->cache[$this->nl];
        $this->nlc = $this->nc - $lpos;
    }

    // ========================================================================
    public function getChars(\Closure $predicate): string
    {
        return Streams::getChars($this->getc(...), $this->ungetc(...), $predicate);
    }

    public function getCharsUntil(\Closure $predicate): string
    {
        return Streams::getCharsUntil($this->getc(...), $this->ungetc(...), $predicate);
    }

    public function skipChars(\Closure $predicate): int
    {
        return Streams::skipChars($this->getc(...), $this->ungetc(...), $predicate);
    }
}
