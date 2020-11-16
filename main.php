<?php
\spl_autoload_register(function ($class) {
	$class = \str_replace('\\', '/', $class);
	include __DIR__ . "/$class.php";
});

$CONFIG = [
	'pragmas_fileConfig' => __DIR__ . '/config.php',
	'debug' => false,
	'paths' => [
		'zrlib',
		'src'
	]
];

function loadConfig(string $filePath): array
{
	if (! \is_file($filePath))
		return [];

	return include $filePath;
}

function saveConfig(string $filePath, $config): void
{
	\file_put_contents($filePath, "<?php return " . \var_export($config, true) . ';');
}

function getFiles_c(array $CONFIG): array
{
	$files = [];

	foreach ($CONFIG['paths'] as $path) {
		$dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME);
		$dirIterator = new \RegexIterator(new \RecursiveIteratorIterator($dirIterator), "/^.+\.[hc]/");
		$files += \iterator_to_array($dirIterator);
	}
	return \array_values($files);
}

function getFiles_php(array $CONFIG): array
{
	$files = [];

	foreach ($CONFIG['paths'] as $path) {
		$dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME);
		$dirIterator = new \RegexIterator(new \RecursiveIteratorIterator($dirIterator), "/^.+\.[hc]\.php/");
		$files += \iterator_to_array($dirIterator);
	}
	return \array_values($files);
}

function clean_php(array $files, array $CONFIG)
{
	foreach ($files as $file) {
		// Delete the '.php' suffix
		$newFileName = \substr(\basename($file), 0, - 4);
		$path = \dirname($file);
		$newFile = "$path/$newFileName";

		if (\is_file($newFile)) {
			echo "Delete $newFile\n";
			\unlink($newFile);
		}
	}
}

function process_php(array $files, array $CONFIG)
{
	foreach ($files as $file) {
		$newFileName = \substr(\basename($file), 0, - 4);
		$path = \dirname($file);
		$newFile = "$path/$newFileName";

		if (\file_exists($newFile) && \filemtime($file) <= \filemtime($newFile)) {

			if (! $CONFIG['debug'])
				continue;

			echo "$newFile normally passed\n";
		}
		echo "Generate $newFile\n";
		\ob_start();

		if (false === include "$file")
			exit(1);

		\file_put_contents($newFile, \ob_get_clean());
	}
}

function clean_c(array $files, array $CONFIG)
{
	$confPath = $CONFIG['pragmas_fileConfig'];
	$pragmaConfig = loadConfig($confPath);

	foreach ($files as $file) {
		$pragma = new ZRPragma($file, $pragmaConfig);
		$pragma->clean();
	}
	$pragmaConfig['cleaned'] = true;
	saveConfig($confPath, $pragmaConfig);
}

function process_c(array $files, array $CONFIG)
{
	$confPath = $CONFIG['pragmas_fileConfig'];
	$pragmaConfig = loadConfig($confPath);

	foreach ($files as $file) {
		$pragma = new ZRPragma($file, $pragmaConfig);
		$pragma->process();
	}
	$pragmaConfig['cleaned'] = false;
	saveConfig($confPath, $pragmaConfig);
}

function process()
{
	global $CONFIG;
	$conf = $CONFIG;
	process_php(getFiles_php($conf), $conf);
	process_c(getFiles_c($conf), $conf);
}

function clean()
{
	global $CONFIG;
	$conf = $CONFIG;
	clean_php(getFiles_php($conf), $conf);
	clean_c(getFiles_c($conf), $conf);
}

$actions = [
	'process',
	'clean'
];
$action = $argv[1] ?? 'process';

if (! \in_array($action, $actions))
	exit(1);

$action();