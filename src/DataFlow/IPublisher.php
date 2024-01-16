<?php
namespace Time2Split\PCP\DataFlow;

interface IPublisher
{

    function subscribe(ISubscriber $s): void;
}