<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\Config\Configurations;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\MoreActions;

final class ConfigAction extends BaseAction
{
    public function onCommand(ActionCommand $command): MoreActions
    {
        if ($command->getName() === 'config')
            $this->doConfig($command);

        return MoreActions::empty();
    }

    public function onMessage(CContainer $ccontainer): MoreActions
    {
        return MoreActions::empty();
    }

    private function doConfig(ActionCommand $command): void
    {
        $arguments = $command->getArguments();

        if ($arguments->isPresent('@expr')) {
            $id = $this->getExprIdentifier($command, 'config');
            $arguments = clone $arguments;
            $arguments->unsetNode('@expr');
            $key = "@config.$id";
        } else {
            $key = [
                '@config',
                '@config@'
            ];
        }
        $arguments = Configurations::unmodifiable($arguments);

        foreach ((array)$key as $k)
            $this->config[$k] = $arguments;
    }
}
