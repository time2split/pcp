<?php
namespace File;

final class Insertion
{

    private $tmpFile;

    private $fp;

    private $tmpfp;

    private int $written = 0;

    private int $pos = 0;

    private int $copied = 0;

    private function __construct($fp, string $tmpFile)
    {
        $this->tmpFile = $tmpFile;
        $this->fp = $fp;
        $this->tmp = new \SplFileInfo($tmpFile);
        $this->tmpfp = \fopen($tmpFile, "w+");
    }

    public static function fromFilePath(string $file, string $tmpFile)
    {
        return self::fromStream(\fopen($file, 'r'), $tmpFile);
    }

    public static function fromStream($stream, string $tmpFile)
    {
        $md = \stream_get_meta_data($stream);

        if ($md['mode'][0] !== 'r')
            throw new \Exception(__class__ . " the mode must be r, has '{$md['mode']} ({$md['uri']})");

        return new self($stream, $tmpFile);
    }

    public function getReadStream()
    {
        return $this->fp;
    }

    public function close(): int
    {
        if ($this->fp) {
            $this->flush();

            $this->seekReadStream();
            $nb = \fwrite($this->tmpfp, \stream_get_contents($this->fp));
            $this->written += $nb;
            \fclose($this->fp);
            \fclose($this->tmpfp);

            $this->fp = null;
            return $nb;
        }
        return 0;
    }

    private function flush(): bool
    {
        if ($this->written == 0)
            return true;

        $file = \stream_get_meta_data($this->fp)['uri'];

        if (! $file instanceof \SplFileInfo)
            $file = new \SplFileInfo((string) $file);

        // Preserve the original file times
        if (\is_file($file)) {
            \touch($this->tmpFile, $file->getMTime(), $file->getATime());
        }
        return \rename($this->tmpFile, $file);
    }

    // ========================================================================
    private function seekReadStream()
    {
        if (\ftell($this->fp) != $this->copied)
            \fseek($this->fp, $this->copied, SEEK_SET);
    }

    public function seek(int $pos): void
    {
        if ($pos < $this->pos)
            throw new \Exception(__class__ . " Cannot move to a lower position than $this->pos: ask for $pos");

        $this->pos = $pos;
        return;
    }

    public function write(string $text): int
    {
        if (! $this->fp)
            return 0;

        $nb = 0;

        if ($this->pos !== $this->copied) {
            $this->seekReadStream();
            $nb = \fwrite($this->tmpfp, \fread($this->fp, $this->pos));
            $this->copied += $nb;
        }
        $nbw = \fwrite($this->tmpfp, $text);
        $this->written += $nb;
        return $nb + $nbw;
    }
}