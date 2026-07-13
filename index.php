<?php

require_once __DIR__ . '/src/autoload.php';

use SnapKolibrios\Transpiler;

$xmlPath = $argv[1] ?? __DIR__ . '/default.xml';
$outputPath = $argv[2] ?? __DIR__ . '/application.c';

$transpiler = new Transpiler(__DIR__ . '/resource');
$transpiler->transpile($xmlPath, $outputPath);
