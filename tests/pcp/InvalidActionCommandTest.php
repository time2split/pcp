<?php

declare(strict_types=1);

namespace Time2Split\PCP\Tests\pcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Time2Split\PCP\Action\InvalidActionCommandException;
use Time2Split\PCP\App;
use Time2Split\PCP\App\Bootstrap;
use Time2Split\PCP\PCP;

final class InvalidActionCommandTest extends TestCase
{
    private const WDir =  __DIR__ . '/../../tests.wd/invalid';

    private const WFile =  'invalid.c';

    public static function provideInvalidActionCommand(): array
    {
        return [
            'bad generate' => ['#pragma pcp generate badgenerate', InvalidActionCommandException::class],
        ];
    }

    #[DataProvider("provideInvalidActionCommand")]
    public function testInvalidActionCommand(string $actionCommand, string $exceptionClass)
    {
        assert(
            $exceptionClass === InvalidActionCommandException::class
                || \is_subclass_of($exceptionClass, InvalidActionCommandException::class)
        );

        $wdir = self::WDir;
        $filePath =  "$wdir/" . self::WFile;

        if (!\is_dir($wdir))
            \mkdir($wdir);

        \file_put_contents($filePath, $actionCommand);

        $config = App::emptyConfiguration();
        $config->merge([
            PCP::CONFIG_FS_ROOT => $wdir,
            PCP::CONFIG_PCP_ACTION => 'process',
            PCP::CONFIG_SOURCE_PATHS => '.',
        ]);
        $pcp = new PCP($config, Bootstrap::getProcessActions());

        $this->expectException($exceptionClass);
        $pcp->process();
    }
}
