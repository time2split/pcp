<?php
namespace Time2Split\PCP\Expression\Node;

abstract class UnaryNode implements Node
{

    public final function __construct( //
    protected readonly string $op, //
    protected readonly Node $node) //
    {}
}