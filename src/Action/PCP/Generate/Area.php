<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\PCP\File\Section;

interface Area
{

    public function getFileSection(): Section;

    public function getArguments(): Configuration;
}