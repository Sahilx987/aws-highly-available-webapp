<?php
/**
 * Health Check Endpoint
 * 
 * Used by Application Load Balancer to determine instance health
 * Returns HTTP 200 if healthy, HTTP 503 if unhealthy
 */

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// Check 1: Environment variables configured
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

if (empty($db_host) || empty($db_user) || empty($db_pass) || empty($db_name)) {
    $health['status'] = 'unhealthy';
    $health['checks']['environment'] = 'missing_variables';
    http_response_code(503);
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}
$health['checks']['environment'] = 'ok';

// Check 2: Database connection
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    $health['status'] = 'unhealthy';
    $health['checks']['database_connection'] = 'failed';
    $health['error'] = mysqli_connect_error();
    http_response_code(503);
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}
$health['checks']['database_connection'] = 'ok';

// Check 3: Database query
$result = mysqli_query($conn, "SELECT 1 AS health_check");

if (!$result) {
    $health['status'] = 'unhealthy';
    $health['checks']['database_query'] = 'failed';
    $health['error'] = mysqli_error($conn);
    mysqli_close($conn);
    http_response_code(503);
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}
$health['checks']['database_query'] = 'ok';

// Check 4: Verify submissions table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'submissions'");

if (!$result || mysqli_num_rows($result) === 0) {
    $health['status'] = 'unhealthy';
    $health['checks']['database_schema'] = 'table_missing';
    mysqli_close($conn);
    http_response_code(503);
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}
$health['checks']['database_schema'] = 'ok';

// Optional: Check disk space
$disk_free = disk_free_space('/');
$disk_total = disk_total_space('/');
$disk_usage_percent = 100 - (($disk_free / $disk_total) * 100);

$health['checks']['disk_usage_percent'] = round($disk_usage_percent, 2);

if ($disk_usage_percent > 90) {
    $health['status'] = 'degraded';
    $health['checks']['disk_space'] = 'warning';
}

// Optional: Check memory
$memory_free = memory_get_usage(true);
$memory_limit = ini_get('memory_limit');
$health['checks']['memory_usage_mb'] = round($memory_free / 1024 / 1024, 2);

// Add server info
$health['server'] = [
    'hostname' => gethostname(),
    'ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
    'php_version' => PHP_VERSION
];

// Close connection
mysqli_close($conn);

// Return healthy status
http_response_code(200);
echo json_encode($health, JSON_PRETTY_PRINT);
