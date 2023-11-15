<?php
namespace DataFlow;

abstract class BasePublisher
{

    private \SplObjectStorage $subscribersSet;

    private array $subscribers;

    protected function __construct()
    {
        $this->subscribersSet = new \SplObjectStorage();
        $this->subscribers = [];
    }

    public final function subscribe(ISubscriber $s): void
    {
        if (! $this->subscribersSet->contains($s)) {
            $this->subscribersSet->attach($s);
            $this->subscribers[] = $s;
            $s->onSubscribe();
        }
    }

    protected function getSubscribers(): array
    {
        return $this->subscribers;
    }
}