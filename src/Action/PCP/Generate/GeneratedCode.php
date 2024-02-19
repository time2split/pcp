<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\PCP\Help\ArrayCodec;

final class GeneratedCode implements ArrayCodec
{

    private \ArrayObject $moreTags;

    private function __construct( //
    private readonly string $text, //
    private readonly array $tags)
    {
        $this->moreTags = new \ArrayObject();
    }

    public static function create(string $text, string ...$tags)
    {
        return new self($text, $tags);
    }

    public function array_encode(): array
    {
        return [
            'text' => $this->text,
            'tags' => $this->tags
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

    public function getTags(): array
    {
        return \array_merge($this->tags, $this->moreTags->getArrayCopy());
    }

    public function moreTags(): \ArrayObject
    {
        return $this->moreTags;
    }
}
