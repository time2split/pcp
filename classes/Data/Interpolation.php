<?php
namespace Data;

abstract class Interpolation
{

    protected mixed $group;

    protected mixed $key;

    protected function __construct(string $group, string $key)
    {
        $this->group = $group;
        $this->key = $key;
    }

    abstract public function get(): mixed;

    public function __toString(): string
    {
        $v = $this->get();

        if (\is_array($v))
            return \print_r($v, true);

        return (string) $v;
    }
}