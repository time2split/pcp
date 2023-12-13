<?php
namespace Data;

final class InterpolatedConfig extends AbstractTreeConfig
{

    private IConfig $decorate;

    private InterpolationBuilder $builder;

    private function __construct(IConfig $decorate, array $groups)
    {
        parent::__construct($decorate->getKeyDelimiter());
        $this->decorate = $decorate;
        $this->builder = $this->createBuilder($groups);
    }

    private function createBuilder(array $groups): InterpolationBuilder
    {
        $groups[''] = $this;
        return InterpolationBuilder::create($groups);
    }

    public static function from(IConfig $config, array $groups = []): self
    {
        return new self($config, $groups);
    }

    public function getNullValue(): mixed
    {
        return $this->decorate->getNullValue();
    }

    public function &getReference($offset): mixed
    {
        return $this->decorate->getReference($offset);
    }

    public function get($offset): mixed
    {
        return $this->decorate->get($offset);
    }

    public function subConfig($offset): static
    {
        return new self($this->decorate->subConfig($offset), $this->builder->getGroups());
    }

    public function child(): static
    {
        return new self($this->decorate->child(), $this->builder->getGroups());
    }

    public function keys(): array
    {
        return $this->decorate->keys();
    }

    public function toArray(): array
    {
        return $this->decorate->toArray();
    }

    public function clear(): void
    {
        $this->decorate->clear();
    }

    // ========================================================================
    public function offsetExists($offset): bool
    {
        return $this->decorate->offsetExists($offset);
    }

    public function &offsetGet($offset): mixed
    {
        $v = $this->decorate->offsetGet($offset);

        if (! \is_string($v))
            return $v;

        $v = $this->builder->for($v);

        if ($v instanceof Interpolation) {
            $v = (string) $v;
            return $v;
        }
        return $v;
    }

    public function offsetSet($offset, $value): void
    {
        $this->decorate->offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->decorate->offsetUnset($offset);
    }
}