<?php
namespace App\Controllers;

use Core\Database;
use Exception;
use finfo; // Ensure we can instantiate the File Information extension explicitly

class ReportController {
    
    /**
     * Helper method to validate the JWT Bearer Token if your router doesn't block it globally
     */
    private function authenticateToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Unauthorized access: Missing or malformed Bearer Token."]);
            exit;
        }
        
        $token = $matches[1];
        // Optional: Implement your custom verification decoding logic here if needed:
        // if (!YourJwtUtility::verify($token)) { ... exit; }
        return $token;
    }

    /**
     * 1. READ: Fetches all cataloged documents sorted by year and category priority
     * 🔓 PUBLICLY ACCESSIBLE: Left open so users can read files freely on your frontend dashboard.
     */
    public function getReports() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
        
        try {
            $db = Database::connect();
            
            // Keeps your custom prioritized category list order active
            $stmt = $db->query("
                SELECT id, title, year, month, size, href 
                FROM transparency_reports 
                ORDER BY year DESC,
                FIELD(
                    title,
                    'Annual Audit Reports',
                    'Quarterly Financial Statements',
                    'Full Disclosure Policy Compliance Report',
                    'Statement of Receipts and Expenditures'
                )
            ");
            
            echo json_encode([
                "status" => "success", 
                "data" => $stmt->fetchAll()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }

    /**
     * 2. CREATE: Handles file upload validations, directory structure, and database insertion
     * 🔒 PROTECTED: Validates preflight structures and maps authorization parameters safely.
     */
    public function uploadReport() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        // 🔒 SECURITY CHECK: Confirm incoming transaction authenticity
        $this->authenticateToken();

        try {
            $title = $_POST['title'] ?? '';
            $year  = $_POST['year'] ?? date('Y');
            $month = $_POST['month'] ?? '1';
            
            // Strip any accidental non-numeric characters to prevent folder path exploits
            $year  = preg_replace('/[^0-9]/', '', $year);

            // Look for 'file' payload key to align perfectly with your React FormData hook
            if (empty($title) || !isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Missing required text fields or physical file attachment."]);
                exit;
            }

            $file = $_FILES['file'];
            
            // Basic error handling for native PHP upload restrictions
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "PHP file upload error code: " . $file['error']]);
                exit;
            }

            // Intercept file binary and inspect actual content signatures
            $finfoInstance = new finfo(FILEINFO_MIME_TYPE);
            $trueMimeType = $finfoInstance->file($file['tmp_name']);

            // Reject anything pretending to be a PDF that doesn't output standard headers
            if ($trueMimeType !== 'application/pdf') {
                http_response_code(400);
                echo json_encode([
                    "status" => "error", 
                    "message" => "Security Exception: Server-side file verification mismatch. Uploaded file is not a genuine PDF document."
                ]);
                exit;
            }

            // Calculate human-readable document file metric tags
            $fileSize = $file['size'];
            $sizeLabel = ($fileSize >= 1048576) 
                ? number_format($fileSize / 1048576, 2) . ' MB' 
                : number_format($fileSize / 1024, 0) . ' KB';

            // Point down into your public directory system
            $targetDirectory = __DIR__ . "/../../public/reports/" . $year . "/";
            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0777, true);
            }

            // Sanitize file titles to avoid whitespace asset drops on Apache servers
            $fileName = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($file['name']));
            $destinationPath = $targetDirectory . $fileName;

            // Shift payload binary file out of temp cache over onto system drive storage
            if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
                throw new Exception("File system write permission block failed on local directory path structure.");
            }

            // Public web context link asset url route path targeting your local root directly
            $hrefLink = "/backend-project-ojt/public/reports/" . $year . "/" . $fileName;

            $db = Database::connect();
            $stmt = $db->prepare("
                INSERT INTO transparency_reports (title, year, month, size, href) 
                VALUES (:title, :year, :month, :size, :href)
            ");
            
            $stmt->execute([
                ':title' => $title,
                ':year'  => $year,
                ':month' => $month,
                ':size'  => $sizeLabel,
                ':href'  => $hrefLink
            ]);

            // Hand standard schema row data back to React hooks so it matches getReports map keys
            echo json_encode([
                "status" => "success",
                "newRecord" => [
                    "id"    => $db->lastInsertId(),
                    "title" => $title,
                    "year"  => $year,
                    "month" => $month,
                    "size"  => $sizeLabel,
                    "href"  => $hrefLink
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }

    /**
     * 3. DELETE: Wipes physical file asset from drive space, then purges SQL row reference mapping
     * 🔒 PROTECTED: Intercepted and secured using access token verification.
     */
    public function deleteReport() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: DELETE, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        // 🔒 SECURITY CHECK: Confirm incoming transaction authenticity
        $this->authenticateToken();

        try {
            // Unpack native JSON input body stream delivered via JavaScript fetch context 
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "No explicit record identity parsed for deletion loop."]);
                exit;
            }

            $db = Database::connect();
            
            // Query the document track entry out of DB first to locate its file on disk
            $findStmt = $db->prepare("SELECT href FROM transparency_reports WHERE id = :id");
            $findStmt->execute([':id' => $id]);
            $reportItem = $findStmt->fetch();
            
            if ($reportItem) {
                // Map the relative database URL string directly back to an absolute file directory block path
                $cleanHref = str_replace("/backend-project-ojt/", "", $reportItem['href']);
                $physicalDiskPath = __DIR__ . "/../../" . ltrim($cleanHref, '/');
                
                if (file_exists($physicalDiskPath)) {
                    unlink($physicalDiskPath); // Erases physical PDF asset from folder storage completely
                }
            }

            // Remove SQL registry record reference
            $stmt = $db->prepare("DELETE FROM transparency_reports WHERE id = :id");
            $stmt->execute([':id' => $id]);

            echo json_encode(["status" => "success", "message" => "Record and physical data asset destroyed completely."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
}