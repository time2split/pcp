<?php

declare(strict_types=1);

namespace Time2Split\PCP\App;

use Exception;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\Action\ActionCommandReader;
use Time2Split\PCP\Action\PCP\BlockAction;
use Time2Split\PCP\Action\PCP\ConfigAction;
use Time2Split\PCP\Action\PCP\ForAction;
use Time2Split\PCP\Action\PCP\Generate;
use Time2Split\PCP\Action\PCP\GenerateClean;
use Time2Split\PCP\App;
use Time2Split\PCP\PCP;

final class Bootstrap
{
    use NotInstanciable;

    public static function bootstrap(array $argv): void
    {
        $text = \implode(' ', $argv);
        $reader = ActionCommandReader::ofString($text);
        $command = $reader->next();

        if (null === $command)
            throw new \Exception("Waiting for a command");
        if (null !== ($c = $reader->next()))
            throw new Exception("Unexpected command $c; for now only one command is allowed as input");

        unset($text, $reader, $c);
        $config = $command->getArguments();

        switch ($command->getName()) {
            case 'process':
                $pcpActions = self::getProcessActions();
                break;

            case 'clean':
                $pcpActions = self::getCleanActions();
                break;

            default:
                throw new \Exception("Unexpected command '{$command->getName()}'");
        }
        $pcp = new PCP($config, $pcpActions);
        $pcp->process();
    }

    public static function getProcessActions(): array
    {
        $config ??= App::emptyConfiguration();
        return [
            new Generate(),
            new ForAction(),
            new BlockAction(),
            new ConfigAction(),
        ];
    }

    public static function getCleanActions(): array
    {
        $config ??= App::emptyConfiguration();
        return [
            new GenerateClean(),
        ];
    }
}
