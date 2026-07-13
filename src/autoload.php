<?php

spl_autoload_register(function (string $class): void {
    $prefix = 'SnapKolibrios\\';
    $baseDir = __DIR__ . '/SnapKolibrios/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
