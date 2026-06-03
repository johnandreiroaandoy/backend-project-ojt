<?php
namespace Core;

class Router {
    protected array $routes = [];

    /**
     * Register a new route mapping
     */
    public function add(string $method, string $route, string $controllerAction) {
        $this->routes[] = [
            'method' => $method,
            'route' => $route,
            'action' => $controllerAction
        ];
    }

    /**
     * Match the incoming URL and execute the appropriate controller action
     */
    public function dispatch(string $uri, string $method) {
        // Extract the path component from the URL
        $url = parse_url($uri, PHP_URL_PATH);
        
        // Clean up project subfolder path from URL if running via XAMPP htdocs subfolder
        $url = str_replace('/cgo-accountant-api/public', '', $url);
        
        // Normalize trailing slashes (e.g., convert '/users/' to '/users')
        if ($url !== '/' && substr($url, -1) === '/') {
            $url = rtrim($url, '/');
        }
        
        // Default to root if path becomes empty
        if ($url === '' || $url === '/') { 
            $url = '/'; 
        }

        foreach ($this->routes as $route) {
            if ($route['route'] === $url && $route['method'] === $method) {
                [$controllerName, $action] = explode('@', $route['action']);
                $controllerClass = "App\\Controllers\\" . $controllerName;

                // 🔍 DEBUGGING CHECK 1: Does the class file actually exist/load?
                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    die("<h2>Routing Error</h2>Class <strong>{$controllerClass}</strong> could not be found.<br><br>
                         <strong>How to fix:</strong><br>
                         1. Check if <code>app/Controllers/{$controllerName}.php</code> exists.<br>
                         2. Check if the namespace inside that file is exactly <code>namespace App\Controllers;</code>.<br>
                         3. Open your terminal and run <code>composer dump-autoload</code>.");
                }

                $controllerInstance = new $controllerClass();

                // 🔍 DEBUGGING CHECK 2: Does the method exist inside that controller?
                if (!method_exists($controllerInstance, $action)) {
                    http_response_code(500);
                    die("<h2>Routing Error</h2>Method <strong>{$action}()</strong> does not exist inside <strong>{$controllerClass}</strong>.<br><br>
                         <strong>How to fix:</strong> Open <code>{$controllerClass}.php</code> and add: <br>
                         <code>public function {$action}() { ... }</code>");
                }

                // If everything is completely correct, execute the controller method!
                $controllerInstance->$action();
                return;
            }
        }

        // If no routes match at all
        http_response_code(404);
        echo "<h1>404 - Page Not Found</h1><p>The custom MVC router matched the path '<strong>{$url}</strong>', but no defined route matches this destination.</p>";
    }
}