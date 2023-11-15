<?php
namespace File;

final class Navigator
{

    private $fp;

    private int $nl = 0;

    private int $nlc = 0;

    private int $nc = 0;

    private array $cache = [];

    private function __construct($fp)
    {
        $this->fp = $fp;
        rewind($fp);
    }

    public static function fromStream($stream)
    {
        return new self($stream);
    }

    public function close(): void
    {
        \fclose($this->fp);
    }

    // ========================================================================
    public function getCursorPosition(): CursorPosition
    {
        return CursorPosition::create($this->nl + 1, $this->nlc, $this->nc);
    }

    public function getc()
    {
        $c = \fgetc($this->fp);
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
        \fseek($this->fp, $this->nc, SEEK_SET);
    }

    private function ungetcUpdate(int $nb)
    {
        \fseek($this->fp, $this->nc, SEEK_SET);
        $contents = \fgets($this->fp, $nb + 1);
        $nblines = \substr_count($contents, "\n");

        $this->nl -= $nblines;

        // Get the pos in the line
        list ($lpos, $lsize) = $this->cache[$this->nl];
        $this->nlc = $this->nc - $lpos;
    }
}