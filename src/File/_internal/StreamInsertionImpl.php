<?php
namespace Time2Split\PCP\File\_internal;

use Time2Split\PCP\File\StreamInsertion;

final class StreamInsertionImpl implements StreamInsertion
{

    private string $sourceFile;

    private $destinationfp;

    private array $fp_metaData;

    private $tmpfp;

    private int $written = 0;

    private int $pos = 0;

    private int $copied = 0;

    private bool $closeStream;

    private function __construct($destinationfp, string $sourceFile, bool $closeStream)
    {
        $this->sourceFile = $sourceFile;
        $this->destinationfp = $destinationfp;
        $this->fp_metaData = \stream_get_meta_data($this->destinationfp);
        $this->tmpfp = \fopen($sourceFile, "w+");
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

    public function getSourceStream()
    {
        if (\ftell($this->destinationfp) != $this->pos)
            \fseek($this->destinationfp, $this->pos, SEEK_SET);

        return $this->destinationfp;
    }

    public function close(): void
    {
        if ($this->destinationfp && $this->closeStream) {
            $this->seekReadStream();
            $nb = \fwrite($this->tmpfp, \stream_get_contents($this->destinationfp));
            $this->written += $nb;

            \fclose($this->destinationfp);
            \fclose($this->tmpfp);
            $this->destinationfp = null;
        }
    }

    // ========================================================================
    private function seekReadStream()
    {
        if (\ftell($this->destinationfp) != $this->copied)
            \fseek($this->destinationfp, $this->copied, SEEK_SET);
    }

    private function preWrite(): void
    {
        $nb = 0;
        if ($this->pos !== $this->copied) {
            $this->seekReadStream();
            $len = $this->pos - $this->copied;
            $nb = \fwrite($this->tmpfp, \fread($this->destinationfp, $len));

            if ($nb !== $len)
                throw new \Exception(__CLASS__ . " error, written $nb/$len bytes");

            $this->copied += $nb;
        }
    }

    public function seekMore(int $pos): void
    {
        $this->seekSet($this->pos + $pos);
    }

    public function seekSet(int $pos): void
    {
        if ($pos < $this->pos)
            throw new \Exception(__class__ . " Cannot move to a lower position than $this->pos: ask for $pos");

        $this->pos = $pos;
    }

    public function seekSkip(int $pos): void
    {
        if (! $this->destinationfp)
            return;

        $this->preWrite();
        $this->seekSet($pos);

        // Just to raise the write condition at the end
        $this->written += $this->pos - $this->copied;

        $this->copied = $this->pos;
    }

    public function write(string $text = ''): void
    {
        if (! $this->destinationfp)
            return;

        $nb = $this->preWrite();
        \fwrite($this->tmpfp, $text);
        $this->written += $nb;
    }
}