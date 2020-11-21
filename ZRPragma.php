<?php

class ZRPragma
{

	private const configDefault = [
		'debug' => false,
		'generate.target' => null,
		'generate.prefix' => null,
		'cleaned' => null
	];

	private array $config;

	private string $filePath;

	private int $line_n = 0;

	private int $char_n = 0;

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

	private function parseArgs(string $args): array
	{
		$args .= ' ';
		$len = strlen($args);
		$ret = [];
		$state = 0;
		$i = 0;

		$p = &$ret;

		$buffer = '';

		$pStack = [];
		$stateStack = [];
		$skipChar = false;

		$fpush = function (array &$stack, &$val) {
			$stack[] = &$val;
		};
		$fpop = function &(array &$stack) {
			$pos = count($stack) - 1;
			$val = &$stack[$pos];
			unset($stack[$pos]);
			return $val;
		};

		for (; $i < $len; $i ++) {
			$c = $args[$i];

			// echo "$c state($state) buffer($buffer)\n";

			switch ($state) {
			case 0:
				if (ctype_space($c));
				elseif ($c === '"') {
					$state = 20;
				}
				elseif ($c == '{') {
					$p[] = [];
					$fpush($pStack, $p);
					$p = &$p[count($p) - 1];
				}
				elseif ($c === '}') {
					$stackDepth = count($pStack);

					if ($stackDepth === 0)
						throw new \InvalidArgumentException("Invalid ')' character");

					$p = &$fpop($pStack);
				}
				else {
					$buffer = $c;
					$state = 10;
				}
				break;

			// A word is read
			case 10:
				if (ctype_space($c) || $c === '}') {
					$p[] = $buffer;
					$buffer = '';
					$state = 0;
					$i --;
				}
				else
					$buffer .= $c;
				break;

			// String
			case 20:

				if ($skipChar) {
					$skipChar = false;
					$buffer .= $c;
				}
				elseif ($c === "\\")
					$skipChar = true;
				elseif ($c === '"') {
					$p[] = $buffer;
					$buffer = '';
					$state = 0;
				}
				else
					$buffer .= $c;

				break;
			}
		}
		return $ret;
	}

	private function parseException(?string $msg): void
	{
		throw new \RuntimeException("$this->filePath: line($this->line_n) char($this->char_n) $msg");
	}

	private function skipJunk($file): void
	{
		while (true) {
			$this->char_n ++;
			$c = fgetc($file);

			if ($c === false)
				return;

			if ($c === "\n") {
				$this->line_n ++;
				$this->char_n = 0;
			}
			elseif ($c == '/') {
				$this->skipComment($file);
			}
			elseif (ctype_space($c)) {}
			else {
				fseek($file, - 1, SEEK_CUR);
				return;
			}
		}
	}

	/*
	 * A '/' as been read
	 */
	private function skipComment($file)
	{
		$state = 0;
		while (true) {
			$this->char_n ++;
			$c = fgetc($file);

			if ($c === false)
				$this->parseException("Waited for comment; end of file reached");

			if ($c === "\n") {
				$this->line_n ++;
				$this->char_n = 0;
			}
			switch ($state) {

			// Comment ?
			case 0:
				if ('/' === $c)
					$state = 1;
				elseif ('*' === $c)
					$state = 100;
				else
					$this->parseException("Waited for comment");
				break;
			case 1:
				if ("\n" === $c)
					return;
				break;
			// Multiline comment
			case 100:
				if ('*' === $c)
					$state ++;
				break;
			case 101:

				if ('/' === $c)
					return;
				elseif ('*' === $c);
				else
					$state --;
				break;
			}
		}
	}

	/**
	 * At begin next char of $file must be the start of a line
	 */
	public function nextDefineOrFunction($file): array
	{
		$this->skipJunk($file);
		$c = fgetc($file);
		fseek($file, - 1, SEEK_CUR);

		if ($c === '#') {
			return $this->nextDefine($file);
		}
		return $this->nextCFunction($file);
	}

	public function nextMacro($file, array $allowed = [])
	{
		$this->skipJunk($file);

		while (true) {
			$this->line_n ++;
			$pos = \ftell($file);
			$line_s = \fgets($file);
			$line_len = \strlen($line_s);

			if (false === $line_s)
				return false;

			$line_s = trim($line_s);

			if ('#' !== ($line_s[0] ?? null))
				continue;

			$words = \preg_split("/\s+/", \trim(\substr($line_s, 1)), 2);

			if (! \in_array(\array_shift($words), $allowed))
				continue;

			return [
				'pos' => $pos,
				'line' => $this->line_n,
				'query' => \array_shift($words),
				'len' => $line_len,
			];
		}
	}

	public function nextDefine($file)
	{
		$define = $this->nextMacro($file, [
			"define"
		]);
		if ($define === false)
			return false;

		$query = $define['query'];
		unset($define['query']);

		if (1 !== preg_match("/^(\w+)\(([^\)]*)\)(.*)$/", $query, $matches))
			return false;

		$args = preg_split("/[\s,]+/", $matches[2]);
		$define += [
			'name' => $matches[1],
			'arguments' => $args,
			'define' => trim($matches[3])
		];
		return $define;
	}

	public function nextPragma($file)
	{
		while (true) {
			$ret = $this->nextMacro($file, [
				'pragma'
			]);

			if ($ret === false)
				return false;

			$query = $ret['query'];
			$query = \preg_split("/\s+/", $ret['query'], 3);

			if ('zrlib' !== \array_shift($query))
				continue;

			$ret['action'] = \array_shift($query);
			$ret['query'] = $this->parseArgs(\trim(\array_shift($query)));
			return $ret;
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
			echo "{$pragma['line']} $this->filePath \n";

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
		\preg_match("/^(\w+[\s\*]*(?:\w+\s+)*)\(*[\(\s\*]*(\w*)/", $carg, $match);
		return [
			'type' => \rtrim($match[1]),
			'name' => $match[2],
			's' => $carg
		];
	}

	public function nextCFunction($file)
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
			else
				$read = true;

			// echo (int) $c . ": $c state($state) $read\n";

			if (false === $c)
				throw new \RuntimeException("End of file '$this->filePath' reached");

			switch ($state) {

			case 0:
				if ('/' === $c) {
					$this->skipJunk($file);
				}
				elseif ('#' === $c) {
					\fgets($file);
					$read = false;
					$c = "\n";
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
						$this->skipJunk($file);
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
						$this->parseException("Waited for an argument");
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
					$this->skipJunk($file);
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
