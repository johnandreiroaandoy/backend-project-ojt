<?php
namespace App\Controllers;

use Core\Database;
use Exception;

class ReportController {
    public function getReports() {
        header("Content-Type: application/json");
        try {
            $db = Database::connect();
            $stmt = $db->query("SELECT title, year, size, href FROM transparency_reports ORDER BY year DESC");
            $reports = $stmt->fetchAll();

            echo json_encode([
                "status" => "success",
                "data" => $reports
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
}