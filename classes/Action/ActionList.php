<?php
namespace Action;

final class ActionList
{

    private array $actions = [];

    public function addAction(array $actionElement, array $targetElement): void
    {
        $this->actions[] = [
            $actionElement,
            $targetElement
        ];
    }

    public function process(): void
    {
        error_dump($this->actions);
    }
}