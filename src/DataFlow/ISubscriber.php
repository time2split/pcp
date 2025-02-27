<?php

namespace Time2Split\PCP\DataFlow;

interface ISubscriber
{
    function onSubscribe(): void;
}
