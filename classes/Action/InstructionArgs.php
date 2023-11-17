<?php
namespace Action;

final class InstructionArgs
{

    private \File\Navigator $fnav;

    private function __construct(\File\Navigator $fnav)
    {
        $this->fnav = $fnav;
    }

    public static function parse($fp): array
    {
        if (is_string($fp))
            $fp = \Help\IO::stringToStream($fp);

        $parser = new self(\File\Navigator::fromStream($fp));
        return $parser->parseStream();
    }

    private function parseStream(): array
    {
        $state = 0;
        $key = null;
        $value = null;
        $args = [];

        for (;;) {
            switch ($state) {

                // start
                case 0:
                    $c = $this->nextChar();

                    if ($c === false)
                        break 2;

                    if ($c === '+') {
                        $value = true;
                        $state = 5;
                    } elseif ($c === '-') {
                        $value = false;
                        $state = 5;
                    } elseif (null !== ($key = $this->nextElement($c))) {
                        $state = 10;
                    } else
                        throw new \Exception("Invalid '$c' char");
                    break;

                // Bool
                case 5:
                    $c = $this->nextChar();

                    if (null !== ($key = $this->nextElement($c))) {
                        $args[$key] = $value;
                        $state = 0;
                    } else
                        throw new \Exception("Invalid '$c' char");
                    break;

                // A key is set
                case 10:
                    $c = $this->nextChar();

                    if ($c === ':' || $c === '=') {
                        $state = 20;
                    } else {
                        $this->fnav->ungetc();
                        $args[] = self::interpret($key);
                        $key = null;
                        $state = 0;
                    }
                    break;

                // Waiting value
                case 20:
                    $c = $this->nextChar();

                    if (null !== ($value = $this->nextElement($c))) {
                        $state = 21;
                        $args[$key] = self::interpret($value);
                    } else
                        throw new \Exception();
                    break;

                // Check value list
                case 21:
                    $c = $this->nextChar();

                    if ($c === false)
                        break 2;

                    if ($c === ',') {
                        $args[$key] = (array) $args[$key];
                        $state = 22;
                    } else {
                        $this->fnav->ungetc();
                        $state = 0;
                    }
                    break;

                case 22:
                    $c = $this->nextChar();

                    if ($c === false)
                        break 2;
                    if (null !== ($value = $this->nextElement($c))) {
                        $state = 21;
                        $args[$key][] = self::interpret($value);
                    } else {
                        $this->fnav->ungetc();
                        $state = 0;
                    }
                    break;
            }
        }
        $this->fnav->close();
        return $args;
    }

    private static function interpret($value)
    {
        if (\is_numeric($value))
            return \intval($value);

        return $value;
    }

    private function nextElement($c): ?string
    {
        if ($c === '"')
            return $this->fnav->getCharsUntil('"');

        return $c . $this->nextWord();
    }

    private function nextChar()
    {
        while (false !== ($c = $this->fnav->getc()) && \ctype_space($c));
        return $c;
    }

    private static function ctype_word(string $c)
    {
        return ! \ctype_space($c) && \strpos(':=,', $c) === false;
    }

    private function nextWord()
    {
        return $this->fnav->getChars(fn ($c) => self::ctype_word($c));
    }
}