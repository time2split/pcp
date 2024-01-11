<?php
namespace Help;

final class Optional
{

    private readonly mixed $value;

    private readonly bool $isPresent;

    public static function of($value): self
    {
        $ret = new Optional();
        $ret->isPresent = true;
        $ret->value = $value;
        return $ret;
    }

    private static Optional $empty;

    public static function empty(): self
    {
        if (! isset(self::$empty)) {
            $e = new self();
            $e->isPresent = false;
            self::$empty = $e;
        }
        return self::$empty;
    }

    // ========================================================================
    public final function isPresent()
    {
        return $this->isPresent;
    }

    public final function get()
    {
        return $this->value;
    }

    public final function orElse($value)
    {
        if (! $this->isPresent())
            return $value;

        return $this->value;
    }
}