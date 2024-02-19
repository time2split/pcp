<?php
namespace Time2Split\PCP\File\_internal;

use Time2Split\PCP\File\StreamInsertion;

final class StreamInsertionImpl implements StreamInsertion
{

    private $sourceStream;

    private $tmpStream;

    private int $inserted = 0;

    private int $pos = 0;

    private int $copied = 0;

    private bool $closeStream;

    private function __construct($sourceStream, string $tmpFile, bool $closeStream)
    {
        $this->sourceStream = $sourceStream;
        $this->tmpStream = \fopen($tmpFile, "w+");
        $this->closeStream = $closeStream;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function insertionCount(): int
    {
        return $this->inserted;
    }

    public static function fromFilePath(string $file, string $tmpFile, bool $closeStream = true)
    {
        return self::fromStream(\fopen($file, 'r'), $tmpFile, $closeStream);
    }

    public static function fromStream($stream, string $tmpFile, bool $closeStream = true)
    {
        $md = \stream_get_meta_data($stream);

        if ($md['mode'][0] !== 'r')
            throw new \Exception("The mode must be r, has '{$md['mode']} ({$md['uri']})");

        return new self($stream, $tmpFile, $closeStream);
    }

    public function getSourceStream()
    {
        if (\ftell($this->sourceStream) != $this->pos)
            \fseek($this->sourceStream, $this->pos, SEEK_SET);

        return $this->sourceStream;
    }

    public function close(): void
    {
        if ($this->sourceStream && $this->closeStream) {
            $this->seekReadStream();
            \fwrite($this->tmpStream, \stream_get_contents($this->sourceStream));

            \fclose($this->sourceStream);
            \fclose($this->tmpStream);
            $this->sourceStream = null;
        }
    }

    // ========================================================================
    private function seekReadStream()
    {
        if (\ftell($this->sourceStream) != $this->copied)
            \fseek($this->sourceStream, $this->copied, SEEK_SET);
    }

    private function preWrite(): void
    {
        $nb = 0;
        if ($this->pos !== $this->copied) {
            $this->seekReadStream();
            $len = $this->pos - $this->copied;
            $nb = \fwrite($this->tmpStream, \fread($this->sourceStream, $len));

            if ($nb !== $len)
                throw new \Exception("Written $nb/$len bytes");

            $this->copied = $this->pos;
        }
    }

    public function seekMore(int $pos): void
    {
        $this->seekSet($this->pos + $pos);
    }

    public function seekSet(int $pos): void
    {
        if ($pos < $this->pos)
            throw new \Exception("Cannot move to a lower position than $this->pos: ask for $pos");

        $this->pos = $pos;
    }

    public function seekSkip(int $pos): void
    {
        if (! $this->sourceStream)
            return;

        $this->preWrite();
        $this->seekSet($pos);

        // Just to raise the write condition at the end
        $this->copied = $this->pos;
    }

    public function write(string $text = ''): void
    {
        if (! $this->sourceStream)
            return;

        $this->preWrite();
        $this->inserted += \fwrite($this->tmpStream, $text);
    }
}