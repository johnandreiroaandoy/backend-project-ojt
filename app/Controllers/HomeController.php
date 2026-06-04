<?php
namespace App\Controllers;

class HomeController {
    public function index() {
        header("Content-Type: application/json");
        echo json_encode([
            "status" => "success", 
            "message" => "CGO Accountant API Framework Active Engine"
        ]);
    }
}