<?php
namespace C;

use C\Element\ElementType;

interface Element
{

    function getElementType(): ElementType;
}