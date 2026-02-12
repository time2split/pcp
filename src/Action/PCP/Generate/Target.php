<?php

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\PCP\FS\FSFile;

interface Target
{
    public function getFSFile(): FSFile;
}
