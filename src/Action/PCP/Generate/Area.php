<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;

interface Area
{

    public function getFileCursors(): array;

    public function getArguments(): Configuration;
}