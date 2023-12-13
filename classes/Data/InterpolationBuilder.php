<?php
namespace Data;

final class InterpolationBuilder
{

    private array $groups;

    private function __construct(array $groups)
    {
        $this->groups = $groups;

        foreach ($groups as $v) {
            if (\is_array($v) || $v instanceof \ArrayAccess || \is_callable($v))
                continue;

            throw new \Exception(__class__ . " Invalid value: " . print_r($v, true));
        }
    }

    public static function create(array $groups): self
    {
        return new self($groups);
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function for(mixed $interpolation): mixed
    {
        if (\is_resource($interpolation) && \get_resource_id($interpolation) === 'stream')
            $fp = $interpolation;
        else
            $fp = \Help\IO::stringToStream($interpolation);

        $this->fp = $fp;
        $ret = [];

        do {
            $update = false;
            $s = $this->nextString();

            if (isset($s)) {
                $ret[] = $s;
                $update = true;
            }
            $i = $this->nextInterpolation();

            if (! empty($i)) {
                $ret[] = $i;
                $update = true;
            }
        } while ($update);

        $c = \count($ret);
        if ($c === 0)
            return $interpolation;
        if ($c === 1)
            return $ret[0];

        return new class($ret) extends Interpolation {

            private array $ints;

            function __construct(array $interpolations)
            {
                parent::__construct("", "", \Help\NullValue::v);
                $this->ints = $interpolations;
            }

            public function get(): mixed
            {
                return implode('', \array_map(fn ($i) => (string) $i, $this->ints));
            }
        };
    }

    private $fp;

    private function nextString(): ?string
    {
        return \Help\FIO::streamGetChars($this->fp, fn ($c) => $c !== '$');
    }

    private function skipSpaces(): void
    {
        \Help\FIO::streamSkipChars($this->fp, \ctype_space(...));
    }

    private function ungetc(): bool
    {
        return \Help\FIO::streamUngetc($this->fp);
    }

    private function nextChar(): string|false
    {
        $this->skipSpaces();
        return \fgetc($this->fp);
    }

    private function nextWord(): string
    {
        $this->skipSpaces();
        return \Help\FIO::streamGetChars($this->fp, fn ($c) => \ctype_alnum($c) || $c === '=' || $c === '.');
    }

    private function nextInterpolation(): ?Interpolation
    {
        $states = [
            0
        ];

        while (true) {
            $state = \array_pop($states);

            switch ($state) {
                case - 1:
                    return null;

                case 0:
                    $c = $this->nextChar();

                    if ($c === '$')
                        \array_push($states, 1);
                    else
                        \array_push($states, - 1);
                    break;

                case 1:
                    $c = $this->nextChar();

                    if ($c === '{') {
                        \array_push($states, 2);
                        \array_push($states, 10);
                    } else
                        \array_push($states, - 1);
                    break;

                case 2:
                    $c = $this->nextChar();

                    if ($c === '}')
                        return $interpolation;
                    else
                        \array_push($states, - 1);
                    break;

                // Group reading
                case 10:
                    $group = $this->nextWord();

                    \array_push($states, 11);
                    break;

                // Check group
                case 11:
                    $c = $this->nextChar();

                    if ($c === ':') {
                        $key = $this->nextWord();
                    } else {
                        $key = $group;
                        $group = '';
                        $this->ungetc();
                    }
                    \array_push($states, 20);
                    break;

                // Do the interpolation
                case 20:
                    $data = $this->groups[$group];

                    if (\is_array($data) || $data instanceof \ArrayAccess) {
                        $interpolation = //
                        new class($data, $group, $key) extends Interpolation {

                            private array|\ArrayAccess $data;

                            function __construct(array|\ArrayAccess $data, string $group, string $key)
                            {
                                parent::__construct($group, $key);
                                $this->data = $data;
                            }

                            function get(): mixed
                            {
                                return $this->data[$this->key] ?? null;
                            }
                        };
                    } elseif (\is_callable($data)) {
                        $interpolation = //
                        new class($data, $group, $key) extends Interpolation {

                            private $data;

                            function __construct(callable $data, string $group, string $key)
                            {
                                parent::__construct($group, $key);
                                $this->data = $data;
                            }

                            function get(): mixed
                            {
                                return ($this->data)($this->key);
                            }
                        };
                    } else
                        throw new \Error(__class__ . "Cannot interpolate the value: " . print_r($data) . "[$group]");
                    break;
            }
        }
    }
}