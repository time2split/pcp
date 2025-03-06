<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\ActionCommandReader;

final class ActionCommandReaderTest extends TestCase
{

    private static function getActionCommandData(): array
    {
        return [
            ['name', 'name', []],
            [' name ', 'name', []],
            ['name p1="v1"', 'name', ['p1' => 'v1']],
            ["name\\\np1=\"v1\"", 'name', ['p1' => 'v1']],
        ];
    }

    public static function actionCommandProvider(): \Traversable
    {
        return (function () {
            foreach (self::getActionCommandData() as $data) {
                [$text] = $data;
                yield "$text" => $data;
            }
        })();
    }

    #[DataProvider("actionCommandProvider")]
    public  function testParseOneCommand(string $text, string $expectCommand, array $expectArguments): void
    {
        $creader = ActionCommandReader::ofString($text);
        $command = $creader->next();

        $this->assertInstanceOf(ActionCommand::class, $command);
        $this->assertEquals($expectCommand, $command->getName());
        $this->assertSame($expectArguments, $command->getArguments()->toArray());
    }
}
