#!/bin/bash
#
# EC2 User Data Script
# 
# This script runs on instance launch to configure the web application
# It installs dependencies, configures Apache/PHP, and deploys the application
#
# IMPORTANT: Replace the following placeholders before use:
# - YOUR_RDS_ENDPOINT_HERE
# - YOUR_DB_PASSWORD_HERE
#

set -e  # Exit on error
set -x  # Debug logging

exec > >(tee /var/log/user-data.log)  # Log all output
exec 2>&1

echo "========================================="
echo "User Data Script Started: $(date)"
echo "========================================="

# ===== PHASE 1: SYSTEM SETUP =====
echo "[$(date)] Phase 1: System setup"

apt-get update -y
DEBIAN_FRONTEND=noninteractive apt-get upgrade -y

# Install required packages
apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-mysql \
    mysql-client \
    curl \
    wget \
    unzip

echo "[$(date)] Packages installed successfully"

# ===== PHASE 2: CONFIGURE DATABASE CREDENTIALS =====
echo "[$(date)] Phase 2: Setting up database configuration"

# IMPORTANT: Replace these with your actual values
DB_HOST="YOUR_RDS_ENDPOINT_HERE"  # e.g., app-rdsdb.xxxxx.eu-west-3.rds.amazonaws.com
DB_USER="admin"
DB_PASS="YOUR_DB_PASSWORD_HERE"   # Replace with your RDS master password
DB_NAME="webapp_db"

# Validate credentials are set
if [[ "$DB_HOST" == "YOUR_RDS_ENDPOINT_HERE" ]] || [[ "$DB_PASS" == "YOUR_DB_PASSWORD_HERE" ]]; then
    echo "ERROR: Database credentials not configured!"
    echo "Please edit user-data.sh and replace placeholder values"
    exit 1
fi

echo "[$(date)] Database credentials configured"

# ===== PHASE 3: CONFIGURE APACHE =====
echo "[$(date)] Phase 3: Configuring Apache web server"

# Create Apache configuration with environment variables
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    
    # Set environment variables for PHP
    SetEnv DB_HOST "$DB_HOST"
    SetEnv DB_USER "$DB_USER"
    SetEnv DB_PASS "$DB_PASS"
    SetEnv DB_NAME "$DB_NAME"
    
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Enable required Apache modules
a2enmod rewrite headers
systemctl enable apache2

echo "[$(date)] Apache configured successfully"

# ===== PHASE 4: DEPLOY APPLICATION =====
echo "[$(date)] Phase 4: Deploying application"

# Remove default index.html
rm -f /var/www/html/index.html

# Create config.php
cat > /var/www/html/config.php <<'PHPEOF'
<?php
/**
 * Database Configuration
 */

function get_db_connection() {
    static $conn = null;
    
    if ($conn !== null && $conn->ping()) {
        return $conn;
    }
    
    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');
    $db_name = getenv('DB_NAME');
    
    if (empty($db_host) || empty($db_user) || empty($db_pass) || empty($db_name)) {
        error_log("Database configuration missing");
        throw new Exception("Database configuration error");
    }
    
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Database connection failed");
    }
    
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

function close_db_connection($conn) {
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}
?>
PHPEOF

# Create index.php
cat > /var/www/html/index.php <<'PHPEOF'
<?php
require_once 'config.php';

$hostname = gethostname();
$ip = $_SERVER['SERVER_ADDR'];
$az = @file_get_contents('http://169.254.169.254/latest/meta-data/placement/availability-zone');

try {
    $conn = get_db_connection();
    $db_connected = true;
} catch (Exception $e) {
    $db_connected = false;
    error_log("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Highly Available Web Application</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #667eea; margin-bottom: 10px; font-size: 2.5em; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 1.1em; }
        .info-box { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .info-item:last-child { border-bottom: none; }
        .info-value { 
            font-family: 'Courier New', monospace;
            background: rgba(0,0,0,0.2);
            padding: 5px 15px;
            border-radius: 5px;
        }
        .refresh-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #856404;
        }
        input, textarea { 
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }
        button { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
        }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 5px; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Highly Available Web Application</h1>
        <p class="subtitle">AWS Multi-AZ Architecture with Auto Scaling</p>
        
        <div class="info-box">
            <div class="info-item">
                <span>Instance Hostname:</span>
                <span class="info-value"><?php echo htmlspecialchars($hostname); ?></span>
            </div>
            <div class="info-item">
                <span>Private IP:</span>
                <span class="info-value"><?php echo htmlspecialchars($ip); ?></span>
            </div>
            <div class="info-item">
                <span>Availability Zone:</span>
                <span class="info-value"><?php echo htmlspecialchars($az ?: 'Unknown'); ?></span>
            </div>
            <div class="info-item">
                <span>Database Status:</span>
                <span class="info-value"><?php echo $db_connected ? '✅ Connected' : '❌ Disconnected'; ?></span>
            </div>
        </div>
        
        <div class="refresh-note">
            <strong>💡 Load Balancing Demo:</strong> Refresh this page to see different server hostnames!
        </div>
        
        <?php if (isset($_GET['success'])): ?>
        <div class="success">✅ Submission saved to database!</div>
        <?php endif; ?>
        
        <form action="submit.php" method="POST">
            <input type="text" name="name" placeholder="Your Name" required>
            <input type="email" name="email" placeholder="Your Email" required>
            <textarea name="message" placeholder="Message" rows="4"></textarea>
            <button type="submit">Submit</button>
        </form>
        
        <h2 style="margin-top: 30px;">Recent Submissions</h2>
        <?php include 'view.php'; ?>
    </div>
</body>
</html>
PHPEOF

# Create submit.php
cat > /var/www/html/submit.php <<'PHPEOF'
<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

try {
    $conn = get_db_connection();
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $hostname = gethostname();
    
    if (empty($name) || empty($email)) {
        header("Location: index.php?error=missing_fields");
        exit();
    }
    
    $sql = "INSERT INTO submissions (name, email, message, server_hostname) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $message, $hostname);
    
    if ($stmt->execute()) {
        header("Location: index.php?success=1");
    } else {
        header("Location: index.php?error=database");
    }
    
    $stmt->close();
    close_db_connection($conn);
} catch (Exception $e) {
    error_log("Submission error: " . $e->getMessage());
    header("Location: index.php?error=exception");
}
exit();
?>
PHPEOF

# Create view.php
cat > /var/www/html/view.php <<'PHPEOF'
<?php
require_once 'config.php';

try {
    $conn = get_db_connection();
    $result = $conn->query("SELECT * FROM submissions ORDER BY created_at DESC LIMIT 10");
    
    if ($result && $result->num_rows > 0) {
        echo '<table style="width:100%; margin-top:20px; border-collapse: collapse;">';
        echo '<tr style="background:#667eea; color:white;"><th>Name</th><th>Email</th><th>Message</th><th>Server</th><th>Time</th></tr>';
        while($row = $result->fetch_assoc()) {
            echo '<tr style="border-bottom:1px solid #ddd; padding:10px;">';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
            echo '<td>' . htmlspecialchars($row['message']) . '</td>';
            echo '<td>' . htmlspecialchars($row['server_hostname']) . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No submissions yet.</p>';
    }
    close_db_connection($conn);
} catch (Exception $e) {
    echo '<p style="color:red;">Error loading submissions</p>';
}
?>
PHPEOF

# Create health.php
cat > /var/www/html/health.php <<'PHPEOF'
<?php
header('Content-Type: application/json');

$health = ['status' => 'healthy', 'checks' => []];

$db_host = getenv('DB_HOST');
if (empty($db_host)) {
    $health['status'] = 'unhealthy';
    $health['checks']['environment'] = 'missing';
    http_response_code(503);
    echo json_encode($health);
    exit;
}

$conn = @mysqli_connect(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
if (!$conn) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = 'unreachable';
    http_response_code(503);
    echo json_encode($health);
    exit;
}

$health['checks']['database'] = 'ok';
$health['server'] = gethostname();
mysqli_close($conn);

http_response_code(200);
echo json_encode($health);
?>
PHPEOF

# Set permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "[$(date)] Application deployed successfully"

# ===== PHASE 5: START SERVICES =====
echo "[$(date)] Phase 5: Starting services"

systemctl restart apache2

echo "[$(date)] Services started"

# ===== PHASE 6: VALIDATION =====
echo "[$(date)] Phase 6: Validating deployment"

sleep 5

# Test health endpoint
HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/health.php)

if [[ "$HEALTH_STATUS" == "200" ]]; then
    echo "[$(date)] ✅ Health check PASSED (HTTP $HEALTH_STATUS)"
else
    echo "[$(date)] ❌ Health check FAILED (HTTP $HEALTH_STATUS)"
    curl -v http://localhost/health.php
    exit 1
fi

# Test main page
INDEX_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/index.php)

if [[ "$INDEX_STATUS" == "200" ]]; then
    echo "[$(date)] ✅ Index page PASSED (HTTP $INDEX_STATUS)"
else
    echo "[$(date)] ❌ Index page FAILED (HTTP $INDEX_STATUS)"
    exit 1
fi

echo "========================================="
echo "User Data Script Completed: $(date)"
echo "========================================="
