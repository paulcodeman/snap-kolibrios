<?php

require_once __DIR__ . '/src/autoload.php';

use SnapKolibrios\CompileHandler;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$xml = $_POST['project'] ?? null;
if (!$xml) {
    http_response_code(400);
    echo 'No project XML provided';
    exit;
}

$handler = new CompileHandler(
    buildsDir: __DIR__ . '/builds',
    cmmPath: __DIR__ . '/cmm.exe',
    projectDir: __DIR__,
);

$handler->handle($xml);
