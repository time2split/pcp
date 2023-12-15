<?php
namespace C;

abstract class ReaderElement implements Element, \Action\IActionMessage, \ArrayAccess
{

    protected array $elements;

    protected function __construct(array $elements)
    {
        $this->elements = $elements;
    }

    public function getElements(): array
    {
        return $this->elements;
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