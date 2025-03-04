<?php

namespace Time2Split\PCP\C\Element;

interface CPPDefine extends CPPDirective
{
    public function getID(): string;

    public function getTokensText(): string;

    public function getParametersText(): string;

    public function getParameters(): array;
}
