<?php
// app/Models/ContentModel.php

// namespace: Places this class in the correct logical folder so the autoloader can find it instantly
namespace App\Models;

// use: Tells PHP to look inside the Core folder to grab your central Database connection manager class
use Core\Database;
use PDO;

class ContentModel {
    private $db;

    public function __construct() {
        // Connect via your framework's central PDO database core engine!
        $this->db = Database::connect(); 
    }

    /**
     * Pulls all rows assigned to a specific page string
     * Uses PDO Prepared Statements to keep data fetching fast and secure
     */
    public function getPageContent($page) {
        // 1. Prepare the SQL query with a placeholder (:page)
        $query = "SELECT content_key, content_value FROM site_content WHERE page = :page";
        $stmt = $this->db->prepare($query);

        // 2. Execute the statement, securely binding the variable
        $stmt->execute([':page' => $page]);

        // 3. Fetch all records as an associative array
        $rows = $stmt->fetchAll();

        $content = [];
        if ($rows) {
            foreach ($rows as $row) {
                $content[$row['content_key']] = $row['content_value'];
            }
        }
        
        return $content;
    }

    /**
     * Updates or Inserts a text block (Used for your Admin panel dashboard!)
     */
    public function updatePageContent($page, $key, $value) {
        $query = "INSERT INTO site_content (page, content_key, content_value) 
                  VALUES (:page, :key, :value) 
                  ON DUPLICATE KEY UPDATE content_value = :value_update";
                  
        $stmt = $this->db->prepare($query);
        
        return $stmt->execute([
            ':page'         => $page,
            ':key'          => $key,
            ':value'        => $value,
            ':value_update' => $value
        ]);
    }
}