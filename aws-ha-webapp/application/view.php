<?php
/**
 * View Submissions
 * 
 * Displays recent submissions from the database
 * Shows which server instances processed each request
 */

require_once 'config.php';

try {
    $conn = get_db_connection();
    
    // Query for recent submissions
    $sql = "SELECT id, name, email, message, server_hostname, created_at 
            FROM submissions 
            ORDER BY created_at DESC 
            LIMIT 10";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        echo '<p style="color: red;">❌ Database query failed: ' . htmlspecialchars($conn->error) . '</p>';
    } elseif ($result->num_rows === 0) {
        echo '<p style="text-align: center; color: #666; padding: 40px;">
                📭 No submissions yet. Be the first to submit!
              </p>';
    } else {
        echo '<table>';
        echo '<thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Processed By</th>
                    <th>Timestamp</th>
                </tr>
              </thead>';
        echo '<tbody>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['id']) . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
            echo '<td>' . htmlspecialchars($row['message'] ?: '-') . '</td>';
            echo '<td><span class="info-value">' . htmlspecialchars($row['server_hostname']) . '</span></td>';
            echo '<td class="timestamp">' . htmlspecialchars($row['created_at']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Display summary
        $total = $result->num_rows;
        echo '<p style="text-align: center; color: #666; margin-top: 10px; font-size: 0.9em;">
                Showing ' . $total . ' most recent submission' . ($total !== 1 ? 's' : '') . '
              </p>';
    }
    
    close_db_connection($conn);
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error loading submissions: ' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log("View submissions error: " . $e->getMessage());
}
?>
