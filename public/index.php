<?php
// public/index.php

// 🟢 NEW: ENVIRONMENT VARIABLE LOADER FOR CUSTOM MVC
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
// Execute the loader pointing back to your root directory .env file
loadEnv(__DIR__ . '/../.env');


// 1. 🟢 INSTANT CORS PREFLIGHT GREEN LIGHT
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Router;

$router = new Router();

// Keeps your default backend test page active
$router->add('GET', '/', 'HomeController@index');

// Endpoints for your actual project pages:
$router->add('GET', '/api/reports', 'HomeController@getReports');
$router->add('POST', '/api/contact', 'HomeController@handleContactSubmit');

// 2. 🟢 SUB-FOLDER STRIPPER 
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']); // Gets '/cgo-accountant-api/public'

if (strpos($requestUri, $scriptName) === 0) {
    $requestUri = substr($requestUri, strlen($scriptName));
}

$requestUri = '/' . ltrim(parse_url($requestUri, PHP_URL_PATH), '/');

// Dispatch the cleaned path request
$router->dispatch($requestUri, $_SERVER['REQUEST_METHOD']);