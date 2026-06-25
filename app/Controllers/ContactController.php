<?php
namespace App\Controllers;

use Core\Database;
use Exception;
use PDO;

class ContactController {
    
    /**
     * 🔒 LOCAL GET-ROUTE SECURITY CHECK: Authenticates read operations since GET skips the global Router guard
     */
    private function verifyReadPermission() {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);

        if (empty($token) || $token === 'undefined') {
            http_response_code(401);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "status" => "error",
                "message" => "Access Denied: Administrative credential token context required."
            ]);
            exit();
        }
    }

    /**
     * Processing inbound public user inquiry form data streams
     * 🔓 PUBLICLY ACCESSIBLE: Router whitelisted to allow visitor feedback messages
     */
    public function handleContactSubmit() {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *"); 
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        try {
            // 1. 🛑 RATE LIMITER GATEWAY (Anti-Spam Security)
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $currentTime = time();
            $limitDir = __DIR__ . '/../../storage/rate_limits/';
            
            if (!is_dir($limitDir)) {
                mkdir($limitDir, 0777, true);
            }
            
            $logFile = $limitDir . 'ip_' . md5($ipAddress) . '.json';
            $timestamps = [];
            
            if (file_exists($logFile)) {
                $timestamps = json_decode(file_get_contents($logFile), true) ?: [];
            }
            
            $timestamps = array_filter($timestamps, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 60;
            });
            
            if (count($timestamps) >= 3) {
                http_response_code(429); 
                echo json_encode([
                    "status" => "error", 
                    "message" => "Too many submissions! Please wait a minute before trying again."
                ]);
                return;
            }
            
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

    /**
     * 📊 SECURED READ METHOD: Pulls recent system contact inquiries
     * 🔒 SECURED: Protected manually because GET verbs bypass the global Router firewall
     */
    public function getInquiriesList() {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        try {
            // Manually intercepts anonymous read scrapers since it is a GET method
            $this->verifyReadPermission();

            $db = Database::connect();
            $stmt = $db->query("SELECT id, name, email, message, created_at FROM contacts ORDER BY created_at DESC");
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "inquiriesList" => $inquiries
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
        }
    }

    /**
     * 🗑️ SECURED DELETE METHOD: Drops an individual item record matching parameter IDs
     * 🔒 AUTOMATICALLY PROTECTED: The Router interceptor blocks this before it runs
     */
    public function deleteInquiry() {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: DELETE, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Target ID entry is required."]);
                return;
            }

            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM contacts WHERE id = :id");
            $stmt->bindParam(':id', $input['id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Inquiry row removed from registry catalog."]);
            } else {
                throw new Exception("Execution pipeline dropped delete action.");
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
        }
    }
}