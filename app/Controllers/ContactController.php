<?php
// namespace: Defines the folder organization path so the application knows where this file lives structurally.
namespace App\Controllers;

// use: Imports external layout blueprints and dependencies so this file can access database utilities and data tools.
use Core\Database;
use Exception;
use PDO;

// class: A logical code container that holds the behavior for managing forms, APIs, or user interactions.
class ContactController {
    
    // public function: A callable method action block designed to process the incoming web request parameters.
    public function handleContactSubmit() {
        
        // header(): Issues a protocol instruction telling the browser to expect data format layouts matching standard JSON text strings.
        header("Content-Type: application/json");
        
        // try: An isolation environment gate designed to monitor inner blocks for processing anomalies or system failures.
        try {

            // header("Access-Control-Allow-Origin: *"): Bypasses browser security (CORS) by letting external frontend domains request this API file.
            header("Access-Control-Allow-Origin: *"); 
            
            // header(...Headers...): Tells the browser which tracking components or identity values are officially allowed inside request packets.
            header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
            
            // header(...Methods...): Explicitly restricts the API endpoint so it only accepts submission actions (POST) or browser tests (OPTIONS).
            header("Access-Control-Allow-Methods: POST, OPTIONS");
            
            // header(...charset=UTF-8): Enforces text configurations to utilize universal encoding rules so foreign characters read correctly.
            header("Content-Type: application/json; charset=UTF-8");

            // if: A conditional test tracking if the incoming browser packet is merely an automated security pre-flight check.
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                // http_response_code(200): Sends back an automated "OK/Success" signal telling the browser it has permission to send the real data.
                http_response_code(200);
                // exit(): Terminates further execution immediately because the preliminary connection handshake is complete.
                exit();
            }

            // 1. 🛑 RATE LIMITER GATEWAY (Anti-Spam Security)
            
            // $_SERVER['REMOTE_ADDR']: A system variable that detects and reads the network identity address (IP) of the incoming visitor.
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // time(): A tracking tool that captures the exact second this code executes based on standard historical server timestamps.
            $currentTime = time();
            
            // __DIR__: A magic constant that references the exact hard drive folder path where *this* controller file resides.
            $limitDir = __DIR__ . '/../../storage/rate_limits/';
            
            // is_dir(): Looks at the local storage paths to check if a specified directory container physically exists yet.
            if (!is_dir($limitDir)) {
                // mkdir(): Programmatically generates a folder configuration with absolute read, write, and execute permissions (0777).
                mkdir($limitDir, 0777, true);
            }
            
            // md5(): Converts raw IP text layouts into scrambled characters to prevent file-system naming bugs.
            $logFile = $limitDir . 'ip_' . md5($ipAddress) . '.json';
            
            // Initialize an empty layout tracker to manage historical connection attempts.
            $timestamps = [];
            
            // file_exists(): Inspects the hard drive storage directory to locate a matching structural history record file.
            if (file_exists($logFile)) {
                // file_get_contents(): Opens a physical log text document and imports its inner string content blocks.
                // json_decode(..., true): Takes plain JSON text records and converts them into manageable PHP index array positions.
                $timestamps = json_decode(file_get_contents($logFile), true) ?: [];
            }
            
            // array_filter(): Loops through every tracked submission timestamp to isolate recent events.
            $timestamps = array_filter($timestamps, function($timestamp) use ($currentTime) {
                // Subtracts historical attempts from the current time to see if the interaction happened within a 60-second window.
                return ($currentTime - $timestamp) < 60;
            });
            
            // count(): Counts the exact number of active logs remaining inside the isolated 60-second timeline.
            if (count($timestamps) >= 3) {
                // http_response_code(429): Drops a standard network protocol barrier code representing "Too Many Requests".
                http_response_code(429); 
                // echo json_encode(): Packages an explicit rejection notification string and transmits it to your React components.
                echo json_encode([
                    "status" => "error", 
                    "message" => "Too many submissions! Please wait a minute before trying again."
                ]);
                // return: Halts processing blocks instantly to lock out the spammer before they stress the core database.
                return;
            }
            
            // [] = $currentTime: Appends the fresh connection timeline validation parameter straight into the temporary log sequence array.
            $timestamps[] = $currentTime;
            
            // file_put_contents(): Permanently saves the updated timestamp history configuration directly into the local server file system.
            file_put_contents($logFile, json_encode($timestamps));


            // 2. Gather the raw incoming payload from React
            
            // file_get_contents('php://input'): Directly intercepts raw, incoming streams transmitted over HTTP request bodies.
            // json_decode(..., true): Extracts variable layouts passed by React so PHP scripts can manipulate them easily.
            $input = json_decode(file_get_contents('php://input'), true);

            // 3. Structural Validation Gate
            
            // empty(): A tracking evaluation that ensures inputs are filled with data characters instead of blank spaces.
            if (empty($input['name']) || empty($input['email']) || empty($input['message'])) {
                // http_response_code(400): Triggers a protocol alert status signifying an incomplete data package transmission (Bad Request).
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "All fields are required."]);
                return;
            }

            // 4. Multi-stage XSS sanitization
            
            // trim(): Strips out invisible formatting marks or spaces tracking along outer entry points.
            // strip_tags(): Scrubs out malicious HTML structure blocks (like <script>) to block Code Injection attacks.
            // htmlspecialchars(..., ENT_QUOTES): Neutralizes remaining formatting marks like quotes, changing them into text entries.
            $secureName    = htmlspecialchars(strip_tags(trim($input['name'])), ENT_QUOTES, 'UTF-8');
            
            // filter_var(..., FILTER_SANITIZE_EMAIL): Clears characters from email inputs that break messaging delivery rules.
            $secureEmail   = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
            $secureMessage = htmlspecialchars(strip_tags(trim($input['message'])), ENT_QUOTES, 'UTF-8');

            // 5. Connect to database
            
            // Database::connect(): Activates your PDO environment layout to request an operational transaction pathway to MySQL.
            $db = Database::connect();

            // 6. THE PREPARED STATEMENT
            
            // Writes out an injection-safe SQL query blueprint using abstract placeholder words (`:name`) instead of tracking raw variables.
            $sql = "INSERT INTO contacts (name, email, message) VALUES (:name, :email, :message)";
            
            // $db->prepare(): Delivers the template layout straight to MySQL to compile the execution sequence ahead of time.
            $stmt = $db->prepare($sql);
            
            // 7. Explicit Parameter Binding
            
            // bindParam(): Locks a localized data value straight onto a query placeholder word.
            // PDO::PARAM_STR: Commands the execution parser to treat incoming data strictly as plain text characters, never as runnable code.
            // 255/65535: Enforces a strict data clipping barrier matching your structural database table constraints.
            $stmt->bindParam(':name', $secureName, PDO::PARAM_STR, 255);
            $stmt->bindParam(':email', $secureEmail, PDO::PARAM_STR, 255);
            $stmt->bindParam(':message', $secureMessage, PDO::PARAM_STR, 65535);
            
            // 8. Execute the compiled statement
            
            // $stmt->execute(): Runs the pre-compiled layout structure using the sanitized data points, returning a true/false status.
            $success = $stmt->execute();

            // 9. Send Response Status back to Frontend
            if ($success) {
                // echo json_encode(): Returns a success packet to trigger the React Toast slider animation.
                echo json_encode([
                    "status" => "success",
                    "message" => "Thank you, " . $secureName . "! Your inquiry has been logged securely."
                ]);
            } else {
                // throw new Exception: Forcefully kicks system operation tracks straight into the emergency catch segment if database writing fails.
                throw new Exception("The database was unable to execute the statement.");
            }

        // catch: The emergency terminal that captures system errors without allowing your raw server paths to expose themselves online.
        } catch (Exception $e) {
            // http_response_code(500): Issues an internal system failure notification to the client side.
            http_response_code(500);
            // $e->getMessage(): Reads the internal structural text trace of the failure pattern to simplify troubleshooting.
            echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
        }
    }
}