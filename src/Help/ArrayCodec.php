<?php
namespace Time2Split\PCP\Help;

interface ArrayCodec
{

    /**
     * Encode $this instance to an array representation
     */
    public function array_encode(): array;

    /**
     * Decode an array representation to an instance
     */
    public static function array_decode(array $array): object;
}