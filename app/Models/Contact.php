<?php
namespace App\Models;

use Core\Database;
use PDO;
use PDOException;

class Contact {
    
    /**
     * Securely persist filtered and sanitized contact messages into MySQL
     *
     * @param string $name
     * @param string $email
     * @param string $message
     * @return bool
     * @throws PDOException
     */
    public static function save($name, $email, $message) {
        try {
            $db = Database::connect();
            
            // 1. Defensive Layer: Multi-stage XSS sanitization 
            // Converts special characters to safe HTML entities (e.g., < becomes &lt;)
            $secureName    = htmlspecialchars(strip_tags(trim($name)), ENT_QUOTES, 'UTF-8');
            $secureEmail   = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
            $secureMessage = htmlspecialchars(strip_tags(trim($message)), ENT_QUOTES, 'UTF-8');

            // 2. Strict Data Minimization Query Configuration
            $sql = "INSERT INTO contacts (name, email, message) VALUES (:name, :email, :message)";
            $stmt = $db->prepare($sql);
            
            // 3. Explicit Parameter Type Binding 
            // Forcing datatype limits prevents memory overflow payloads or type-juggling attacks
            $stmt->bindParam(':name', $secureName, PDO::PARAM_STR, 255);
            $stmt->bindParam(':email', $secureEmail, PDO::PARAM_STR, 255);
            $stmt->bindParam(':message', $secureMessage, PDO::PARAM_STR, 65535);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            // Rethrow exception to be handled by the controller's catch block
            throw $e;
        }
    }
}