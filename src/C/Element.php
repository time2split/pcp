<?php
namespace Time2Split\PCP\C;

use Time2Split\PCP\C\Element\ElementType;

interface Element
{

    function getElementType(): ElementType;
}