<?php
// config/database.php - Fixed database configuration

class DatabaseConfig {
    // Database credentials
    private $host = 'localhost';
    private $database = 'fraud_detection_system';  // Your database name
    private $username = 'root';                    // XAMPP default
    private $password = '';                        // XAMPP default (empty)
    private $port = 3306;                         // XAMPP default
    
    private $pdo;
    
    public function getConnection() {
        if ($this->pdo === null) {
            try {
                // Create PDO connection with proper options
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_STRINGIFY_FETCHES => false
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
                
                // Test the connection with a simple query
                $this->pdo->query("SELECT 1");
                
            } catch (PDOException $e) {
                // Log the error but don't expose database details to users
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please check your configuration.");
            }
        }
        
        return $this->pdo;
    }
    
    // Test database connection
    public function testConnection() {
        try {
            $pdo = $this->getConnection();
            
            // Check if required tables exist using simple query
            $requiredTables = ['User', 'Transaction', 'Category', 'Profile', 'AnomalyRecord', 'Alert'];
            $existingTables = [];
            
            // Get all tables in database
            $stmt = $pdo->query("SHOW TABLES");
            $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Check which required tables exist
            foreach ($requiredTables as $table) {
                if (in_array($table, $allTables)) {
                    $existingTables[] = $table;
                }
            }
            
            $missingTables = array_diff($requiredTables, $existingTables);
            
            return [
                'success' => true,
                'message' => 'Database connection successful',
                'existing_tables' => $existingTables,
                'missing_tables' => $missingTables,
                'all_tables' => $allTables
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Helper function for other files to get database connection
function getDatabaseConnection() {
    $config = new DatabaseConfig();
    return $config->getConnection();
}
?>