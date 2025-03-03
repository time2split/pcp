<?php

namespace Time2Split\PCP\C\Element;

interface CPPDefine extends CPPDirective
{
    public function isFunction(): bool;

    public function getMacroParameters(): array;

    public function getMacroContents();
}
