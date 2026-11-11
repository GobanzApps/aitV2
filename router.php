<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Permitir que PHP sirva archivos reales (CSS, JS, imágenes, etc.)
$archivo = __DIR__ . $uri;

if ($uri !== '/' && (is_file($archivo) || is_dir($archivo))) {
    return false;
}

// Convertir /actividad en index.php?ruta=actividad
$ruta = trim($uri, '/');

if ($ruta !== '') {
    $_GET['ruta'] = $ruta;
}

require __DIR__ . '/index.php';
