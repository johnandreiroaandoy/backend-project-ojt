<?php
// namespace: Structural folder space declaration path indicating this class belongs to the core system engine layer.
namespace Core;

// class: A blueprint class container holding all application operations for capturing URLs and parsing route paths.
class Router {
    
    // protected array $routes = [];
    // An internal structural list array that stores every single valid URL path route combination mapped in your system configuration.
    protected array $routes = [];

    /**
     * Docblock: Programmer's comment explaining that this method registers route configurations.
     */
    public function add(string $method, string $route, string $controllerAction) {
        
        // $this->routes[] = : Appends a new route configuration straight into the localized $routes storage array matrix.
        $this->routes[] = [
            // 'method': Stores the valid HTTP Verb required for this path (e.g., 'POST' for submissions or 'GET' for reads).
            'method' => strtoupper($method), // 🟢 IMPROVEMENT: Force uppercase to ensure perfect matching strings
            // 'route': Stores the textual endpoint pattern string matching the URL path (e.g., '/api/contact').
            'route' => $route,
            // 'action': Stores a tracking identifier combining the controller name and target function separated by a symbol (e.g., 'ContactController@handleContactSubmit').
            'action' => $controllerAction
        ];
    }

    // 🟢 NEW: Shorthand helper shortcut wrapper method for handling GET data requests
    public function get(string $route, string $controllerAction) {
        $this->add('GET', $route, $controllerAction);
    }

    // 🟢 NEW: Shorthand helper shortcut wrapper method for handling POST data streams
    public function post(string $route, string $controllerAction) {
        $this->add('POST', $route, $controllerAction);
    }

    /**
     * Docblock: Programmer's comment explaining that this method matches the incoming request to trigger a controller execution.
     */
    public function dispatch(string $uri, string $method) {
        
        // parse_url(..., PHP_URL_PATH): A built-in PHP utility that extracts only the textual directory path from a complete URL string, completely stripping out any trailing query string parameters (like ?id=1).
        $url = parse_url($uri, PHP_URL_PATH);
        
        // 🟢 FIXED: Updated local development folder path matching rules to align directly with your 'backend-project-ojt' layout matrix
        $url = str_replace('/backend-project-ojt/public', '', $url);
        
        // if: A conditional guard that checks if a user included a sloppy trailing slash at the absolute end of their browser URL.
        if ($url !== '/' && substr($url, -1) === '/') {
            // rtrim(): Trims and strips off the trailing right slash (e.g., rewriting '/api/reports/' into '/api/reports') so matching remains uniform.
            $url = rtrim($url, '/');
        }
        
        // if: An absolute fallback check that defaults the route to a clean forward slash ('/') if the path tracking string evaluates to completely blank.
        if ($url === '' || $url === '/') { 
            $url = '/'; 
        }

        // foreach: An iterator loop that examines every single row record stored inside our system's private $routes list.
        foreach ($this->routes as $route) {
            
            // if: Validates that BOTH the URL string path AND the incoming HTTP verb match the exact layout configurations stored in the array row.
            if ($route['route'] === $url && $route['method'] === $method) {
                
                // explode(): Breaks down your custom identifier string at the '@' symbol mark into two separate array values.
                // [$controllerName, $action]: Immediately maps those pieces into individual distinct text variables (e.g., $controllerName becomes 'ContactController' and $action becomes 'handleContactSubmit').
                [$controllerName, $action] = explode('@', $route['action']);
                
                // Concatenates the string pieces to define the fully qualified object blueprint directory path.
                $controllerClass = "App\\Controllers\\" . $controllerName;

                // 🔍 DEBUGGING CHECK 1: Does the class file actually exist/load?
                if (!class_exists($controllerClass)) {
                    // http_response_code(500): Issues an internal system configuration failure protocol status back to the client interface.
                    http_response_code(500);
                    // die(): Halts operation completely and prints out a detailed visual troubleshooting guide with remediation steps for the developer.
                    die("<h2>Routing Error</h2>Class <strong>{$controllerClass}</strong> could not be found.<br><br>
                         <strong>How to fix:</strong><br>
                         1. Check if <code>app/Controllers/{$controllerName}.php</code> exists.<br>
                         2. Check if the namespace inside that file is exactly <code>namespace App\Controllers;</code>.<br>
                         3. Open your terminal and run <code>composer dump-autoload</code>.");
                }

                // new $controllerClass(): Dynamically instantiates a brand new working memory object instance of your target controller.
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
                
                // return: Gracefully exits the dispatch controller function lifecycle because traffic mapping is complete.
                return;
            }
        }

        // If no routes match at all
        http_response_code(404);
        echo "<h1>404 - Page Not Found</h1><p>The custom MVC router matched the path '<strong>{$url}</strong>', but no defined route matches this destination.</p>";
    }
}