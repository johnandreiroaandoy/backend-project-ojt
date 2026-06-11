<?php
// namespace: Defines the directory folder organization path so the application knows this is a Model component.
namespace App\Models;

// use: Imports required database dependencies and PDO constants so this file can run database queries safely.
use Core\Database;
use PDO;
use PDOException;

// class: A blueprint class container representing a single entity or database table context ("contacts").
class Contact {
    
    /**
     * Docblock: This is a professional programmer's comment section. It acts as documentation, 
     * explaining what parameters ($name, $email, $message) are required, what type of value 
     * it returns (bool = true/false), and what errors it might throw.
     */
    public static function save($name, $email, $message) {
        
        // try: An isolation block that watches all database transactions for unexpected system errors or connection drops.
        try {
            
            // Database::connect(): Requests an active, open connection link from your system's MySQL service.
            $db = Database::connect();
            
            // 1. Defensive Layer: Multi-stage XSS sanitization 
            
            // trim(): Cuts off accidental whitespace padding from the beginning and end of the user's input.
            // strip_tags(): Scrubs out and removes risky HTML tags (like <script>) to completely eliminate Cross-Site Scripting (XSS).
            // htmlspecialchars(..., ENT_QUOTES): Turns remaining special symbols into safe HTML text tags (e.g., changing < into &lt;).
            $secureName    = htmlspecialchars(strip_tags(trim($name)), ENT_QUOTES, 'UTF-8');
            
            // filter_var(..., FILTER_SANITIZE_EMAIL): Standard PHP filter that strips out illegal text characters that shouldn't exist in an email string.
            $secureEmail   = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
            $secureMessage = htmlspecialchars(strip_tags(trim($message)), ENT_QUOTES, 'UTF-8');

            // 2. Strict Data Minimization Query Configuration
            
            // Writes an optimized SQL query string using placeholder labels (:name) instead of gluing variables directly into the query.
            $sql = "INSERT INTO contacts (name, email, message) VALUES (:name, :email, :message)";
            
            // $db->prepare(): Pre-compiles the query blueprint on the MySQL server, creating an unchangeable execution structure.
            $stmt = $db->prepare($sql);
            
            // 3. Explicit Parameter Type Binding 
            
            // bindParam(): Explicitly maps the cleaned text variables onto your query placeholders.
            // PDO::PARAM_STR: Commands MySQL to treat the data purely as safe text literals, making SQL Injection attacks mechanically impossible.
            // 255 / 65535: Enforces data clipping barriers to prevent memory overflow payloads from attacking your database size limits.
            $stmt->bindParam(':name', $secureName, PDO::PARAM_STR, 255);
            $stmt->bindParam(':email', $secureEmail, PDO::PARAM_STR, 255);
            $stmt->bindParam(':message', $secureMessage, PDO::PARAM_STR, 65535);
            
            // $stmt->execute(): Runs the pre-compiled query statement with your safe variables, returning true on success or false on failure.
            return $stmt->execute();
            
        // catch: Triggers instantly if a database connection error or query failure occurs during execution.
        } catch (PDOException $e) {
            
            // throw $e: Forwards the database error straight up to the Controller's catch block so the user gets a clean JSON error response.
            throw $e;
        }
    }
}