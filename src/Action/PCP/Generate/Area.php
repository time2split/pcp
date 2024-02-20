<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\PCP\File\Section;
use Time2Split\PCP\C\Element\PCPPragma;

interface Area
{

    public function getPCPPragma(): PCPPragma;

    public function getSections(): array;

    public function getSectionArguments(Section $section): Configuration;

    public function getArguments(): Configuration;
}