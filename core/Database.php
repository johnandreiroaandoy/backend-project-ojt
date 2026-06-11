<?php
// namespace: Structural folder space declaration path indicating this class belongs to the core system engine layer.
namespace Core;

// use: Imports the PHP Data Objects (PDO) classes so the code can talk securely to your database server.
use PDO;
use PDOException;

// class: A blueprint class container holding all application operations for database connections.
class Database {
    
    // private static: A private restricted variable locked to this class. It stores the single active database pipeline.
    // ?PDO $instance = null: The question mark means it can either hold a live PDO database resource or be completely empty (null).
    private static ?PDO $instance = null;

    /**
     * Docblock: Programmer's comment explaining that this method builds a Singleton instance 
     * to avoid overloading database sockets on your host system.
     */
    public static function connect() {
        
        // if: A conditional gateway checking if our database connection instance is empty (null).
        // This is the core magic of a Singleton: it only runs the setup code ONCE per request lifecycle.
        if (self::$instance === null) {
            
            // 🟢 DYNAMICALLY READ FROM THE .ENV FILE
            
            // getenv(): Reads hidden environment variables from your system configuration file (.env).
            // ?: 'localhost': Fallback check. If the .env file is missing or unreadable, it defaults to local hosting parameters.
            $host    = getenv('DB_HOST') ?: 'localhost';
            $db      = getenv('DB_NAME') ?: 'cgo_accountant'; 
            $user    = getenv('DB_USER') ?: 'root';
            
            // !== false: Strict comparison to check if a password environment string exists, even if it is blank.
            $pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''; 
            
            // $charset: Enforces modern encoding rules (utf8mb4) so that special symbols or emojis won't corrupt text fields.
            $charset = 'utf8mb4';

            // $dsn: Data Source Name. A specialized connection address string that tells PDO exactly what database type (mysql) and host to call.
            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            
            // $options: Configuration tuning array that alters how PDO behaves globally during runtime.
            $options = [
                // PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION: Tells PDO to automatically throw explicit errors if a SQL query breaks.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                
                // PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC: Sets data queries to return pure associative arrays mapped directly by column names.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                
                // PDO::ATTR_EMULATE_PREPARES => false: Native Prepares. Disables fake SQL emulation, forcing your queries to compile directly on MySQL for maximum SQL injection protection.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // try: An isolation cage that monitors the database startup sequence for fatal connection crashes.
            try {
                // new PDO(): Instantiates and fires up the physical connection engine using your credential variables and settings.
                // self::$instance = : Saves that fresh live pipeline straight into our persistent class variable slot.
                self::$instance = new PDO($dsn, $user, $pass, $options);
                
            // catch: Captures connection crashes (like if XAMPP MySQL is turned off or your password is wrong).
            } catch (PDOException $e) {
                // header(): Instructs the browser that this emergency error message is packed as a standardized JSON data string.
                header('Content-Type: application/json');
                // http_response_code(500): Issues an internal system failure status code to let your React frontend know something broke.
                http_response_code(500);
                
                // die(): Instantly kills further script execution and echoes out a clean, structured JSON notification layout.
                // $e->getMessage(): Reads out the exact error description string coming from the MySQL service for fast debugging.
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Database connection breakdown: ' . $e->getMessage()
                ]));
            }
        }
        
        // return: Hands back the active, running database pipeline tool to whatever model or controller called it.
        return self::$instance;
    }
}