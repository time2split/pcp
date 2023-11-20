<?php
namespace C;

abstract class ReaderElement implements \Action\IActionMessage
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
}