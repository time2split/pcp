<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Help\Set;
use Time2Split\Help\Sets;
use Time2Split\PCP\Help\ArrayCodec;

final class GeneratedCode implements ArrayCodec
{

    private function __construct( //
        private readonly string $text, //
        private readonly Set $tags,
    ) {}

    public static function create(string $text, string ...$tags)
    {
        return new self($text, (Sets::arrayKeys())->setMore(...$tags));
    }

    public function array_encode(): array
    {
        return [
            'text' => $this->text,
            'tags' => \iterator_to_array($this->tags),
        ];
    }

    public static function array_decode(array $array): self
    {
        return self::create($array['text'], ...($array['tags'] ?? []));
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getTags(): Set
    {
        return $this->tags;
    }
}
