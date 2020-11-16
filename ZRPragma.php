<?php

class ZRPragma
{
	private const configDefault = [
		'debug' => false,
		'generate.target' => null,
		'generate.prefix' => null,
		'cleaned' => null,
	];

	private array $config;

	private string $filePath;

	private int $line_n = 0;

	public function __construct(string $filePath, array $config = [])
	{
		$this->filePath = $filePath;
		$this->config = self::configDefault;
		$this->config = \array_merge($this->config, $config);
	}

	public function __destruct()
	{}

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

	public function nextPragma($file)
	{
		while (true) {
			$this->line_n ++;
			$pos = \ftell($file);
			$line_s = \fgets($file);

			if (false === $line_s)
				return false;

			if ('#' !== ($line_s[0] ?? null))
				continue;

			$words = \preg_split("/[\s]+/", \trim(\substr($line_s, 1)));

			if (\array_shift($words) !== 'pragma' || \array_shift($words) !== 'zrlib')
				continue;

			$action = \array_shift($words);
			return [
				'pos' => $pos,
				'line' => $this->line_n,
				'action' => $action,
				'query' => $words,
				'len' => \strlen($line_s)
			];
		}
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
			$class = "ZRAction_$action";

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
		$file = \fopen($this->filePath, 'r');
		$pragmas = [];

		while (false !== ($pragma = $this->nextPragma($file))) {
			try {
				$pragma = $this->process_pragma($pragma, $file);
			} catch (\Exception $e) {
				throw new \Exception("Processing file '$this->filePath' on line $this->line_n", 0, $e);
			}

			if (null !== $pragma)
				$pragmas[] = $pragma;
		}
		\fclose($file);
		$this->line_n = 0;

		if (! empty($pragmas))
			$this->flush($pragmas);
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

		$class = "ZRAction_$action";

		if (\class_exists($class)) {
			$instance = new $class($this);
			return $instance->process($pragma, $file);
		}
		throw new \BadMethodCallException("zrlib: action: '$action' does not exists");
	}

	private function makeArg(string $carg): array
	{
		\preg_match("/^(\w+[\s\*]*(?:\w+\s+)*)\(*[\(\s\*]*(\w+)/", $carg, $match);
		return [
			'type' => \rtrim($match[1]),
			'name' => $match[2],
			's' => $carg
		];
	}

	public function getNextCFunction($file)
	{
		$state = 0;
		$char_n = 0;
		$words = [];
		$args = [];
		$funcName = "";
		$buffer = "";
		$read = true;

		$fnewWord = function () use (&$words, &$buffer) {
			if ('' !== $buffer) {
				$words[] = $buffer;
				$buffer = '';
			}
		};
		$fnewArg = function () use (&$args, &$buffer) {
			if ('' !== $buffer) {
				$args[] = $a = $this->makeArg(trim($buffer));
				$buffer = '';
			}
		};
		$fexception = function () use (&$char_n) {
			throw new \RuntimeException("$this->filePath: line($this->line_n) char($char_n) waited for function");
		};
		$state_stack = [];

		for (;;) {

			if ($read) {
				$char_n ++;
				$c = \fgetc($file);

				if ($c === "\n") {
					$this->line_n ++;
					$char_n = 0;
				}
			}
			// echo "$c state($state)\n";

			if (false === $c)
				throw new \RuntimeException("End of file '$this->filePath' reached");

			switch ($state) {

			case 0:
				if ('/' === $c) {
					$state = 1000;
					\array_push($state_stack, 0);
				}
				elseif ('#' === $c) {
					\fgets($file);
					$fnewLine();
				}
				elseif (\ctype_space($c));
				else {
					$buffer .= $c;
					$state = 10;
				}
				break;

			// Register words
			case 10:
				if (\ctype_punct($c)) {

					if ($c === '/') {
						$fnewWord();
						$state = 1000;
						\array_push($state_stack, 10);
					}
					elseif ('_' === $c)
						$buffer .= $c;
					elseif ('*' === $c) {
						$fnewWord();
						$words[\count($words) - 1] .= '*';
					}
					elseif ('(' === $c) {
						$funcName = $buffer;
						$buffer = '';
						$state = 20;
					}
					else
						$fexception();
				}
				elseif (\ctype_space($c)) {
					$fnewWord();
				}
				else
					$buffer .= $c;
				break;

			// Register arguments
			case 20:

				if ('(' === $c) {
					$state ++;
					$buffer .= $c;
					\array_push($state_stack, 20);
				}
				elseif ('/' === $c) {
					$state = 1000;
					\array_push($state_stack, 20);
				}
				elseif (',' === $c)
					$fnewArg();
				elseif (')' === $c) {
					$fnewArg();
					break 2;
				}
				else
					$buffer .= $c;
				break;

			case 21:

				if (')' === $c) {
					$state = \array_pop($state_stack);
				}
				elseif ('(' === $c)
					\array_push($state_stack, 21);
				$buffer .= $c;
				break;

			// Comment ?
			case 1000:
				if ('/' === $c)
					$state = 1001;
				elseif ('*' === $c)
					$state = 1100;
				else
					$fexception();
				break;
			case 1001:
				if ("\n" === $c) {
					$fnewLine();
					$state = \array_pop($state_stack);
				}
				break;
			// Multiline comment
			case 1100:
				if ('*' === $c)
					$state ++;
				break;
			case 1101:

				if ('/' === $c)
					$state = \array_pop($state_stack);
				elseif ('*' === $c);
				else
					$state --;
				break;
			}
		}
		return [
			'name' => $funcName,
			'qualifiers' => $words,
			'arguments' => $args
		];
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
