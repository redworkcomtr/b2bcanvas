<?php

$root = dirname(__DIR__);
$directories = ['app', 'bootstrap', 'config', 'database', 'routes', 'scripts'];
$failed = [];

foreach ($directories as $directory) {
    $path = $root.DIRECTORY_SEPARATOR.$directory;

    if (! is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1';
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $failed[$file->getPathname()] = $output;
        }

        $output = [];
    }
}

if ($failed !== []) {
    foreach ($failed as $file => $messages) {
        fwrite(STDERR, $file.PHP_EOL.implode(PHP_EOL, $messages).PHP_EOL);
    }

    exit(1);
}

echo 'PHP syntax OK'.PHP_EOL;
