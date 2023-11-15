<?php
namespace DataFlow;

abstract class BaseSubscriber
{
    public function onSubscribe(): void
    {}
}