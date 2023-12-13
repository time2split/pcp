<?php
namespace Data;

final class InterpolatedConfig implements IConfig
{

    private IConfig $decorate;

    private InterpolationBuilder $builder;

    private function __construct(IConfig $decorate, InterpolationBuilder $builder)
    {
        $this->decorate = $decorate;
        $this->builder = $builder;
    }

    public static function from(IConfig $config, array $groups = []): self
    {
        return new self($config, InterpolationBuilder::create([
            '' => $config,
            ...$groups
        ]));
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
        return $this->decorate->subConfig($offset);
    }

    public function child(): static
    {
        return new self($this->decorate->child(), $this->builder);
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

    public function mergeArray(array $config): void
    {
        $this->decorate->mergeArray($config);
    }

    public function mergeArrayRecursive(array $config): void
    {
        $this->decorate->mergeArrayRecursive($config);
    }

    // ========================================================================
    public function offsetExists($offset): bool
    {
        return $this->decorate->offsetExists($offset);
    }

    public function &offsetGet($offset): mixed
    {
        $v = $this->decorate->offsetGet($offset);

        if ($v instanceof Interpolation) {
            $v = (string) $v;
            return $v;
        }
        return $v;
    }

    public function offsetSet($offset, $value): void
    {
        if (\is_string($value))
            $value = $this->builder->for($value);

        $this->decorate->offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->decorate->offsetUnset($offset);
    }
}