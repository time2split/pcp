<?php
namespace Time2Split\PCP\Action;

use Time2Split\Config\IConfig;

final class ActionFactory
{

    private IConfig $config;

    private function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    public static function get(IConfig $config)
    {
        return new self($config);
    }

    public function getActions(): array
    {
        $action = $this->config['action'] ?? 'process';

        return match ($action) {
            'process' => [
                // new \Action\PCP\EchoAction($this->config),
                new PCP\Generate($this->config)
            ],
            'clean' => [
                new PCP\GenerateClean($this->config)
            ],
            default => []
        };
    }
}