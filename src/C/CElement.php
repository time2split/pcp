<?php
namespace Time2Split\PCP\C;

use Time2Split\PCP\C\Element\CElementType;

interface CElement
{

    function getElementType(): CElementType;
}