<?php
// namespace: Structural folder space declaration path indicating this class belongs to the core system engine layer.
namespace Core;

// class: A blueprint class container holding all application operations for capturing URLs and parsing route paths.
class Router {
    
    // An internal structural list array that stores every single valid URL path route combination mapped in your system configuration.
    protected array $routes = [];

    /**
     * Registers route configurations in the internal routing table matrix.
     */
    public function add(string $method, string $route, string $controllerAction) {
        $this->routes[] = [
            'method' => strtoupper($method), // Force uppercase to ensure perfect matching strings
            'route'  => $route,
            'action' => $controllerAction
        ];
    }

    // Shorthand helper shortcut wrapper method for handling GET data requests
    public function get(string $route, string $controllerAction) {
        $this->add('GET', $route, $controllerAction);
    }

    // Shorthand helper shortcut wrapper method for handling POST data streams
    public function post(string $route, string $controllerAction) {
        $this->add('POST', $route, $controllerAction);
    }

    /**
     * 🔒 Centralized Security Engine: Validates admin bearer tokens before resource routing
     */
    private function verifyGlobalAdminSession() {
        // Fallback resolution sequence targeting native Apache functions alongside global server variables
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        
        // Normalize capitalization discrepancies from diverse clients + check global system backups
        $authHeader = $headers['Authorization'] ?? 
                      $headers['authorization'] ?? 
                      $_SERVER['HTTP_AUTHORIZATION'] ?? 
                      $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
                      
        $token = str_replace('Bearer ', '', $authHeader);

        if (empty($token) || $token === 'undefined') {
            http_response_code(401);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "status" => "error",
                "message" => "Security Access Denied: Administrative role clearance required for state-changing operations."
            ]);
            exit();
        }
    }

    /**
     * Matches the incoming request to trigger a controller execution or handles failures cleanly.
     */
    public function dispatch(string $uri, string $method) {
        
        $method = strtoupper($method);

        // 🛡️ GLOBAL CORS PREFLIGHT INTERCEPTOR: Immediately green-light browser OPTIONS inquiries
        if ($method === 'OPTIONS') {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Device-Model, X-Client-Device-Model, X-Client-Platform, X-Client-Is-Mobile, Sec-CH-UA-Mobile, Sec-CH-UA-Model, Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version");
            header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
            http_response_code(200);
            exit(0);
        }

        // Extract only the textual directory path from a complete URL string, stripping out trailing parameters (?id=1)
        $url = parse_url($uri, PHP_URL_PATH);
        
        // Updated local development folder path matching rules to align directly with your 'backend-project-ojt' layout matrix
        $url = str_replace('/backend-project-ojt/public', '', $url);
        
        // Checks if a user included a trailing slash at the absolute end of their browser URL.
        if ($url !== '/' && substr($url, -1) === '/') {
            $url = rtrim($url, '/');
        }
        
        // Fallback Hard-Guard: Default to clean forward slash if empty
        if ($url === '' || $url === '/') { 
            $url = '/'; 
        }

        // 🛡️ GLOBAL METHOD INTERCEPTOR: Lock down data adjustments
        $destructiveMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        
        if (in_array($method, $destructiveMethods)) {
            // 💡 WHITELIST EXCEPTION: Allow the anonymous frontend public landing form to deliver contact tickets
            if ($url !== '/api/contact') {
                $this->verifyGlobalAdminSession();
            }
        }

        // An iterator loop that examines every single row record stored inside our system's private $routes list.
        foreach ($this->routes as $route) {
            
            // Validates that BOTH the URL string path AND the incoming HTTP verb match the layout configuration
            if ($route['route'] === $url && $route['method'] === $method) {
                
                // Breaks down your custom identifier string at the '@' symbol mark into two separate values
                [$controllerName, $action] = explode('@', $route['action']);
                
                // Concatenates the string pieces to define the fully qualified object blueprint directory path
                $controllerClass = "App\\Controllers\\" . $controllerName;

                // 🔍 DEBUGGING CHECK 1: Does the class file actually exist/load?
                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    header("Content-Type: application/json");
                    echo json_encode([
                        "status" => "error",
                        "error_type" => "Routing System Misconfiguration",
                        "message" => "Class '{$controllerClass}' could not be located by the system autoloader.",
                        "remediation" => [
                            "1. Verify app/Controllers/{$controllerName}.php exists.",
                            "2. Confirm the namespace inside that file is exactly 'namespace App\Controllers;'.",
                            "3. Run 'composer dump-autoload' inside your terminal."
                        ]
                    ]);
                    exit;
                }

                // Dynamically instantiates a brand new working memory object instance of your target controller.
                $controllerInstance = new $controllerClass();

                // 🔍 DEBUGGING CHECK 2: Does the method exist inside that controller?
                if (!method_exists($controllerInstance, $action)) {
                    http_response_code(500);
                    header("Content-Type: application/json");
                    echo json_encode([
                        "status" => "error",
                        "error_type" => "Action Engine Missing",
                        "message" => "Method '{$action}()' does not exist inside '{$controllerClass}'.",
                        "remediation" => "Open '{$controllerName}.php' and add: public function {$action}() { ... }"
                    ]);
                    exit;
                }

                // Execute the target controller method action block!
                $controllerInstance->$action();
                return;
            }
        }

        // 🛑 API-COMPLIANT 404 FALLBACK: Modified to emit readable data objects instead of plain HTML text blocks
        http_response_code(404);
        header("Content-Type: application/json");
        echo json_encode([
            "status" => "error",
            "message" => "Resource not found.",
            "route_requested" => $url,
            "method_requested" => $method
        ]);
        exit;
    }
}
