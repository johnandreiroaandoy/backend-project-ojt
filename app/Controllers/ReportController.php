<?php
// namespace: Sets up the internal folder structure path so the application's autoloader can locate this file.
namespace App\Controllers;

// use: References external layout files so this controller can use your database tools and error handlers.
use Core\Database;
use Exception;

// class: A grouping block that contains operations dealing with city transparency or financial reports.
class ReportController {
    
    // public function: An accessible action block designed to process the page request when a user visits your reports section.
    public function getReports() {
        
        // header(): Issues a browser instruction declaring that all outputs returned by this file will be formatted as a JSON data string.
        header("Content-Type: application/json");
        
        // try: A diagnostic safety cage that watches the database code blocks below for unexpected failures.
        try {
            
            // Database::connect(): Triggers your system configuration utility to open up an active connection channel to MySQL.
            $db = Database::connect();
            
            // $db->query(): Sends a direct read command to MySQL.
            // SELECT...: Fetches specific column variables (title, year, size, file link) from the transparency table.
            // ORDER BY year DESC: Organizes the files automatically from the newest year down to the oldest year.
            $stmt = $db->query("SELECT title, year, size, href FROM transparency_reports ORDER BY year DESC");
            
            // $stmt->fetchAll(): Gathers all matching records pulled from MySQL and compiles them into a clean PHP index data list.
            $reports = $stmt->fetchAll();

            // echo: Transmits information back to your React application interface.
            // json_encode(): Packs your PHP database entries into a clean JSON string array format that JavaScript can read effortlessly.
            echo json_encode([
                // "status" => "success": A notification key proving to React that the backend successfully retrieved the information.
                "status" => "success",
                // "data" => $reports: Ships the physical rows of financial records straight to your React table layout.
                "data" => $reports
            ]);
            
        // catch: The crisis control checkpoint that traps database crashes safely without revealing core raw configuration paths to the public.
        } catch (Exception $e) {
            // http_response_code(500): Issues a protocol indicator declaring an "Internal Server Error" occurred.
            http_response_code(500);
            
            // $e->getMessage(): Grabs the technical description of what broke inside the code and displays it cleanly inside a JSON block.
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
}