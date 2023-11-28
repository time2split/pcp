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

        foreach ($list as $c)
            $delims[] = $c->getNullValue();

        $udelims = \array_unique($delims);

        if (\count($udelims) > 1)
            throw new \Error(__class__ . " Has multiple delimiters: " . print_r($delims, true));
    }

    public static function create(): self
    {
        return new self(TreeConfig::empty());
    }

    private function last(): TreeConfig
    {
        return \Help\Arrays::first($this->rlist);
    }

    // ========================================================================
    public function getNullValue(): mixed
    {
        return $this->last()->getNullValue;
    }

    public function subConfig(mixed $offset): static
    {
        foreach ($this->rlist as $c)
            if (isset($c[$offset]))
                return $c->subConfig();

        return $this->last()->subConfig($offset);
    }

    public function child(): static
    {
        $last = \Help\Arrays::first($this->rlist);
        $ret = new self();
        $ret->rlist = [
            $last->child(),
            ...$this->rlist
        ];
        return $ret;
    }

    public function &getReference($offset): mixed
    {
        return $this->last()->getReference();
    }

    public function get($offset): mixed
    {
        foreach ($this->rlast as $c) {
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
    public function mergeArray(array $config): void
    {
        $this->last()->mergeArray($config);
    }

    public function mergeArrayRecursive(array $config): void
    {
        $this->last()->mergeArrayRecursive($config);
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
        $this->last()->unset($offset);
    }
}