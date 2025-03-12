<?php
namespace Time2Split\PCP\Expression\Node;

abstract class BinaryNode implements Node
{

    public final function __construct( //
    protected readonly string $op, //
    protected readonly Node $left, //
    protected readonly Node $right) //
    {}
}