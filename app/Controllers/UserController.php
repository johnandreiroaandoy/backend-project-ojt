<?php
namespace App\Controllers;

use Core\Database;
use Exception;
use PDO;

class UserController {
    // Reads a request header safely across Apache and normal PHP server variables.
    private function getRequestHeaderValue($name) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $headerName => $headerValue) {
                if (strcasecmp($headerName, $name) === 0) {
                    return $headerValue;
                }
            }
        }

        return '';
    }

    // Normalizes device/platform strings before storing them in analytics tables.
    private function cleanDeviceLabel($value) {
        $value = trim((string)$value);
        $value = trim($value, "\"' ");

        if ($value === '' || $value === '?0') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value);
        $value = preg_replace('/[^\p{L}\p{N}\s\.\-\_\(\)\/]/u', '', $value);

        return substr(trim($value), 0, 120);
    }

    private function inferMobileDeviceBrand($model, $userAgent) {
        $model = $this->cleanDeviceLabel($model);
        $modelLower = strtolower($model);
        $userAgentLower = strtolower((string)$userAgent);

        if (preg_match('/iphone/i', $userAgent)) {
            return 'Apple iPhone';
        }

        if (preg_match('/ipad/i', $userAgent)) {
            return 'Apple iPad';
        }

        if (strpos($modelLower, 'pixel') !== false || strpos($userAgentLower, 'pixel') !== false) {
            return 'Google';
        }

        if (preg_match('/^(sm-|gt-|sch-|sgh-)/i', $model)) {
            return 'Samsung';
        }

        if (preg_match('/^(redmi|poco|mi\s|m20|m21|m22|m23|m24|m25)/i', $model) || strpos($userAgentLower, 'xiaomi') !== false) {
            return 'Xiaomi';
        }

        if (preg_match('/^(cph|p[a-z]{2,3}m|oppo)/i', $model) || strpos($userAgentLower, 'oppo') !== false) {
            return 'OPPO';
        }

        if (preg_match('/^(rmx|realme)/i', $model) || strpos($userAgentLower, 'realme') !== false) {
            return 'Realme';
        }

        if (preg_match('/^(vivo|v\d{4})/i', $model) || strpos($userAgentLower, 'vivo') !== false) {
            return 'Vivo';
        }

        if (preg_match('/^(huawei|ane-|lya-|vog-|mar-|yal-)/i', $model) || strpos($userAgentLower, 'huawei') !== false) {
            return 'Huawei';
        }

        if (preg_match('/^(honor|bkl-|col-|jsn-)/i', $model) || strpos($userAgentLower, 'honor') !== false) {
            return 'Honor';
        }

        if (preg_match('/^(moto|xt\d+)/i', $model) || strpos($userAgentLower, 'motorola') !== false) {
            return 'Motorola';
        }

        if (preg_match('/^(infinix|tecno|itel)/i', $model, $matches) || preg_match('/(infinix|tecno|itel)/i', $userAgent, $matches)) {
            return ucfirst(strtolower($matches[1]));
        }

        return '';
    }

    private function buildMobileDeviceLabel($model, $userAgent) {
        $model = $this->cleanDeviceLabel($model);
        $brand = $this->inferMobileDeviceBrand($model, $userAgent);

        if ($brand !== '' && $model !== '' && stripos($model, $brand) !== 0) {
            return trim($brand . ' ' . $model);
        }

        return $brand !== '' ? $brand : $model;
    }

    // Browser client hints can explicitly tell us if the visitor is on a mobile device.
    private function isClientMobileHint() {
        $mobileHint = strtolower(trim((string)(
            $_GET['mobile'] ?? ''
            ?: $_GET['is_mobile'] ?? ''
            ?: $this->getRequestHeaderValue('X-Client-Is-Mobile')
            ?: $this->getRequestHeaderValue('Sec-CH-UA-Mobile')
        ), "\"' "));

        return in_array($mobileHint, ['?1', '1', 'true', 'yes'], true);
    }

    // Filters noisy Android model values like "K", "wv", or "Linux" so the dashboard shows "Phone" instead.
    private function isUsableMobileDeviceModel($model) {
        $model = $this->cleanDeviceLabel($model);

        if ($model === '') {
            return false;
        }

        if (strlen($model) < 2) {
            return false;
        }

        return !preg_match('/^(wv|mobile|tablet|android|linux|phone|unknown|not available|unavailable)$/i', $model);
    }

    // Keeps older local databases working by adding the os column the first time analytics needs it.
    private function ensureVisitorActivityLogOsColumn(PDO $db) {
        static $checked = false;
        if ($checked) {
            return;
        }

        $stmt = $db->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'visitor_activity_logs'
              AND COLUMN_NAME = 'os'
        ");

        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec("ALTER TABLE visitor_activity_logs ADD COLUMN os VARCHAR(80) NULL AFTER device_type");
        }

        $checked = true;
    }

    // Converts ?page=2&per_page=10 into safe LIMIT/OFFSET values for paginated API endpoints.
    private function getPaginationParams($defaultPerPage = 10, $maxPerPage = 50) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, (int)($_GET['per_page'] ?? $defaultPerPage));
        $perPage = min($perPage, $maxPerPage);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage
        ];
    }

    // Standard response metadata used by React pagination controls.
    private function buildPaginationMeta($total, $pagination) {
        $total = (int)$total;
        $perPage = (int)$pagination['per_page'];

        return [
            'current_page' => (int)$pagination['page'],
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage))
        ];
    }

    // Determines the visitor OS using explicit browser hints first, then falls back to User-Agent parsing.
    private function resolveOperatingSystem($userAgent) {
        $platformHint = $this->cleanDeviceLabel(
            $_GET['platform'] ?? ''
            ?: $this->getRequestHeaderValue('X-Client-Platform')
            ?: $this->getRequestHeaderValue('Sec-CH-UA-Platform')
        );
        $platform = strtolower(trim($platformHint, "\"' "));
        $userAgent = (string)$userAgent;
        $isMobileClient = $this->isClientMobileHint();

        if ($platform !== '') {
            if (strpos($platform, 'win') !== false) {
                return 'Windows';
            }

            if (strpos($platform, 'mac') !== false) {
                return 'macOS';
            }

            if (strpos($platform, 'chrome os') !== false || strpos($platform, 'chromium os') !== false) {
                return 'ChromeOS';
            }

            if (strpos($platform, 'android') !== false || preg_match('/Android/i', $userAgent)) {
                return 'Android';
            }

            if ($isMobileClient && strpos($platform, 'linux') !== false) {
                return 'Android';
            }

            if (strpos($platform, 'ios') !== false) {
                return 'iOS';
            }

            if (strpos($platform, 'linux') !== false) {
                return 'Linux';
            }
        }

        if (preg_match('/CrOS/i', $userAgent)) {
            return 'ChromeOS';
        }

        if (preg_match('/Windows NT/i', $userAgent)) {
            return 'Windows';
        }

        if (preg_match('/iPad/i', $userAgent)) {
            return 'iPadOS';
        }

        if (preg_match('/iPhone|iPod/i', $userAgent)) {
            return 'iOS';
        }

        if (preg_match('/Android/i', $userAgent)) {
            return 'Android';
        }

        if (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
            return 'macOS';
        }

        if (preg_match('/Linux/i', $userAgent)) {
            return 'Linux';
        }

        return 'Unknown';
    }

    // Determines whether the visitor is a Phone, Tablet, or Desktop/Laptop.
    private function resolveDeviceCategory($userAgent) {
        if ($this->isClientMobileHint()) {
            return 'Phone';
        }

        $platformHint = strtolower($this->cleanDeviceLabel(
            $_GET['platform'] ?? ''
            ?: $this->getRequestHeaderValue('X-Client-Platform')
            ?: $this->getRequestHeaderValue('Sec-CH-UA-Platform')
        ));

        if (strpos($platformHint, 'android') !== false || strpos($platformHint, 'ios') !== false) {
            return 'Phone';
        }

        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
            return 'Phone';
        }

        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent)) {
            return 'Tablet';
        }

        return 'Desktop/Laptop';
    }

    // Builds the dashboard device label, preferring a real mobile brand/model when the browser exposes one.
    private function resolveDeviceModel($userAgent, $deviceCategory) {
        $explicitModelSources = [
            $_GET['device_model'] ?? '',
            $this->getRequestHeaderValue('X-Device-Model'),
            $this->getRequestHeaderValue('X-Client-Device-Model'),
            $this->getRequestHeaderValue('Sec-CH-UA-Model')
        ];

        foreach ($explicitModelSources as $model) {
            $cleanModel = $this->cleanDeviceLabel($model);
            if ($cleanModel !== '') {
                if ($deviceCategory === 'Phone' || $deviceCategory === 'Tablet') {
                    if (!$this->isUsableMobileDeviceModel($cleanModel)) {
                        continue;
                    }

                    return $this->buildMobileDeviceLabel($cleanModel, $userAgent);
                }

                return $cleanModel;
            }
        }

        if (preg_match('/Android\s+[^;]+;\s*([^;\)]+?)(?:\s+Build\/|;|\))/i', $userAgent, $matches)) {
            $androidModel = $this->cleanDeviceLabel($matches[1]);
            if ($this->isUsableMobileDeviceModel($androidModel)) {
                return $this->buildMobileDeviceLabel($androidModel, $userAgent);
            }
        }

        if (preg_match('/iPhone/i', $userAgent)) {
            return 'Apple iPhone';
        }

        if (preg_match('/iPad/i', $userAgent)) {
            return 'Apple iPad';
        }

        if (preg_match('/Windows NT/i', $userAgent)) {
            return $deviceCategory;
        }

        if (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
            return $deviceCategory;
        }

        if (preg_match('/Linux/i', $userAgent)) {
            return $deviceCategory;
        }

        return $deviceCategory;
    }
    
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
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Model, X-Client-Device-Model, X-Client-Platform, X-Client-Is-Mobile");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Accept-CH: Sec-CH-UA-Mobile, Sec-CH-UA-Model, Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        try {
            $pagename = isset($_GET['pagename']) ? trim($_GET['pagename']) : 'root';
            if (empty($pagename)) {
                $pagename = 'root';
            }

            $db = Database::connect(); 
            $this->ensureVisitorActivityLogOsColumn($db);

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
                
                $deviceCategory = $this->resolveDeviceCategory($userAgent);
                $deviceType = $this->resolveDeviceModel($userAgent, $deviceCategory);
                $operatingSystem = $this->resolveOperatingSystem($userAgent);

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
                    INSERT INTO visitor_activity_logs (pagename, ip_address, browser, device_type, os, accessed_at) 
                    VALUES (:pagename, :ip_address, :browser, :device_type, :os, NOW())
                ");
                $stmtLog->execute([
                    ':pagename'    => $pagename,
                    ':ip_address'  => $ipAddress,
                    ':browser'     => $browser,
                    ':device_type' => $deviceType,
                    ':os'          => $operatingSystem
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
            $this->ensureVisitorActivityLogOsColumn($db);
            $pagination = $this->getPaginationParams();

            // Count grouped visitor rows first so the frontend knows how many pages exist.
            $countStmt = $db->query("
                SELECT COUNT(*) FROM (
                    SELECT ip_address
                    FROM visitor_activity_logs
                    GROUP BY ip_address
                ) AS visitor_matrix_count
            ");
            $totalRows = (int)$countStmt->fetchColumn();
            
            // Pivot raw logs into one row per IP, then apply backend LIMIT/OFFSET pagination.
            $stmt = $db->prepare("
                SELECT 
                    v.ip_address,
                    -- Use the latest log for display fields so device/OS do not get mixed by MAX().
                    (
                        SELECT latest.browser
                        FROM visitor_activity_logs latest
                        WHERE latest.ip_address = v.ip_address
                        ORDER BY latest.accessed_at DESC, latest.id DESC
                        LIMIT 1
                    ) AS browser,
                    (
                        SELECT latest.device_type
                        FROM visitor_activity_logs latest
                        WHERE latest.ip_address = v.ip_address
                        ORDER BY latest.accessed_at DESC, latest.id DESC
                        LIMIT 1
                    ) AS device_type,
                    (
                        SELECT latest.os
                        FROM visitor_activity_logs latest
                        WHERE latest.ip_address = v.ip_address
                        ORDER BY latest.accessed_at DESC, latest.id DESC
                        LIMIT 1
                    ) AS os,
                    MAX(v.accessed_at) AS last_active,
                    
                    -- Normalize saved route names so slashed and non-slashed routes count in the same column.
                    SUM(CASE WHEN TRIM(BOTH '/' FROM LOWER(v.pagename)) IN ('', 'root', 'home') THEN 1 ELSE 0 END) AS root_hits,
                    SUM(CASE WHEN TRIM(BOTH '/' FROM LOWER(v.pagename)) IN ('mandate', 'mandates') THEN 1 ELSE 0 END) AS mandates_hits,
                    SUM(CASE WHEN TRIM(BOTH '/' FROM LOWER(v.pagename)) LIKE 'service%' THEN 1 ELSE 0 END) AS services_hits,
                    SUM(CASE WHEN TRIM(BOTH '/' FROM LOWER(v.pagename)) LIKE 'report%' THEN 1 ELSE 0 END) AS reports_hits,
                    SUM(CASE WHEN TRIM(BOTH '/' FROM LOWER(v.pagename)) LIKE 'contact%' THEN 1 ELSE 0 END) AS contact_hits,
                    
                    -- Combined total of all interactions
                    COUNT(*) AS total_combined_hits
                FROM visitor_activity_logs v
                GROUP BY v.ip_address
                ORDER BY total_combined_hits DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
            $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
            $stmt->execute();
            $matrixData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "userMatrix" => $matrixData,
                "userMatrixPagination" => $this->buildPaginationMeta($totalRows, $pagination)
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
            $pagination = $this->getPaginationParams();
            
            // Total contact count drives the frontend submissions pagination controls.
            $countStmt = $db->query("SELECT COUNT(*) FROM contacts");
            $totalRows = (int)$countStmt->fetchColumn();

            // Return only the current requested page of inquiries.
            $stmt = $db->prepare("
                SELECT id, name, email, message, created_at
                FROM contacts
                ORDER BY id DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
            $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
            $stmt->execute();
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "inquiries" => $inquiries,
                "inquiriesPagination" => $this->buildPaginationMeta($totalRows, $pagination)
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
            $this->ensureVisitorActivityLogOsColumn($db);
            $pagination = $this->getPaginationParams();

            // Count all raw activity logs before applying page size.
            $countStmt = $db->query("SELECT COUNT(*) FROM visitor_activity_logs");
            $totalRows = (int)$countStmt->fetchColumn();
            
            // Pull only the current page of raw tracking rows for the System Logs tab.
            $stmt = $db->prepare("
                SELECT 
                    id, 
                    pagename, 
                    ip_address,
                    browser,
                    device_type,
                    os,
                    accessed_at 
                FROM visitor_activity_logs
                ORDER BY id DESC 
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
            $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
            $stmt->execute();
            $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "activityLogs" => $activityLogs,
                "activityLogsPagination" => $this->buildPaginationMeta($totalRows, $pagination)
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
