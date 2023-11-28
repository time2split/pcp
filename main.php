<?php
require_once __DIR__ . '/classes/autoload.php';

$CONFIG = [
    'cpp.wd' => getcwd() . '/cpp.wd',
    'pragmas_fileConfig' => __DIR__ . '/config.php',
    'debug' => false,
    // 'cleaned' => null,
    'cpp.name' => [
        'zrlib',
        'pcp'
    ],
    'paths' => [
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

function getFiles_c($CONFIG): array
{
    $files = [];

    foreach ($CONFIG['paths'] as $path) {
        $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME);
        $dirIterator = new \RegexIterator(new \RecursiveIteratorIterator($dirIterator), "/^.+\.[hc]$/");
        $files += \iterator_to_array($dirIterator);
    }
    return \array_values($files);
}

function getFiles_php($CONFIG): array
{
    $files = [];

    foreach ($CONFIG['paths'] as $path) {
        $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME);
        $dirIterator = new \RegexIterator(new \RecursiveIteratorIterator($dirIterator), "/^.+\.[hc]\.php$/");
        $files += \iterator_to_array($dirIterator);
    }
    return \array_values($files);
}

function clean_php(array $files, $CONFIG)
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

function clean_c(array $files, $CONFIG)
{
    $confPath = $CONFIG['pragmas_fileConfig'];
    $pragmaConfig = loadConfig($confPath);

    foreach ($files as $file) {
        $pragma = new \C\PCP($file, $pragmaConfig);
        $pragma->clean();
    }
    $pragmaConfig['cleaned'] = true;
    saveConfig($confPath, $pragmaConfig);
}

function clean()
{
    global $CONFIG;
    $conf = $CONFIG;
    clean_php(getFiles_php($conf), $conf);
    clean_c(getFiles_c($conf), $conf);
}

$actions = [
    'process' => '\Process\DoIt',
    'clean' => '\Process\Clean'
];
$action = $argv[1] ?? 'process';

if (! isset($actions[$action]))
    exit(1);

$process = new $actions[$action]();

$theConfig = \Data\TreeConfigHierarchy::create();
$theConfig->mergeArrayRecursive($CONFIG);

$process->process($theConfig);

