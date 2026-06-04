<?php
namespace App\Controllers;

// 🟢 IMPORT THE MODEL LAYER
use App\Models\Contact;
use Exception;

class ContactController {
    public function handleContactSubmit() {
        header("Content-Type: application/json");
        try {
            // 1. Gather the raw incoming payload from React
            $input = json_decode(file_get_contents('php://input'), true);

            // 2. Structural Validation Gate
            if (empty($input['name']) || empty($input['email']) || empty($input['message'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "All fields are required."]);
                return;
            }

            // 3. 🟢 PASS DATA TO MODEL (Model sanitizes data and interacts with MySQL)
            $success = Contact::save($input['name'], $input['email'], $input['message']);

            // 4. Send Response Status back to Frontend
            if ($success) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Thank you, " . htmlspecialchars($input['name']) . "! Your inquiry has been logged securely."
                ]);
            } else {
                throw new Exception("The database was unable to store the record.");
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
        }
    }
}