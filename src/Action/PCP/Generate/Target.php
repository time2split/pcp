<?php
namespace Time2Split\PCP\Action\PCP\Generate;

interface Target
{

    public function getFileInfo(): \SplFileInfo;

    public function getAreas(): array;
}