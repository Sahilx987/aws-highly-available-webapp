<?php
/**
 * Database Configuration
 * 
 * Manages database connections using environment variables
 * Supports both Apache SetEnv and PHP-FPM environment configurations
 */

/**
 * Get database connection
 * 
 * Uses singleton pattern to reuse connection across requests
 * Reads credentials from environment variables set by Apache or PHP-FPM
 * 
 * @return mysqli Database connection object
 * @throws Exception if configuration is missing or connection fails
 */
function get_db_connection() {
    static $conn = null;
    
    // Return existing connection if available
    if ($conn !== null && $conn->ping()) {
        return $conn;
    }
    
    // Read configuration from environment variables
    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');
    $db_name = getenv('DB_NAME');
    
    // Validate configuration
    if (empty($db_host) || empty($db_user) || empty($db_pass) || empty($db_name)) {
        error_log("Database configuration missing. Check environment variables.");
        error_log("DB_HOST: " . ($db_host ? "set" : "missing"));
        error_log("DB_USER: " . ($db_user ? "set" : "missing"));
        error_log("DB_PASS: " . ($db_pass ? "set" : "missing"));
        error_log("DB_NAME: " . ($db_name ? "set" : "missing"));
        throw new Exception("Database configuration error");
    }
    
    // Create connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Database connection failed");
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

/**
 * Close database connection
 * 
 * @param mysqli $conn Connection to close
 */
function close_db_connection($conn) {
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}

/**
 * Execute a prepared statement safely
 * 
 * @param mysqli $conn Database connection
 * @param string $sql SQL query with placeholders
 * @param string $types Type definition string (e.g., "ssi" for string, string, integer)
 * @param array $params Parameters to bind
 * @return mysqli_result|bool Query result or false on failure
 */
function execute_query($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}
