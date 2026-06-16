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
    
} // Ends the HomeController class container