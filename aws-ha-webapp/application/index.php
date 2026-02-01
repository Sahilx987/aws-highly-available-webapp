<?php
/**
 * Main Application Page
 * 
 * Displays server information and form for database submissions
 * Demonstrates load balancing by showing which instance served the request
 */

require_once 'config.php';

// Get instance metadata
$hostname = gethostname();
$ip = $_SERVER['SERVER_ADDR'];
$az = @file_get_contents('http://169.254.169.254/latest/meta-data/placement/availability-zone');

// Get database connection
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
    <title>Highly Available Web Application - AWS</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
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
        
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        
        .info-box { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .info-box h3 {
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .info-item:last-child { 
            border-bottom: none; 
        }
        
        .info-label { 
            font-weight: 600; 
            opacity: 0.9; 
        }
        
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
        
        .form-section {
            margin: 30px 0;
        }
        
        .form-section h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        input, textarea { 
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #155724;
        }
        
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #721c24;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:last-child td { 
            border-bottom: none; 
        }
        
        tr:hover { 
            background: #f8f9fa; 
        }
        
        .timestamp { 
            color: #666;
            font-size: 0.9em;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Highly Available Web Application</h1>
        <p class="subtitle">AWS Multi-AZ Architecture with Auto Scaling</p>
        
        <div class="info-box">
            <h3>📊 Server Information</h3>
            <div class="info-item">
                <span class="info-label">Instance Hostname:</span>
                <span class="info-value"><?php echo htmlspecialchars($hostname); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Private IP Address:</span>
                <span class="info-value"><?php echo htmlspecialchars($ip); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Availability Zone:</span>
                <span class="info-value"><?php echo htmlspecialchars($az ?: 'Unknown'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Current Time:</span>
                <span class="info-value"><?php echo date('Y-m-d H:i:s T'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Database Status:</span>
                <span class="info-value">
                    <?php if ($db_connected): ?>
                        <span class="badge badge-success">✅ Connected</span>
                    <?php else: ?>
                        <span class="badge badge-danger">❌ Disconnected</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="refresh-note">
            <strong>💡 Load Balancing Demo:</strong> Refresh this page multiple times to see different server hostnames - this proves the Application Load Balancer is distributing traffic across multiple EC2 instances in different Availability Zones!
        </div>
        
        <?php if (isset($_GET['success'])): ?>
        <div class="success">
            ✅ <strong>Success!</strong> Your submission has been saved to the RDS MySQL database and will appear in the list below.
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="error">
            ❌ <strong>Error:</strong> Failed to save your submission. Please try again or contact support.
        </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2>📝 Submit Your Information</h2>
            <p style="color: #666; margin-bottom: 20px;">
                This form demonstrates database connectivity. Your submission will be stored in 
                Amazon RDS MySQL and the server hostname will show which instance processed your request.
            </p>
            <form action="submit.php" method="POST">
                <input type="text" name="name" placeholder="Your Name" required maxlength="100">
                <input type="email" name="email" placeholder="Your Email" required maxlength="100">
                <textarea name="message" placeholder="Your Message (optional)" rows="4" maxlength="500"></textarea>
                <button type="submit">Submit to Database</button>
            </form>
        </div>
        
        <div class="form-section">
            <h2>📋 Recent Submissions</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Last 10 submissions from the database. Notice the "Processed By" column showing 
                different server hostnames - this demonstrates load balancing across multiple instances.
            </p>
            <?php include 'view.php'; ?>
        </div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666;">
            <p>
                <strong>Architecture:</strong> VPC • ALB • Auto Scaling • EC2 • RDS Multi-AZ • NAT Gateway
            </p>
            <p style="margin-top: 10px; font-size: 0.9em;">
                Built with AWS best practices for high availability, security, and scalability
            </p>
        </div>
    </div>
</body>
</html>
