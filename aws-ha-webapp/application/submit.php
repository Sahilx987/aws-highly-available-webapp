<?php
/**
 * Form Submission Handler
 * 
 * Processes user submissions and stores them in the database
 * Demonstrates database write operations and load balancing
 */

require_once 'config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Validate CSRF token in production
// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//     http_response_code(403);
//     die('Invalid CSRF token');
// }

try {
    // Get database connection
    $conn = get_db_connection();
    
    // Sanitize and validate input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email)) {
        header("Location: index.php?error=missing_fields");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?error=invalid_email");
        exit();
    }
    
    if (strlen($name) > 100) {
        header("Location: index.php?error=name_too_long");
        exit();
    }
    
    if (strlen($message) > 500) {
        header("Location: index.php?error=message_too_long");
        exit();
    }
    
    // Get server hostname to track which instance processed this request
    $hostname = gethostname();
    
    // Prepare SQL statement (prevent SQL injection)
    $sql = "INSERT INTO submissions (name, email, message, server_hostname) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        header("Location: index.php?error=database");
        exit();
    }
    
    // Bind parameters
    $stmt->bind_param("ssss", $name, $email, $message, $hostname);
    
    // Execute statement
    if ($stmt->execute()) {
        $stmt->close();
        close_db_connection($conn);
        
        // Log successful submission
        error_log("New submission: $name ($email) processed by $hostname");
        
        // Redirect to success page
        header("Location: index.php?success=1");
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        close_db_connection($conn);
        
        // Log error
        error_log("Insert failed: " . $error);
        
        // Redirect to error page
        header("Location: index.php?error=database");
        exit();
    }
    
} catch (Exception $e) {
    error_log("Submission error: " . $e->getMessage());
    header("Location: index.php?error=exception");
    exit();
}
