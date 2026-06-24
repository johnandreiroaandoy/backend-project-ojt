<?php
namespace App\Controllers;

use Core\Database;
use Exception;
use PDO;

class UserController {
    
    /**
     * Verifies if an email address exists inside the database registry table
     */
    public function verifyEmail() {
        header("Content-Type: application/json");
        
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
     * 👥 Increments the master counter AND appends a precise time-log record
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

            // 🟢 Only record telemetry metrics if it's NOT an administrative panel preview fetch
            if ($pagename !== 'admin_summary') {
                
                // 1. DUAL TRACKING STEP A: Keep your original master counter incrementing intact
                $stmt1 = $db->prepare("
                    INSERT INTO website_visitors (pagename, counter) 
                    VALUES (:pagename, 1)
                    ON DUPLICATE KEY UPDATE 
                        counter = counter + 1
                ");
                $stmt1->execute([':pagename' => $pagename]);

                // 2. DUAL TRACKING STEP B: Record the exact user check-in time into the historical log table
                $stmt2 = $db->prepare("INSERT INTO visitor_activity_logs (pagename) VALUES (:pagename)");
                $stmt2->execute([':pagename' => $pagename]);
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
     * 📊 Bundles master counters, itemized access times, chronological chart intervals,
     * AND extracts real-time submission counts from the user contact form matrix entries.
     */
    public function getAnalyticsMetrics() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");

        try {
            $db = Database::connect();
            
            // 1. Fetch total page counters sorted highest to lowest traffic
            $counterStmt = $db->query("SELECT pagename, counter, updated_at FROM website_visitors ORDER BY counter DESC");
            $counters = $counterStmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Fetch the 50 most recent exact raw access times from your logging table
            $logStmt = $db->query("
                SELECT pagename, accessed_at 
                FROM visitor_activity_logs 
                ORDER BY accessed_at DESC 
                LIMIT 50
            ");
            $recentLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Generate a chart timeline data map (grouped by date and hourly slots)
            $chartStmt = $db->query("
                SELECT 
                    DATE_FORMAT(accessed_at, '%Y-%m-%d %H:00:00') as time_bucket,
                    COUNT(*) as hits
                FROM visitor_activity_logs
                GROUP BY time_bucket
                ORDER BY time_bucket ASC
                LIMIT 100
            ");
            $timelineData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Query total records contained within the contacts table database structure
            $contactStmt = $db->query("SELECT COUNT(*) as total_messages FROM contacts");
            $contactResult = $contactStmt->fetch(PDO::FETCH_ASSOC);
            $totalInquiries = isset($contactResult['total_messages']) ? (int)$contactResult['total_messages'] : 0;

            echo json_encode([
                "status" => "success",
                "metrics" => $counters,       // Master counter dataset
                "recentLogs" => $recentLogs,   // Precise user click clock times
                "timeline" => $timelineData,   // Recharts visualization map array
                "totalInquiries" => $totalInquiries // Real-time message count parameter
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
     * 📩 NEW STEP FOR OPTION A: Extracts raw recent submission text data matrices 
     * This fulfills the React dashboard's request to render rows inside the UI inbox component.
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
            $db = Database::connect();
            
            // Query logs dynamically out of the database (pulling most recent submissions first)
            $stmt = $db->query("SELECT id, name, email, message, created_at FROM contacts ORDER BY id DESC LIMIT 100");
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ✅ CHANGED: Changed the key name to "inquiriesList" to match your front-end expected prop!
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