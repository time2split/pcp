<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\Config\Configurations;
use Time2Split\PCP\Action\IMoreActions;
use Time2Split\PCP\Action\MoreActions;

final class ConfigAction extends BaseAction
{

    public function onMessage(CContainer $ccontainer): IMoreActions
    {
        if ($ccontainer->isPCPPragma()) {
            $pcpPragma = $ccontainer->getPCPPragma();

            if ($pcpPragma->getCommand() === 'config')
                $this->doConfig($pcpPragma);
        }
        return MoreActions::empty();
    }

    private function doConfig(PCPPragma $pcpPragma): void
    {
        $args = $pcpPragma->getArguments();

        if ($args->isPresent('@expr')) {
            $id = $this->getExprIdentifier($pcpPragma, 'config');
            $args = clone $args;
            $args->unsetNode('@expr');
            $key = "@config.$id";
        } else {
            $key = [
                '@config',
                '@config@'
            ];
        }
        $args = Configurations::unmodifiable($args);

        foreach ((array)$key as $k)
            $this->config[$k] = $args;
    }
}
