<?php
namespace Action;

final class ActionFactory
{

    private \Data\IConfig $config;

    private function __construct(\Data\IConfig $config)
    {
        $this->config = $config;
    }

    public static function get(\Data\IConfig $config)
    {
        return new self($config);
    }

    public function getActions(): array
    {
        $action = $this->config['action'] ?? 'process';

        return match ($action) {
            'process' => [
                // new \Action\PCP\EchoAction($this->config),
                new \Action\PCP\Generate($this->config)
            ],
            'clean' => [
                new \Action\PCP\GenerateClean($this->config)
            ],
            default => []
        };
    }
}