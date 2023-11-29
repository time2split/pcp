<?php
namespace C;

abstract class ReaderElement implements \Action\IActionMessage, \ArrayAccess
{

    protected array $elements;

    protected array $tags;

    protected function __construct(array $elements, array $tags = [])
    {
        $this->elements = $elements;
        $this->tags = $tags;
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function addTag(string $tag): void
    {
        $this->tags[] = $tag;
    }

    public function getParameters(): ?array
    {
        if (! isset($this->elements['parameters']))
            return null;

        $params = [];
        foreach ($this->elements['parameters'] as $p)
            $params[] = $this->elements['items'][$p];

        return $params;
    }

    // ========================================================================
    public function &offsetGet($offset): mixed
    {
        return $this->elements[$offset];
    }

    public function offsetSet($offset, $val): void
    {
        $this->elements[$offset] = $val;
    }

    public function offsetExists($offset): mixed
    {
        return isset($this->elements[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->elements[$offset]);
    }

    // ========================================================================
    public function iterator(): \Iterator
    {
        return new \ArrayIterator($this->elements);
    }
}