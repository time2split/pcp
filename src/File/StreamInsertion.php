<?php
namespace Time2Split\PCP\File;

interface StreamInsertion
{

    /**
     * Get the opened resource stream at its current position.
     * If the stream has been red then the new position of the stream is used for the next insertion operations.
     */
    public function getSourceStream();

    /**
     *
     * @param int $pos
     * @return bool
     * @throws \Exception::
     */
    public function close(): void;

    /**
     *
     * @param int $pos
     * @return bool
     * @throws \Exception::
     */
    public function seekSet(int $pos): void;

    /**
     *
     * @param int $pos
     * @return bool
     * @throws \Exception::
     */
    public function seekMore(int $pos): void;

    /**
     *
     * @param int $pos
     * @return bool
     * @throws \Exception::
     */
    public function seekSkip(int $pos): void;

    /**
     *
     * @param int $pos
     * @return bool
     * @throws \Exception::
     */
    public function write(string $text = ''): void;
}