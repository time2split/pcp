<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\Config\Configurations;

final class ConfigAction extends BaseAction
{

    public function onMessage(CContainer $ccontainer): array
    {
        if ($ccontainer->isPCPPragma()) {
            $pcpPragma = $ccontainer->getPCPPragma();

            if ($pcpPragma->getCommand() === 'config')
                $this->doConfig($pcpPragma);
        }
        return [];
    }

    private function doConfig(PCPPragma $pcpPragma): void
    {
        $args = $pcpPragma->getArguments();

        if ($args->isPresent('@expr')) {
            $id = $this->getExprIdentifier($pcpPragma, 'config');
            $args = clone $args;
            $args->removeNode('@expr');
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
