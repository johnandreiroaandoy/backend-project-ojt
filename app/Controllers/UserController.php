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

    /**
     * 👥 NEW: Tracks visitor time, activity heartbeat, and expiration "death" windows
     */
    public function trackVisit() {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type");

        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // 1. Generate an anonymous unique signature tracker for today
            $sessionID = session_id();
            $todayDate = date('Y-m-d');
            $sessionHash = hash('sha256', $sessionID . $todayDate);

            // Set session expiration lifespan window (e.g., 30 minutes = 1800 seconds)
            $lifespan = 1800;
            $deathTime = date('Y-m-d H:i:s', time() + $lifespan);

            // 2. Connect via your built-in framework Database link
            $db = Database::connect(); 

            // 3. Log the "time" (birth) or update activity heartbeat and session "death" window
            $stmt = $db->prepare("
                INSERT INTO website_visitors (session_hash, session_death) 
                VALUES (:session_hash, :session_death)
                ON DUPLICATE KEY UPDATE 
                    last_activity = CURRENT_TIMESTAMP,
                    session_death = :session_death_update
            ");

            $stmt->execute([
                ':session_hash'         => $sessionHash,
                ':session_death'         => $deathTime,
                ':session_death_update'  => $deathTime
            ]);

            // 4. Gather total collective unique traffic reach metrics
            $countStmt = $db->query("SELECT COUNT(*) as total_visitors FROM website_visitors");
            $result = $countStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "total_visitors" => (int)$result['total_visitors']
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Tracking node error: " . $e->getMessage()
            ]);
        }
    }
}