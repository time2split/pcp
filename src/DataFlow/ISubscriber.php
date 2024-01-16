<?php
namespace Time2Split\PCP\DataFlow;

interface ISubscriber
{

    function onNext($data): void;

    function onSubscribe(): void;
}