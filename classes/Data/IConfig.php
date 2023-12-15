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

    public function getKeyDelimiter(): string;

    /**
     * The NullValue indicates a non existent element in the configuration.
     * In a configuration it may be usefull to have null without its sematic of non-existent value.
     * In that case the NullValue takes this role.
     * By default NullValue must be \Help\NullValue::v.
     */
    public function getNullValue(): mixed;

    // ========================================================================
    public function &getReference($offset): mixed;

    public function get($offset): mixed;

    // ========================================================================
    public function subConfig($offset): static;

    public function select($offset): static;

    /**
     * Create a new IConfig object of the same type that inherit all data from this.
     * The parent data stay as default values for the new IConfig and cannot be removed.
     */
    public function child(): IConfig;

    // ========================================================================
    public function keys(): array;

    public function toArray(): array;

    public function clear(): void;

    // ========================================================================

    /**
     * Merge the first level of a $config,
     * that is add all the key/value pairs from $config.
     *
     * @param array $config
     */
    public function flatMerge(array $config): void;

    /**
     * Merge some configuration data.
     * The merge occurs recursively with sub-data.
     * If a sub-data is a list, then the list is considered as a simple value and the recursion stop.
     *
     * @param array $config
     *            The configuration data
     */
    public function merge(array|IConfig $config): void;
}