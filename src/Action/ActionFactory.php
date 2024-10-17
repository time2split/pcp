<?php

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;

final class ActionFactory
{

    private Configuration $config;

    private function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public static function get(Configuration $config)
    {
        return new self($config);
    }

    public function getActions(string $action): array
    {
        return match ($action) {
            'process' => [
                // new PCP\EchoAction($this->config),
                new PCP\Generate($this->config),
                new PCP\ForAction($this->config),
                new PCP\ConfigAction($this->config)
            ],
            'clean' => [
                new PCP\GenerateClean($this->config)
            ],
            default => []
        };
    }
}
