<?php
namespace File;

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
        return CursorPosition::create($this->nl + 1, $this->nlc, $this->nc);
    }

    public function getc()
    {
        $c = \fgetc($this->fp);

        if (false === $c)
            return false;

        $this->nlc ++;
        $this->nc ++;

        if ($c === "\n") {

            if (! isset($this->cache[$this->nl])) {
                list ($i, $j) = \Help\Arrays::last($this->cache, [
                    0,
                    0
                ]);

                $this->cache[] = [
                    $i + $j, // pos
                    $this->nlc // size
                ];
            }
            $this->nl ++;
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
        \fseek($this->fp, $this->offset + $this->nc, SEEK_SET);
    }

    private function ungetcUpdate(int $nb)
    {
        \fseek($this->fp, $this->offset + $this->nc, SEEK_SET);
        $contents = \fgets($this->fp, $nb + 1);
        $nblines = \substr_count($contents, "\n");

        $this->nl -= $nblines;

        // Get the pos in the line
        list ($lpos, $lsize) = $this->cache[$this->nl];
        $this->nlc = $this->nc - $lpos;
    }

    // ========================================================================
    public function getChars(callable $predicate): ?string
    {
        return \Help\FIO::getChars([
            $this,
            'getc'
        ], [
            $this,
            'ungetc'
        ], $predicate);
    }

    public function getCharsUntil($endDelimitation): ?string
    {
        return \Help\FIO::getCharsUntil([
            $this,
            'getc'
        ], $endDelimitation);
    }

    public function skipChars(callable $predicate): ?string
    {
        return \Help\FIO::skipChars([
            $this,
            'getc'
        ], [
            $this,
            'ungetc'
        ], $predicate);
    }
}