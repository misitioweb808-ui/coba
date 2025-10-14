<?php
ini_set('memory_limit', '-1');               // Sin límite de memoria
ini_set('upload_max_filesize', '100M');      // Tamaño máximo de archivos individuales
ini_set('post_max_size', '100M');            // Tamaño máximo total del cuerpo POST
ini_set('max_execution_time', '300');        // Tiempo máximo de ejecución en segundos (5 min)
set_time_limit(300);
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
