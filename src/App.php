<?php

namespace Time2Split\PCP;

use Parsica\Parsica\Parser;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Config\Interpolator;
use Time2Split\Config\TreeConfigurationBuilder;
use Time2Split\Help\Iterables;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Streams;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\ActionCommandReader;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\C\PCPReader;
use Time2Split\PCP\Expression\Expressions;
use Time2Split\PCP\File\StreamInsertion;
use Time2Split\PCP\File\_internal\StreamInsertionImpl;
use Time2Split\PCP\File\CursorPosition;
use Time2Split\PCP\File\HasFileSection;
use Time2Split\PCP\File\Section;

final class App
{
    use NotInstanciable;

    // ========================================================================

    private static function availableFilters(): array
    {
        return [
            'replace' => function (string $subject, string $search, string $replace): string {
                return \str_replace($search, $replace, $subject);
            }
        ];
    }

    public static function expressions(): Parser
    {
        static $ret = Expressions::expression(self::availableFilters());
        return $ret;
    }

    public static function expressionsInterpolator(): Interpolator
    {
        static $ret = Expressions::interpolator(self::availableFilters());
        return $ret;
    }

    public static function expressionsArguments(): Parser
    {
        static $ret = Expressions::arguments(self::availableFilters());
        return $ret;
    }

    // ========================================================================

    public static function emptyConfiguration(): Configuration
    {
        return self::getConfigBuilder()->build();
    }

    public static function getConfigBuilder(array $default = []): TreeConfigurationBuilder
    {
        return Configurations::builder()
            ->setKeyDelimiter('.')
            ->setInterpolator(self::expressionsInterpolator())
            ->mergeTree($default);
    }

    public static function fileInsertion(string $file, string $buffFile): StreamInsertion
    {
        copy($file, $buffFile);
        return StreamInsertionImpl::fromFilePath($buffFile, $file);
    }

    public static function textToParameters($stream): Configuration
    {
        $parameters = self::emptyConfiguration();

        if (is_resource($stream)) {
            $text = \stream_get_contents($stream);
        } else {
            $text =  (string)$stream;
        }

        try {
            self::expressionsArguments()->tryString($text)
                ->output()
                ->get($parameters);
        } catch (\Exception $e) {
            //  {$cursors->begin})
            throw new \Exception("Unable to parse the text as parameters: '$text' ; {$e->getMessage()}");
        }
        return $parameters;
    }

    // ========================================================================

    public static function creaderOfFile(string|\SplFileInfo $file, array $pcpNames): PCPReader
    {
        return self::creaderWrapper(CReader::ofFile($file), $pcpNames);
    }

    public static function creaderOfStream($stream, array $pcpNames): PCPReader
    {
        return self::creaderWrapper(CReader::ofStream($stream), $pcpNames);
    }

    public static function creaderOfString($string, array $pcpNames): PCPReader
    {
        return self::creaderWrapper(CReader::ofString($string), $pcpNames);
    }

    private static function creaderWrapper(CReader $creader, array $pcpNames): PCPReader
    {
        return new class($creader, $pcpNames) implements PCPReader {

            public function __construct(
                private CReader $reader,
                private array $pcpNames,
            ) {}

            public function getCursorPosition(): CursorPosition
            {
                return $this->reader->getCursorPosition();
            }

            public function __destruct()
            {
                $this->close();
            }

            public function next(): CElement|ActionCommand|null
            {
                $next = $this->reader->next();

                if ($next instanceof CPPDirective && $next->getDirective() === 'pragma') {
                    $stream = Streams::stringToStream($next->getText());
                    $first = Streams::streamGetCharsUntil($stream, \ctype_space(...));

                    if (\in_array($first, $this->pcpNames)) {
                        $cmdReader = ActionCommandReader::ofStream($stream);
                        $command = $cmdReader->next();
                        assert(null !== $command);
                        assert(null === $cmdReader->next());

                        return new class(
                            $command->getName(),
                            $command->getArguments(),
                            $next->getFileSection()
                        )
                        extends ActionCommand
                        implements HasFileSection
                        {
                            public function __construct(
                                string $name,
                                Configuration $arguments,
                                private Section $section
                            ) {
                                parent::__construct($name, $arguments);
                            }

                            public  function getFileSection(): Section
                            {
                                return $this->section;
                            }
                        };
                    }
                }
                return $next;
            }

            public function close(): void
            {
                $this->reader->close();
            }
        };
    }

    // ========================================================================

    public static function configShift(Configuration $config, int $nb = 1): Configuration
    {
        $ret = clone $config;

        if ($nb === 0)
            return $ret;

        if ($nb < 0)
            throw new \ValueError(__FUNCTION__ . " \$nb must be a positive or a zero integer");

        $keys = Iterables::keys($config);

        foreach ($keys as $k) {

            if (!isset($k))
                break;

            unset($ret[$k]);

            if (--$nb === 0)
                return $ret;
        }
        throw new \AssertionError();
    }
}
