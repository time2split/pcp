<?php
namespace File;

final class Insertion
{

    private string $tmpFile;

    private $fp;

    private array $fp_metaData;

    private $tmpfp;

    private int $written = 0;

    private int $pos = 0;

    private int $copied = 0;

    private bool $closeStream;

    private function __construct($fp, string $tmpFile, bool $closeStream)
    {
        $this->tmpFile = $tmpFile;
        $this->fp = $fp;
        $this->fp_metaData = \stream_get_meta_data($this->fp);
        $this->tmpfp = \fopen($tmpFile, "w+");
        $this->closeStream = $closeStream;
    }

    public static function fromFilePath(string $file, string $tmpFile, bool $closeStream = true)
    {
        return self::fromStream(\fopen($file, 'r'), $tmpFile, $closeStream);
    }

    public static function fromStream($stream, string $tmpFile, bool $closeStream = true)
    {
        $md = \stream_get_meta_data($stream);

        if ($md['mode'][0] !== 'r')
            throw new \Exception(__class__ . " the mode must be r, has '{$md['mode']} ({$md['uri']})");

        return new self($stream, $tmpFile, $closeStream);
    }

    public function getReadStream()
    {
        if (\ftell($this->fp) != $this->pos)
            \fseek($this->fp, $this->pos, SEEK_SET);

        return $this->fp;
    }

    public function close(): int
    {
        if ($this->fp && $this->closeStream) {
            $this->seekReadStream();
            $nb = \fwrite($this->tmpfp, \stream_get_contents($this->fp));
            $this->written += $nb;

            \fclose($this->fp);
            \fclose($this->tmpfp);
            $this->fp = null;

            $this->flush();
            return $nb;
        }
        return 0;
    }

    private function flush(): bool
    {
        if ($this->written == 0)
            return true;

        $file = new \SplFileInfo($this->fp_metaData['uri']);

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

    private function preWrite(): int
    {
        $nb = 0;
        if ($this->pos !== $this->copied) {
            $this->seekReadStream();
            $len = $this->pos - $this->copied;
            $nb = \fwrite($this->tmpfp, \fread($this->fp, $len));
            $this->copied += $nb;
        }
        return $nb;
    }

    public function seekAdd(int $pos): void
    {
        $this->seek($this->pos + $pos);
    }

    public function seek(int $pos): void
    {
        if ($pos < $this->pos)
            throw new \Exception(__class__ . " Cannot move to a lower position than $this->pos: ask for $pos");

        $this->pos = $pos;
    }

    public function seekForget(int $pos): void
    {
        if (! $this->fp)
            return;

        $this->preWrite();
        $this->seek($pos);

        // Just to raise the write condition at the end
        $this->written += $this->pos - $this->copied;

        $this->copied = $this->pos;
    }

    public function write(string $text = ''): int
    {
        if (! $this->fp)
            return 0;

        $nb = $this->preWrite();
        $nbw = \fwrite($this->tmpfp, $text);
        $this->written += $nb;
        return $nb + $nbw;
    }
}