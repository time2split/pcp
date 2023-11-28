<?php
namespace Data;

/**
 * A set of accessible and modifiable key => value pairs.
 * This interface does not describe what is the format and the semantic of the key part,
 * it may be the same as keys in an array, or be more complex like a hierarchical structure "a.b.c".
 *
 * @author zuri
 *        
 */
interface IConfig extends \ArrayAccess
{

    /**
     * The NullValue indicates a non existent element in the configuration.
     * In a configuration it may be usefull to have null without its sematic of non-existent value.
     * In that case the NullValue takes this role.
     * By default NullValue must be \Help\NullValue::v.
     *
     * @return mixed
     */
    public function getNullValue(): mixed;

    public function &getReference($offset): mixed;

    public function get($offset): mixed;

    public function subConfig($offset): static;

    /**
     * Create a new IConfig object that inherit all data from this.
     *
     * @return static
     */
    public function child(): static;

    public function keys(): array;

    public function toArray(): array;

    public function clear(): void;

    public function mergeArray(array $config): void;

    public function mergeArrayRecursive(array $config): void;
}