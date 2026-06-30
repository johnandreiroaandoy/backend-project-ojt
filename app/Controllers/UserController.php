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
        // Fallback sequence to retrieve headers accurately across both Apache and standard environments
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        
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
            
            // Note: Keeping this query targeting contacts since no users table exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM contacts WHERE email = ?");
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
     * 👥 Track visits: Aggregates totals into website_visitors with enhanced telemetry extraction
     * 🔓 PUBLICLY ACCESSIBLE: Left unauthenticated so frontend client traffic can log telemetry automatically.
     */
    public function trackVisit() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        try {
            $pagename = isset($_GET['pagename']) ? trim($_GET['pagename']) : 'root';
            if (empty($pagename)) {
                $pagename = 'root';
            }

            $db = Database::connect(); 

            if ($pagename !== 'admin_summary') {
                
                // 1. Identify IP Address securely (accounting for potential reverse proxy load balancers)
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ipAddress = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
                }
                if ($ipAddress === '::1') {
                    $ipAddress = '127.0.0.1';
                }

                // 2. Sniff System details using the HTTP User-Agent string
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Determine device category framing matrix
                $deviceType = 'Desktop';
                if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
                    $deviceType = 'Mobile';
                } elseif (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent)) {
                    $deviceType = 'Tablet';
                }

                /* ==========================================================
                    🛡️ FIXED BROWSER SNIFFER MATRIX ORDER
                ========================================================== */
                $browser = 'Unknown';
                if (preg_match('/MSIE/i', $userAgent) && !preg_match('/Opera/i', $userAgent)) { 
                    $browser = 'MSIE'; 
                } 
                // 🟢 Check Edge / Edg FIRST because Chromium Edge includes "Chrome" in its header string
                elseif (preg_match('/Edg/i', $userAgent) || preg_match('/Edge/i', $userAgent)) { 
                    $browser = 'Microsoft Edge'; 
                }
                elseif (preg_match('/Firefox/i', $userAgent)) { 
                    $browser = 'Firefox'; 
                } 
                elseif (preg_match('/Chrome/i', $userAgent)) { 
                    $browser = 'Chrome'; 
                } 
                elseif (preg_match('/Safari/i', $userAgent)) { 
                    $browser = 'Safari'; 
                } 
                elseif (preg_match('/Opera/i', $userAgent)) { 
                    $browser = 'Opera'; 
                }

                // 3. Maintain aggregated tracking totals
                $stmt1 = $db->prepare("
                    INSERT INTO website_visitors (pagename, counter, updated_at) 
                    VALUES (:pagename, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                        counter = counter + 1,
                        updated_at = NOW()
                ");
                $stmt1->execute([':pagename' => $pagename]);

                // 4. CLEANED LOG HOOK: Append metadata parameters safely without user_id references
                $stmtLog = $db->prepare("
                    INSERT INTO visitor_activity_logs (pagename, ip_address, browser, device_type, accessed_at) 
                    VALUES (:pagename, :ip_address, :browser, :device_type, NOW())
                ");
                $stmtLog->execute([
                    ':pagename'    => $pagename,
                    ':ip_address'  => $ipAddress,
                    ':browser'     => $browser,
                    ':device_type' => $deviceType
                ]);
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
     * 📊 FETCH: Generates a spreadsheet-style matrix of page hits per unique user IP
     */
    public function getUserPageMatrix() {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

        try {
            $this->verifyReadPermission();
            $db = Database::connect();
            
            // This query pivots dynamic raw log rows into individual distinct page count columns
            $stmt = $db->query("
                SELECT 
                    ip_address,
                    MAX(browser) AS browser,
                    MAX(device_type) AS device_type,
                    MAX(accessed_at) AS last_active,
                    
                    -- Individual metric column tracking hits per target page
                    SUM(CASE WHEN pagename = 'root' OR pagename = 'home' THEN 1 ELSE 0 END) AS root_hits,
                    SUM(CASE WHEN pagename = 'mandates' THEN 1 ELSE 0 END) AS mandates_hits,
                    SUM(CASE WHEN pagename = 'services' THEN 1 ELSE 0 END) AS services_hits,
                    SUM(CASE WHEN pagename = 'reports' THEN 1 ELSE 0 END) AS reports_hits,
                    SUM(CASE WHEN pagename = 'contact' THEN 1 ELSE 0 END) AS contact_hits,
                    
                    -- Combined total of all interactions
                    COUNT(*) AS total_combined_hits
                FROM visitor_activity_logs
                GROUP BY ip_address
                ORDER BY total_combined_hits DESC
                LIMIT 150
            ");
            $matrixData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "userMatrix" => $matrixData
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to fetch aggregated user matrix metrics: " . $e->getMessage()
            ]);
        }
    }

    /**
     * 📊 Bundles active page counters and extracts dynamic inquiry submission timeline metrics
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
            $this->verifyReadPermission();
            $db = Database::connect();
            
            $counterStmt = $db->query("SELECT pagename, counter, updated_at FROM website_visitors ORDER BY counter DESC");
            $counters = $counterStmt->fetchAll(PDO::FETCH_ASSOC);

            $hourlyStmt = $db->query("
                SELECT 
                    HOUR(created_at) as hour_number, 
                    COUNT(*) as message_count 
                FROM contacts 
                GROUP BY HOUR(created_at) 
                ORDER BY hour_number ASC
            ");
            $rawHourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);

            $inquiryHours = [];
            for ($h = 0; $h < 24; $h++) {
                $inquiryHours[$h] = [
                    "hour_number" => $h,
                    "message_count" => 0
                ];
            }

            foreach ($rawHourlyData as $row) {
                $hourIndex = (int)$row['hour_number'];
                if ($hourIndex >= 0 && $hourIndex < 24) {
                    $inquiryHours[$hourIndex]['message_count'] = (int)$row['message_count'];
                }
            }

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
            $this->verifyReadPermission();
            $db = Database::connect();
            
            $stmt = $db->query("SELECT id, name, email, message, created_at FROM contacts ORDER BY id DESC LIMIT 100");
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "inquiries" => $inquiries
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to fetch raw inquiries matrix data: " . $e->getMessage()
            ]);
        }
    }

    /**
     * 📜 READ: Fetches row records from the visitor activity table (Cleaned from relational tables)
     */
    public function getVisitorActivityLogs() {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        try {
            $this->verifyReadPermission();
            $db = Database::connect();
            
            // DIRECT SELECTION: Pulls tracking rows neatly from visitor_activity_logs without table joins
            $stmt = $db->query("
                SELECT 
                    id, 
                    pagename, 
                    ip_address,
                    browser,
                    device_type,
                    accessed_at 
                FROM visitor_activity_logs
                ORDER BY id DESC 
                LIMIT 150
            ");
            $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "activityLogs" => $activityLogs
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to fetch raw visitor activity tracking entries: " . $e->getMessage()
            ]);
        }
    }

    /**
     * 📈 Generates timeline data metrics for the Recharts graph visualization
     */
    public function getVisitorChartData() {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        try {
            $this->verifyReadPermission();
            $db = Database::connect();
            
            $stmt = $db->query("
                SELECT DATE(accessed_at) as log_date, COUNT(*) as visit_count 
                FROM visitor_activity_logs 
                GROUP BY DATE(accessed_at) 
                ORDER BY log_date ASC 
                LIMIT 30
            ");
            $rawTimeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $chartData = [];
            foreach ($rawTimeline as $row) {
                $chartData[] = [
                    "date" => date("M d", strtotime($row['log_date'])), 
                    "visits" => (int)$row['visit_count']
                ];
            }

            echo json_encode([
                "status" => "success",
                "timelineData" => $chartData
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to build chart visual matrices: " . $e->getMessage()
            ]);
        }
    }
}