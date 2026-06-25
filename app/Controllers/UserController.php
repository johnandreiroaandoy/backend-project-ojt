<?php
namespace App\Controllers;

use Core\Database;
use Exception;
use PDO;

class UserController {
    
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
     * Verifies if an email address exists inside the database registry table
     * 🔒 AUTOMATICALLY PROTECTED: Intercepted and secured by the global Router engine for POST requests.
     */
    public function verifyEmail() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
        
        $input = json_decode(file_get_contents("php://input"), true);
        $email = isset($input['email']) ? trim($input['email']) : '';

        if (empty($email)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error", 
                "message" => "Email address field is required."
            ]);
            return;
        }

        try {
            $db = Database::connect();
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $emailExists = $stmt->fetchColumn() > 0;

            if ($emailExists) {
                echo json_encode([
                    "status" => "success", 
                    "registered" => true,
                    "message" => "Email verified successfully."
                ]);
            } else {
                echo json_encode([
                    "status" => "success", 
                    "registered" => false,
                    "message" => "This email is not registered in our system."
                ]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Database error: " . $e->getMessage()
            ]);
        }
    }

    /**
     * 👥 Track visits: Aggregates totals into website_visitors without log flooding
     * 🔓 PUBLICLY ACCESSIBLE: Left unauthenticated so frontend client traffic can log telemetry automatically.
     */
    public function trackVisit() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type");

        try {
            $pagename = isset($_GET['pagename']) ? trim($_GET['pagename']) : 'root';

            if (empty($pagename)) {
                $pagename = 'root';
            }

            $db = Database::connect(); 

            // Only record telemetry metrics if it's NOT an administrative panel preview fetch
            if ($pagename !== 'admin_summary') {
                $stmt1 = $db->prepare("
                    INSERT INTO website_visitors (pagename, counter, updated_at) 
                    VALUES (:pagename, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                        counter = counter + 1,
                        updated_at = NOW()
                ");
                $stmt1->execute([':pagename' => $pagename]);
            }

            // Gather total collective accumulated views from the master rows
            $countStmt = $db->query("SELECT SUM(counter) as total_visitors FROM website_visitors");
            $result = $countStmt->fetch(PDO::FETCH_ASSOC);
            
            $totalViews = isset($result['total_visitors']) ? (int)$result['total_visitors'] : 0;

            echo json_encode([
                "status" => "success",
                "pagename" => $pagename,
                "total_visitors" => $totalViews
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Tracking node error: " . $e->getMessage()
            ]);
        }
    }

    /**
     * 📊 Bundles active page counters and extracts dynamic inquiry submission timeline metrics
     * 🔒 SECURED MANUALLY: Protected manually because GET verbs bypass the global Router firewall
     */
    public function getAnalyticsMetrics() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        try {
            // Intercept analytics scrapers checking data endpoints
            $this->verifyReadPermission();

            $db = Database::connect();
            
            // 1. Fetch total page counters sorted highest to lowest traffic (Chart 1)
            $counterStmt = $db->query("SELECT pagename, counter, updated_at FROM website_visitors ORDER BY counter DESC");
            $counters = $counterStmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Extract dynamic inquiry hours from the existing timestamps inside the contacts table
            $hourlyStmt = $db->query("
                SELECT 
                    HOUR(created_at) as hour_number, 
                    COUNT(*) as message_count 
                FROM contacts 
                GROUP BY HOUR(created_at) 
                ORDER BY hour_number ASC
            ");
            $rawHourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalize the timeline array into a strict 24-point array matrix (Hours 0-23) so Recharts lines don't break
            $inquiryHours = [];
            for ($h = 0; $h < 24; $h++) {
                $inquiryHours[$h] = [
                    "hour_number" => $h,
                    "message_count" => 0
                ];
            }

            // Merge dynamic grouping data onto the 24-hour timeline skeleton structure
            foreach ($rawHourlyData as $row) {
                $hourIndex = (int)$row['hour_number'];
                if ($hourIndex >= 0 && $hourIndex < 24) {
                    $inquiryHours[$hourIndex]['message_count'] = (int)$row['message_count'];
                }
            }

            // 3. Query total records contained within the contacts table database structure
            $contactStmt = $db->query("SELECT COUNT(*) as total_messages FROM contacts");
            $contactResult = $contactStmt->fetch(PDO::FETCH_ASSOC);
            $totalInquiries = isset($contactResult['total_messages']) ? (int)$contactResult['total_messages'] : 0;

            echo json_encode([
                "status" => "success",
                "metrics" => $counters,               
                "inquiryHours" => array_values($inquiryHours), 
                "totalInquiries" => $totalInquiries   
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to retrieve logs: " . $e->getMessage()
            ]);
        }
    }

    /**
     * 📩 Extracts raw recent submission text data matrices 
     * 🔒 SECURED MANUALLY: Protected manually because GET verbs bypass the global Router firewall
     */
    public function getInquiriesList() {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        try {
            // Blocks anonymous scripts seeking customer listings entries
            $this->verifyReadPermission();

            $db = Database::connect();
            
            // Query logs dynamically out of the database (pulling most recent submissions first)
            $stmt = $db->query("SELECT id, name, email, message, created_at FROM contacts ORDER BY id DESC LIMIT 100");
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "inquiriesList" => $inquiries
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to fetch raw inquiries matrix data: " . $e->getMessage()
            ]);
        }
    }
}