<?php
namespace C;

/**
 * PHP: C preprocessor
 *
 * @author zuri
 * @date 02/07/2022 12:36:56 CEST
 */
class PCP extends \DataFlow\BasePublisher
{

    private const configDefault = [
        'debug' => false,
        'generate.target' => null,
        'generate.prefix' => null,
        'cleaned' => null,
        'cpp.name' => 'zrlib'
    ];

    private array $config;

    private string $filePath;

    public function __construct(string $filePath, array $config = [])
    {
        parent::__construct();
        $this->filePath = $filePath;
        $this->config = \array_merge(self::configDefault, $config);

        $this->subscribe(new \Action\PCP\EchoAction());
    }

    private function deliverMessage(\Action\IActionMessage $d)
    {
        foreach ($this->getSubscribers() as $s)
            $s->onMessage($d);
    }

    private function updatePhase(\Action\PhaseName $name, \Action\PhaseState $state, $data = null)
    {
        foreach ($this->getSubscribers() as $s)
            $s->onPhase(\Action\Phase::create($name, $state), null);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function &getConfig(string ...$names)
    {
        if (empty($names))
            return $this->config;

        $ret = &$this->config;

        foreach ($names as $name)
            $ret = &$ret[$name];

        return $ret;
    }

    private function flush(array $pragmas): void
    {
        $actions = [];

        /*
         * Group pragmas by actions
         */
        foreach ($pragmas as $pragma) {
            $action = $pragma['action'];
            unset($pragma['action']);

            if (! isset($actions[$action]))
                $actions[$action] = [];

            $actions[$action][] = $pragma;
        }

        foreach ($actions as $action => $pragmas) {
            $fun = [
                $this,
                "flush_$action"
            ];

            if (\is_callable($fun)) {
                $fun($pragmas);
                return;
            }
            $class = "\Action\Action_$action";

            if (\class_exists($class)) {
                $instance = new $class($this);
                $instance->flush($pragmas);
                return;
            }
            throw new \InvalidArgumentException("'flush: invalid action '$action'");
        }
    }

    public function clean()
    {
        $actions = [
            'begin',
            'end'
        ];
        $pragmas = [];
        $file = \fopen($this->filePath, 'r');

        while (false !== ($pragma = $this->nextPragma($file))) {

            if (null !== $pragma && \in_array($pragma['action'], $actions))
                $pragmas[] = $pragma;
        }

        if (! empty($pragmas)) {
            echo "Clean $this->filePath\n";

            $tmpFile = \tmpfile();
            $lastPos = 0;

            for ($i = 0, $c = \count($pragmas); $i < $c; $i ++) {
                $pragma = $pragmas[$i];
                $action = $pragma['action'];

                if ($action !== 'begin')
                    throw new \InvalidArgumentException("'clean: action 'begin' attempted");

                $nextPragma = $pragmas[++ $i];
                $nextAction = $nextPragma['action'];

                if ($nextAction !== 'end')
                    throw new \InvalidArgumentException("'clean: action 'end' attempted");

                $pos = $pragma['pos'];
                \fwrite($tmpFile, \stream_get_contents($file, $pos - $lastPos, $lastPos));
                $lastPos = $nextPragma['pos'] + $nextPragma['len'];
            }
            \fwrite($tmpFile, \stream_get_contents($file, - 1, $lastPos));
            \fclose($file);
            $this->line_n = 0;

            $contents = \stream_get_contents($tmpFile, - 1, 0);
            \file_put_contents($this->filePath, $contents);
        }
    }

    public function process()
    {
        $creader = new \C\Reader($this->filePath);
        $this->updatePhase(\Action\PhaseName::ReadingOneFile, \Action\PhaseState::Start, \Action\PhaseData\ReadingOneFile::fromPath($this->filePath));
        $pragmas = [];
        $cppNameRef = $this->config['cpp.name'];
        $skip = false;

        $this->updatePhase(\Action\PhaseName::ReadingOneFile, \Action\PhaseState::Run, \Action\PhaseData\ReadingOneFile::fromPath($this->filePath));
        while (false !== ($element = $creader->next())) {
            $cursor = $element['cursor'];

            if ($element['type'] === 'cpp' && $element['directive'] === 'pragma') {
                $element['arguments'] = \Help\Args::parseString($element['text']);
                list ($cppName, $cmd) = $element['arguments'] + [
                    '',
                    '',
                    ''
                ];

                // Do not process unknownn #pragma
                if ($cppNameRef !== $cppName)
                    continue;

                // Avoid begin/end blocks
                if ($skip) {

                    if ($cmd === 'end')
                        $skip = false;

                    continue;
                } elseif ($cmd === 'begin') {
                    $skip = true;
                    continue;
                }
            } else {
                $this->deliverMessage(Declaration::from($element));
            }
        }
        $this->updatePhase(\Action\PhaseName::ReadingOneFile, \Action\PhaseState::Stop, \Action\PhaseData\ReadingOneFile::fromPath($this->filePath));
    }

    private function process_pragma(array $pragma, $file)
    {
        $action = $pragma['action'];
        $fun = [
            $this,
            "op_$action"
        ];

        if (\is_callable($fun))
            return $fun($pragma);

        $class = "\Action\Action_$action";

        if (\class_exists($class)) {
            $instance = new $class($this);
            return $instance->process($pragma, $file);
        }
        throw new \BadMethodCallException("zrlib: action: '$action' does not exists");
    }

    private function op_write(array $pragma)
    {
        return null;
    }

    private function op_end(array $pragma)
    {
        return null;
    }

    private function op_begin(array $pragma)
    {
        return null;
    }

    public function validate_generate_target(string $val)
    {
        if (empty($val))
            throw new \InvalidArgumentException("'generate.target' cannot be empty '/'");

        if ('.' === $val)
            return $this->filePath;

        if ('/' !== $val[0])
            throw new \InvalidArgumentException("'generate.target' must start by '/'");

        $val = \substr($val, 1);

        if (! \is_file($val))
            throw new \InvalidArgumentException("'generate.target' file '$val' must exists");

        return $val;
    }

    public function finalize_generate_target($val)
    {
        return (array) $val;
    }

    private function op_conf(array $pragma)
    {
        $query = $pragma['query'];
        $var = \array_shift($query);

        if (! \array_key_exists($var, $this->config))
            throw new \InvalidArgumentException("zrlib: action 'conf' invalid name '$var'");

        $suffixName = \str_replace([
            '.'
        ], '_', $var);
        $validate = [
            $this,
            "validate_$suffixName"
        ];
        $conf = [];

        while (null !== ($value = \array_shift($query))) {

            if ($value[0] === '"')
                $value = \substr($value, 1, - 1);

            if (\is_callable($validate))
                $value = $validate($value);

            $conf[] = $value;
        }

        if (\count($conf) === 1)
            $conf = $conf[0];

        $final = [
            $this,
            "finalize_$suffixName"
        ];

        if (\is_callable($final))
            $conf = $final($conf);

        $this->config[$var] = $conf;
        return null;
    }
}
