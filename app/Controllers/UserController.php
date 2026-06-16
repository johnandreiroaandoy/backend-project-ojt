<?php
namespace App\Controllers;

use Core\Database;
use Exception;

class UserController {
    
    /**
     * Verifies if an email address exists inside the database registry table
     */
    public function verifyEmail() {
        // Inform the React client that we are responding with standard JSON formatting
        header("Content-Type: application/json");
        
        // Grab the incoming JSON raw data stream from the React frontend fetch request
        $input = json_decode(file_get_contents("php://input"), true);
        $email = isset($input['email']) ? trim($input['email']) : '';

        // Fail-safe validation: If the payload parameters are blank
        if (empty($email)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error", 
                "message" => "Email address field is required."
            ]);
            return;
        }

        try {
            // Establish connection through your system's core database wrapper
            $db = Database::connect();
            
            // 💡 NOTE: Change 'users' and 'email' if your MySQL table/column names are different!
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
            // Send back a server processing error response code if something breaks
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Database error: " . $e->getMessage()
            ]);
        }
    }
}