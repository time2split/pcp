<?php
$CONFIG = [
	'paths' => ['zrlib', 'src'],
]; $files = [];

foreach($CONFIG['paths'] as $path)
{
	$dirIterator = new RegexIterator(
		new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path,
			FilesystemIterator::SKIP_DOTS|
			FilesystemIterator::CURRENT_AS_PATHNAME
			)
		)
		, "/^.+\.[hc]\.php/"
	);
	$files += iterator_to_array($dirIterator);
}

foreach($files as $file)
{
	$newFileName = substr(basename($file), 0, -4);
	$path = dirname($file);
	$newFile = "$path/$newFileName";

	if(file_exists($newFile) && filemtime($file) <= filemtime($newFile))
		continue;

	echo "Generate $newFile\n";
	ob_start();

	if(false === include "$file")
		exit(1);

	file_put_contents($newFile, ob_get_clean());
}
