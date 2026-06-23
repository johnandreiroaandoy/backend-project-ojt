<?php
// public/index.php

// 🟢 ENVIRONMENT VARIABLE LOADER FOR CUSTOM MVC
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
loadEnv(__DIR__ . '/../.env');

// 1. INSTANT CORS PREFLIGHT GREEN LIGHT
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Controllers/UserController.php';

use Core\Router;

$router = new Router();

$router->add('GET', '/', 'HomeController@index');

// 🟢 ACTIVE API ENDPOINT REGISTRY
$router->add('GET', '/api/reports', 'ReportController@getReports');
$router->add('POST', '/api/reports/upload', 'ReportController@uploadReport');
$router->add('DELETE', '/api/reports/delete', 'ReportController@deleteReport');

$router->add('POST', '/api/contact', 'ContactController@handleContactSubmit');
$router->add('POST', '/api/verify-email', 'UserController@verifyEmail');
$router->add('POST', '/api/content/save-config', 'HomeController@saveConfig');

// 👥 NEW: Analytics background session pipeline tracker endpoint
$router->add('GET', '/api/analytics/track-visit', 'UserController@trackVisit');

// =====================================================================
// 2. RESILIENT SUB-FOLDER STRIPPER & NORMALIZER FOR WINDOWS/XAMPP
// =====================================================================

// Extract the clean structural path from the incoming request URL string
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Identify the execution directory path string and force forward slashes
$scriptName = dirname($_SERVER['SCRIPT_NAME']); 
$scriptName = str_replace('\\', '/', $scriptName); // Safeguard for Windows setups

// Strip the nested script folders (e.g., '/backend-project-ojt/public') if found at the beginning
if (!empty($scriptName) && $scriptName !== '/') {
    if (strpos($requestUri, $scriptName) === 0) {
        $requestUri = substr($requestUri, strlen($scriptName));
    }
}

// Fallback Hard-Guard: Clear the root folder segment if it bypassed the script name check
$projectRoot = '/backend-project-ojt';
if (strpos($requestUri, $projectRoot) === 0) {
    $requestUri = substr($requestUri, strlen($projectRoot));
}

// Ensure the final processed routing path always begins with a single forward slash
$requestUri = '/' . ltrim($requestUri, '/');

// =====================================================================
// 🚀 3. FIXED: REAL-TIME STATIC ASSET CHECK (BYPASS THE ROUTER)
// =====================================================================
// Strip a leading /public text block if it managed to creep into the normalized URI string
$cleanFileUri = preg_replace('/^\/public/', '', $requestUri);
$physicalFilePath = __DIR__ . $cleanFileUri;

if (is_file($physicalFilePath)) {
    // Determine the Content-Type header on the fly to prevent browser rendering drops
    $extension = strtolower(pathinfo($physicalFilePath, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':  header("Content-Type: application/pdf"); break;
        case 'png':  header("Content-Type: image/png"); break;
        case 'jpg':  
        case 'jpeg': header("Content-Type: image/jpeg"); break;
        case 'json': header("Content-Type: application/json"); break;
    }
    
    // Dump the raw file binary data downstream to the browser and terminate process
    readfile($physicalFilePath);
    exit;
}

// =====================================================================
// DISPATCH REQUEST TO YOUR FRAMEWORK ROUTER
// =====================================================================
$router->dispatch($requestUri, $_SERVER['REQUEST_METHOD']);