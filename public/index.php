<?php
// public/index.php

// 1. 🟢 INSTANT CORS PREFLIGHT GREEN LIGHT
// This intercepts the browser's hidden check before your router runs
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
// Cleans '/cgo-accountant-api/public/api/contact' down to just '/api/contact'
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']); // Gets '/cgo-accountant-api/public'

if (strpos($requestUri, $scriptName) === 0) {
    $requestUri = substr($requestUri, strlen($scriptName));
}

// Ensure the URI always starts with a slash and drops extra query parameters
$requestUri = '/' . ltrim(parse_url($requestUri, PHP_URL_PATH), '/');

// Dispatch the cleaned path request
$router->dispatch($requestUri, $_SERVER['REQUEST_METHOD']);