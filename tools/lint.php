<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$ignoredDirectories = array_fill_keys([
    '.git',
    'node_modules',
    'storage',
    'tmp',
    'uploads',
    'vendor',
], true);

$files = [];
$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator(
    $directory,
    static function (SplFileInfo $item) use ($ignoredDirectories): bool {
        if ($item->isDir()) {
            return !isset($ignoredDirectories[$item->getFilename()]);
        }

        return strtolower($item->getExtension()) === 'php';
    }
);

foreach (new RecursiveIteratorIterator($filter) as $item) {
    if ($item instanceof SplFileInfo && $item->isFile()) {
        $files[] = $item->getPathname();
    }
}
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$failed = [];
foreach ($files as $file) {
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        $failed[$file] = implode(PHP_EOL, $output);
    }
}

if ($failed !== []) {
    foreach ($failed as $file => $message) {
        fwrite(STDERR, $file . PHP_EOL . $message . PHP_EOL . PHP_EOL);
    }
    fwrite(STDERR, count($failed) . ' PHP file(s) failed syntax validation.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, count($files) . ' PHP file(s) passed syntax validation.' . PHP_EOL);
