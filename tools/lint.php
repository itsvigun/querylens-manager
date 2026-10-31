<?php

declare(strict_types=1);

$paths = ['src', 'config', 'public', 'tests', 'tools'];
foreach ($paths as $path) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
        if ($file->getExtension() !== 'php') { continue; }
        passthru(PHP_BINARY.' -l '.escapeshellarg($file->getPathname()), $status);
        if ($status !== 0) { exit($status); }
    }
}
