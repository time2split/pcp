<?php

declare(strict_types=1);

namespace Time2Split\Help\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\Tests\DataProvider\Provided;
use Time2Split\PCP\App;
use Time2Split\PCP\C\CDeclarationGroup;
use Time2Split\PCP\C\CDeclarationType;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\C\Element\CPPDirectives;
use Time2Split\PCP\File\Section;

final class CReaderTest extends TestCase
{

    private static function  pcpConfig(): Configuration
    {
        return App::configuration(['pcp.name' => 'pcp']);
    }

    private static function arrayOfCReaderFactory(): array
    {
        return [
            new Provided("CReader", [fn(string $stream) => CReader::fromString($stream)]),
            new Provided("CPPDirectives", [function (string $stream) {
                $reader =  CReader::fromString($stream);
                $reader->setCPPDirectiveFactory(CPPDirectives::factory(self::pcpConfig()));
                return $reader;
            }]),
        ];
    }

    // ========================================================================

    public static function skipUselessCodeProvider(): iterable
    {
        $array = [
            new Provided('empty', ['']),
            new Provided('spaces', [" \t\n\r "]),
            new Provided('comment', ['// commentary']),
            new Provided('mcomment', [
                <<<END
            /*
             * some comment
             */
            END
            ]),
            new Provided('bad', ['&unknowned stuff@']),
            new Provided('block', [
                <<<END
                {
                    char *a = "test";
                }
                END
            ]),
            new Provided('def var', ['int a = 1;']),
        ];
        return Provided::merge(self::arrayOfCReaderFactory(), $array);
    }

    #[DataProvider("skipUselessCodeProvider")]
    public function testskipUselessCode(callable $factory, string $useless): void
    {
        $creader = $factory($useless);
        $this->assertNull($creader->next());
    }

    // ========================================================================

    private static function provideCPPDirective(string $directive, string $text, string $expectedText = null): array
    {
        return ["#$directive $text", CPPDirective::create($directive, $expectedText ?? $text, Section::zero())];
    }

    public static function readCPPDirectiveProvider(): iterable
    {
        $array = [
            new Provided('constant', self::provideCPPDirective('define', 'A 15')),
            new Provided('mconstant', self::provideCPPDirective(
                'define',
                <<<END
A \
15
END
            )),
        ];
        return Provided::merge(self::arrayOfCReaderFactory(), $array);
    }

    #[DataProvider("readCPPDirectiveProvider")]
    public function testReadCPPDirective(callable $factory, string $code, CPPDirective $expect): void
    {
        $creader = $factory($code);
        $cpp = $creader->next();
        $this->assertInstanceOf(CPPDirective::class, $cpp);
        $this->assertEquals($expect->getDirective(), $cpp->getDirective());
        $this->assertEquals($expect->getText(), $cpp->getText());
    }

    // ========================================================================
    /*
    public static function exceptionsProvider(): iterable
    {
        $array = [
            new Provided('EOL', ["#define A \\a", \RuntimeException::class]),
        ];
        return Provided::merge(self::arrayOfCReaderFactory(), $array);
    }

    #[DataProvider("exceptionsProvider")]
    public function testExceptions(callable $factory, string $code, string $expectedException): void
    {
        $this->expectException($expectedException);
        $creader = $factory($code);
        $creader->next();
    }
    // */
    // ========================================================================

    private static function CElementsForOneExpectation(array $expect, array ...$headerAndCodes): array
    {
        $ret = [];
        foreach ($headerAndCodes as $headerAndCode)
            $ret[] = self::CElementForOneExpectation($expect, $headerAndCode[0], $headerAndCode[1]);
        return $ret;
    }

    private static function CElementForOneExpectation(array $expect, string $header, string $code): Provided
    {
        return new Provided($header, [$code, $expect]);
    }

    public static function CElementTypeProvider(): iterable
    {
        $array = [
            ...self::CElementsForOneExpectation(
                [
                    'group' => CDeclarationGroup::declaration,
                    'type' => CDeclarationType::tvariable,
                ],
                ['var', 'int a;'],
                ['c var', 'const int a;'],
                ['(var)', 'int (a);'],
                ['*var', 'int *a;'],
                ['(*var)', 'int (*a);'],
                ['*(var)', 'int *(a);'],
                ['var[]', 'int a[];'],
                ['var[0]', 'int a[0];'],
                ['var[0][0]', 'int a[0][0];'],
            ),
            ...self::CElementsForOneExpectation(
                [
                    'group' => CDeclarationGroup::declaration,
                    'type' => CDeclarationType::tfunction,
                ],
                ['f()', 'int f();'],
                ['f(a)', 'int f(int a);'],
                ['f(a,b)', 'int f(int a, int b);'],
                ['(*var)()', 'int (*a)();'], // TODO: Must be a tvariable
            ),
            ...self::CElementsForOneExpectation(
                [
                    'group' => CDeclarationGroup::definition,
                    'type' => CDeclarationType::tfunction,
                ],
                ['f(){}', 'int f(){ return 0; }'],
                ['f(a){}', 'int f(int a){ return a ;}'],
                ['f(a,b){}', 'int f(int a, int b){ return a + b; }'],
            ),
        ];
        return Provided::merge(self::arrayOfCReaderFactory(), $array);
    }

    #[DataProvider("CElementTypeProvider")]
    public function testCElementType(callable $factory, string $code, array $expect): void
    {
        $creader = $factory($code);
        $celement = $creader->next();
        $this->assertInstanceOf(CDeclaration::class, $celement);

        $carray = $celement->getArrayCopy();
        $carray = \array_intersect_key($carray, $expect);
        $this->assertEquals($expect, $carray);
    }

    // ========================================================================

    public static function CElementSpecifiersAndIdentifier(): iterable
    {
        $array = [
            self::CElementForOneExpectation(
                [
                    // 'infos.specifiers.nb' => 1,
                    'identifier.pos' => 1,
                ],
                'var',
                'int a;',
            ),
            self::CElementForOneExpectation(
                [
                    'identifier.pos' => 2,
                ],
                'c var',
                'const int a;',
            ),
            self::CElementForOneExpectation(
                [
                    'identifier.pos' => 2,
                ],
                '(var)',
                'int (a);',
            ),
            self::CElementForOneExpectation(
                [
                    'identifier.pos' => 2,
                ],
                '*var',
                'int *a;',
            ),
            self::CElementForOneExpectation(
                [
                    'identifier.pos' => 3,
                ],
                '(*var)',
                'int (*a);',
            ),
            self::CElementForOneExpectation(
                [
                    'identifier.pos' => 3,
                ],
                '*(var)',
                'int *(a);',
            ),
            self::CElementForOneExpectation(
                [
                    'identifier.pos' => 1,
                ],
                'f()',
                'int f();',
            ),
        ];
        return Provided::merge(self::arrayOfCReaderFactory(), $array);
    }

    #[DataProvider("CElementSpecifiersAndIdentifier")]
    public function testCElementSpecifiersAndIdentifier(callable $factory, string $code, array $expect): void
    {
        $creader = $factory($code);
        $celement = $creader->next();
        $this->assertInstanceOf(CDeclaration::class, $celement);

        $carray = Configurations::ofTree($celement->getArrayCopy())->toArray();
        $carray = \array_intersect_key($carray, $expect);
        $this->assertEquals($expect, $carray);
    }

    // ========================================================================
}
