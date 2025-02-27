<?php

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\File\HasFileSection;
use Time2Split\PCP\File\Section;

interface Area
{

    public function getActionCommand(): ActionCommand&HasFileSection;

    public function getSections(): array;

    public function getSectionArguments(Section $section): Configuration;

    public function getArguments(): Configuration;
}
