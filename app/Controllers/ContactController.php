<?php
namespace App\Controllers;

use Core\Database;
use Exception;
use PDO;

class ContactController {
    public function handleContactSubmit() {
        header("Content-Type: application/json");
        try {

header("Access-Control-Allow-Origin: *"); 
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Content-Type: application/json; charset=UTF-8");

        // Handle browser preflight checks
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

            // 1. 🛑 RATE LIMITER GATEWAY (Anti-Spam Security)
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $currentTime = time();
            
            // Create a temporary directory to store rate limit logs
            $limitDir = __DIR__ . '/../../storage/rate_limits/';
            if (!is_dir($limitDir)) {
                mkdir($limitDir, 0777, true);
            }
            
            // Generate a safe file name based on the user's IP address
            $logFile = $limitDir . 'ip_' . md5($ipAddress) . '.json';
            
            $timestamps = [];
            if (file_exists($logFile)) {
                $timestamps = json_decode(file_get_contents($logFile), true) ?: [];
            }
            
            // Filter out any timestamps older than 60 seconds (1 minute window)
            $timestamps = array_filter($timestamps, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 60;
            });
            
            // Check if the user has reached the threshold (e.g., max 3 requests per minute)
            if (count($timestamps) >= 3) {
                http_response_code(429); // 429 Too Many Requests
                echo json_encode([
                    "status" => "error", 
                    "message" => "Too many submissions! Please wait a minute before trying again."
                ]);
                return;
            }
            
            // Log the current successful attempt timestamp
            $timestamps[] = $currentTime;
            file_put_contents($logFile, json_encode($timestamps));


            // 2. Gather the raw incoming payload from React
            $input = json_decode(file_get_contents('php://input'), true);

            // 3. Structural Validation Gate
            if (empty($input['name']) || empty($input['email']) || empty($input['message'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "All fields are required."]);
                return;
            }

            // 4. Multi-stage XSS sanitization
            $secureName    = htmlspecialchars(strip_tags(trim($input['name'])), ENT_QUOTES, 'UTF-8');
            $secureEmail   = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
            $secureMessage = htmlspecialchars(strip_tags(trim($input['message'])), ENT_QUOTES, 'UTF-8');

            // 5. Connect to database
            $db = Database::connect();

            // 6. THE PREPARED STATEMENT
            $sql = "INSERT INTO contacts (name, email, message) VALUES (:name, :email, :message)";
            $stmt = $db->prepare($sql);
            
            // 7. Explicit Parameter Binding
            $stmt->bindParam(':name', $secureName, PDO::PARAM_STR, 255);
            $stmt->bindParam(':email', $secureEmail, PDO::PARAM_STR, 255);
            $stmt->bindParam(':message', $secureMessage, PDO::PARAM_STR, 65535);
            
            // 8. Execute the compiled statement
            $success = $stmt->execute();

            // 9. Send Response Status back to Frontend
            if ($success) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Thank you, " . $secureName . "! Your inquiry has been logged securely."
                ]);
            } else {
                throw new Exception("The database was unable to execute the statement.");
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
        }
    }
}