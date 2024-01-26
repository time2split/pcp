<?php
namespace Time2Split\PCP\Action\PCP\For;

use Time2Split\Config\Configuration;

final class Cond
{

    public array $instructions = [];

    public function __construct( //
    public Configuration $config, //
    public readonly mixed $condition) //
    {}
}