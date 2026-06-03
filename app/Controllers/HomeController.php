<?php
namespace App\Controllers;

use Core\Controller;
use Core\Database; 
use PDOException;
use App\Models\Contact; // 🟢 Import the newly created Contact model

class HomeController extends Controller {
    
    /**
     * Default HTML fallback view for the backend URL root
     */
    public function index() {
        $this->view('home', [
            'title' => 'CGO Accountant API',
            'status' => 'Active and Operating'
        ]);
    }

    /**
     * CORS Utility Header Helper to keep code clean and allow React access
     */
    private function setCorsHeaders() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Content-Type: application/json; charset=UTF-8");
        
        // Gracefully exit early if the browser sends a preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * 📊 GET /api/reports
     * Delivers dynamic financial transparency documents directly from MySQL to Reports.jsx
     */
    public function getReports() {
        $this->setCorsHeaders();

        try {
            // 1. Establish database connection link
            $db = Database::connect();
            
            // 2. Fetch the transparency report lists from the live table
            $stmt = $db->query("SELECT title, year, size, href FROM transparency_reports ORDER BY id DESC");
            $reports = $stmt->fetchAll();

            // 3. Dispatch data block back to React UI
            echo json_encode([
                'status' => 'success',
                'data' => $reports
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to query database records: ' . $e->getMessage()
            ]);
        }
        return;
    }

    /**
     * ✉️ POST /api/contact
     * Captures, sanitizes, and processes contact form submissions from Contact.jsx
     */
    public function handleContactSubmit() {
        $this->setCorsHeaders();

        // Grab incoming raw JSON body payload from the React state dispatch
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        // Sanitize and validate fields
        $name = isset($input['name']) ? strip_tags(trim($input['name'])) : '';
        $email = isset($input['email']) ? filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL) : false;
        $message = isset($input['message']) ? strip_tags(trim($input['message'])) : '';

        if (empty($name) || !$email || empty($message)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Please provide a valid name, email address, and message details.'
            ]);
            return;
        }

        try {
            // 🟢 SOLID MVC IMPLEMENTATION: Direct SQL logic is offloaded to the Model layer
            Contact::save($name, $email, $message);

            // Return clean success state bundle without generating tracking strings
            echo json_encode([
                'status' => 'success',
                'message' => "Mabuhay, {$name}! Your inquiry has been safely transmitted directly to the City Accountant's Office."
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to log inquiry entry securely: ' . $e->getMessage()
            ]);
        }
        return;
    }

    /**
     * 🟢 ROUTER BRIDGE METHOD: Maps '/api/contact' to 'handleContactSubmit' alternative paths
     */
    public function contact() {
        $this->handleContactSubmit();
    }
}