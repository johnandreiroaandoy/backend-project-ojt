<?php
// namespace: Defines the logical folder path where this file lives so your application's autoloader can find it.
namespace App\Controllers;

// class: A container that groups related behaviors together. This one handles requests hitting the homepage/root path.
class HomeController {
    
    // public function: An accessible action method. "index" is the industry-standard name for the default entry point.
    public function index() {
        
        // header(): Sends a raw network instruction telling the browser/client to interpret the output strictly as a JSON data string.
        header("Content-Type: application/json");
        
        // echo: Outbound transmitter command that prints text directly into the HTTP response body.
        // json_encode(): A built-in PHP function that converts a readable PHP associative array into a universal JSON format string.
        echo json_encode([
            // "status" => "success": A custom key-value descriptor telling the frontend that the backend infrastructure is healthy.
            "status" => "success", 
            // "message" => "...": A friendly text confirmation verifying that your custom MVC framework engine is successfully active.
            "message" => "CGO Accountant API Framework Active Engine"
        ]);
        
    } // Ends the index function action block

    /**
     * saveConfig: Handles secure inbound POST requests from the React admin panel
     * to update system configuration layout JSON blocks saved on disk.
     */
    public function saveConfig() {
        // 🔓 Set API headers for React CORS clearance
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Content-Type: application/json");

        // Handle preflight browser safety checks cleanly
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        // Restrict communication exclusively to POST data streams
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(["status" => "error", "message" => "Invalid HTTP method channel."]);
            exit;
        }

        // Parse incoming raw request stream payload
        $rawInput = file_get_contents('php://input');
        $payload = json_decode($rawInput, true);

        $targetFile = $payload['targetFile'] ?? '';
        $data = $payload['data'] ?? null;

        if (!$targetFile || !$data) {
            echo json_encode(["status" => "error", "message" => "Incomplete request configuration parameters."]);
            exit;
        }

        // 🛡️ Security Whitelist: Map target references precisely to files inside public/data/
        // ✅ UPDATED: Added 'vision_mission.json' into the authorized storage map array
        $allowedFiles = [
            'header_data.json'        => __DIR__ . '/../../public/data/header_data.json',
            'mandate_data.json'       => __DIR__ . '/../../public/data/mandate_data.json',
            'services.json'           => __DIR__ . '/../../public/data/services.json',
            'services_directory.json' => __DIR__ . '/../../public/data/services_directory.json',
            'contact_info.json'       => __DIR__ . '/../../public/data/contact_info.json',
            'vision_mission.json'     => __DIR__ . '/../../public/data/vision_mission.json' 
        ];

        if (!array_key_exists($targetFile, $allowedFiles)) {
            echo json_encode(["status" => "error", "message" => "Access to specified layout target is restricted."]);
            exit;
        }

        $destinationPath = $allowedFiles[$targetFile];
        
        // 🟢 Guard logic specifically for header layout deep-merging
        if ($targetFile === 'header_data.json') {
            if (!file_exists($destinationPath)) {
                echo json_encode(["status" => "error", "message" => "Target header template file missing from file directory map."]);
                exit;
            }
            
            // 1. Load the original raw document data
            $existingData = json_decode(file_get_contents($destinationPath), true);
            
            // 2. Map structural subkeys safely without damaging asset icons arrays
            $existingData['topBar']['officialTagline'] = $data['officialTagline'] ?? $existingData['topBar']['officialTagline'];
            $existingData['hero']['titleLine1']        = $data['titleLine1'] ?? $existingData['hero']['titleLine1'];
            $existingData['hero']['titleLine2']        = $data['titleLine2'] ?? $existingData['hero']['titleLine2'];
            $existingData['hero']['tagline']           = $data['tagline'] ?? $existingData['hero']['tagline'];
            
            // 3. Move the combined dataset over to processing
            $finalData = $existingData;
        } else {
            // Mandate, landing intro text, contact info, vision/mission, and catalog receive clean rewrites
            $finalData = $data;
        }

        // Format payload cleanly back to human-readable strings before file persistence write
        $jsonString = json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Update target data repository module on local server disk storage
        if (file_put_contents($destinationPath, $jsonString) !== false) {
            echo json_encode(["status" => "success", "message" => "Layout system configurations safely updated!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to write structural resource data onto disk. Verify folder write clearance."]);
        }
        exit;
    }
    
} // Ends the HomeController class container