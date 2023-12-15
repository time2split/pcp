<?php
namespace Data;

/**
 * A sequence of TreeConfig instances where the the last one is the only mutable instance.
 *
 * @author zuri
 */
final class TreeConfigHierarchy implements IConfig
{

    /**
     * The first element is the last TreeConfig added.
     */
    private array $rlist;

    private mixed $nullValue;

    // ========================================================================
    private function __construct(IConfig ...$list)
    {
        $this->rlist = \array_reverse($list);
        $delims = [];

        $udelims = \Help\Arrays::map_unique(fn ($i) => $i->getKeyDelimiter(), $list);

        if (\count($udelims) > 1)
            throw new \Error(__class__ . " Has multiple delimiters: " . print_r($delims, true));

        $unull = \Help\Arrays::map_unique(fn ($i) => $i->getNullValue(), $list);

        if (\count($unull) > 1)
            throw new \Error(__class__ . " Has multiple null value: " . print_r($unull, true));
    }

    public function getKeyDelimiter(): string
    {
        return $this->last()->getKeyDelimiter();
    }

    public static function create(IConfig ...$list): self
    {
        $list[] = TreeConfig::empty();
        return new self(...$list);
    }

    public function __clone(): void
    {
        $last = $this->rlist[0];
        $this->rlist[0] = clone $last;
    }

    private function last(): IConfig
    {
        return \Help\Arrays::first($this->rlist);
    }

    // ========================================================================
    public function getNullValue(): mixed
    {
        return $this->last()->getNullValue();
    }

    public function subConfig(mixed $offset): static
    {
        $sub = [];
        foreach ($this->rlist as $c)
            $sub[] = $c->subConfig($offset);

        return new self(...$sub);
    }

    public function select($offset): static
    {
        $sub = [];
        foreach ($this->rlist as $c)
            $sub[] = $c->select($offset);

        return new self(...$sub);
    }

    public function child(): static
    {
        return self::create(...$this->rlist);
    }

    public function &getReference($offset): mixed
    {
        return $this->last()->getReference();
    }

    public function get($offset): mixed
    {
        foreach ($this->rlist as $c) {
            $v = $c[$offset];

            if ($v !== $c->getNullValue())
                return $v;
        }
        return $this->last()->getNullValue;
    }

    public function keys(): array
    {
        $ret = [];
        $cache = [];

        foreach ($this->rlist as $c)
            foreach ($c->keys() as $k)
                if (! isset($cache[$k])) {
                    $cache[$k] = true;
                    $ret[] = $k;
                }

        return $ret;
    }

    public function toArray(): array
    {
        $ret = [];

        foreach ($this->rlist as $c)
            $ret += $c->toArray();

        return $ret;
    }

    public function clear(): void
    {
        $this->last()->clear();
    }

    // ========================================================================
    public function flatMerge(array $config): void
    {
        $this->last()->flatMerge($config);
    }

    public function merge(array|IConfig $config): void
    {
        $this->last()->merge($config);
    }

    // ========================================================================
    public function offsetExists($offset): bool
    {
        foreach ($this->rlist as $c)
            if ($c->offsetExists($offset))
                return true;

        return false;
    }

    public function &offsetGet($offset): mixed
    {
        foreach ($this->rlist as $c) {
            $ret = $c->get($offset, \Help\NullValue::v);

            if ($ret !== \Help\NullValue::v)
                return $ret;
        }
        $ret = null;
        return $ret;
    }

    public function offsetSet($offset, $value): void
    {
        $this->last()->offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->last()->offsetUnset($offset);
    }
}