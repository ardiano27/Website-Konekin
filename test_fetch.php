<?php
// Test file untuk debug fetch_message.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h2>Debug Info</h2>";
echo "<pre>";

echo "=== SESSION CHECK ===\n";
echo "Session ID: " . session_id() . "\n";
echo "User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Session data: " . print_r($_SESSION, true) . "\n\n";

echo "=== DATABASE CONNECTION ===\n";
try {
    require_once __DIR__ . '/config/Database.php';
    $database = new DatabaseConnection();
    $conn = $database->getConnection();
    echo "✓ Database connected successfully\n";
    echo "Connection object: " . get_class($conn) . "\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
    exit;
}

echo "=== MESSAGES TABLE CHECK ===\n";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table 'messages' exists\n";
        
        // Show columns
        $cols = $conn->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($cols as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "✗ Table 'messages' does not exist\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error checking table: " . $e->getMessage() . "\n\n";
}

echo "=== MESSAGE COUNT ===\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM messages");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total messages in database: " . $result['total'] . "\n\n";
} catch (Exception $e) {
    echo "✗ Error counting messages: " . $e->getMessage() . "\n\n";
}

echo "=== RECENT MESSAGES FOR CURRENT USER ===\n";
if (isset($_SESSION['user_id'])) {
    $current_user = (int) $_SESSION['user_id'];
    
    try {
        $sql = "SELECT m.id, m.sender_id, m.receiver_id, 
                       LEFT(m.message_text, 30) as preview, m.created_at
                FROM messages m
                WHERE m.sender_id = ? OR m.receiver_id = ?
                ORDER BY m.created_at DESC
                LIMIT 5";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$current_user, $current_user]);
        $user_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Recent messages for user $current_user:\n";
        if (count($user_messages) > 0) {
            foreach ($user_messages as $msg) {
                $direction = ($msg['sender_id'] == $current_user) ? 'OUTGOING' : 'INCOMING';
                echo "ID: {$msg['id']} | $direction | From: {$msg['sender_id']} | To: {$msg['receiver_id']} | ";
                echo "Preview: {$msg['preview']}... | Time: {$msg['created_at']}\n";
            }
        } else {
            echo "No messages found for user $current_user\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "Error checking user messages: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "No user logged in\n\n";
}

if (isset($_SESSION['user_id'])) {
    echo "=== SIMULATE FETCH ===\n";
    $current_user = (int) $_SESSION['user_id'];
    
    try {
        $find_sql = "SELECT id, email FROM users WHERE id <> ? LIMIT 3";
        $find_stmt = $conn->prepare($find_sql);
        $find_stmt->execute([$current_user]);
        $other_users = $find_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($other_users) {
            foreach ($other_users as $other) {
                $other_user = (int) $other['id'];
                $other_email = $other['email'];
                echo "Testing fetch between user $current_user and user $other_user ($other_email)\n";

                $sql = "SELECT id, sender_id, receiver_id, message_text, created_at
                        FROM messages
                        WHERE (sender_id = ? AND receiver_id = ?)
                           OR (sender_id = ? AND receiver_id = ?)
                        ORDER BY created_at ASC
                        LIMIT 10";
                
                $msg_stmt = $conn->prepare($sql);
                $msg_stmt->execute([$current_user, $other_user, $other_user, $current_user]);
                $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "Found " . count($messages) . " messages\n";
                if (count($messages) > 0) {
                    echo "First message:\n";
                    print_r($messages[0]);
                }
                echo "---\n";
            }
        } else {
            echo "No other users found in database\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Error in simulation: " . $e->getMessage() . "\n";
    }
} else {
    echo "=== CANNOT SIMULATE ===\n";
    echo "No user logged in (session user_id not set)\n";
}

echo "\n=== PHP INFO ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";

echo "</pre>";

echo "<hr><h2>Test Fetch Message API</h2>";
if (isset($_SESSION['user_id'])) {
    $current_user = $_SESSION['user_id'];
    
    // Get list of other users
    try {
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE id <> ?");
        $stmt->execute([$current_user]);
        $other_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($other_users) {
            echo "<h3>Select User to Chat With:</h3>";
            foreach ($other_users as $user) {
                echo "<button onclick=\"testFetch({$user['id']})\" style='margin:5px; padding:10px;'>Test with User ID: {$user['id']} ({$user['email']})</button><br>";
            }
            
            echo '<div style="margin-top:20px;">';
            echo '<button onclick="clearRateLimit()" style="background:#ff6b6b; color:white; padding:5px 10px;">Clear Rate Limit</button>';
            echo '</div>';
        } else {
            echo "<p>No other users found in database</p>";
        }
    } catch (Exception $e) {
        echo "<p>Error fetching users: " . $e->getMessage() . "</p>";
    }
    
    echo '<div id="fetchResult" style="margin-top:20px; padding:15px; background:#f8f9fa; white-space:pre-wrap; border:1px solid #dee2e6; border-radius:5px; font-family:monospace;"></div>';
    
    echo "<script>
    function testFetch(otherUserId) {
        const result = document.getElementById('fetchResult');
        result.textContent = 'Testing fetch with user ID: ' + otherUserId + '...\\nLoading...';
        result.style.background = '#fff3cd';
        
        fetch('fetch_message.php?other_user=' + otherUserId)
            .then(res => {
                const statusText = 'Status: ' + res.status + ' (' + res.statusText + ')\\n';
                return res.json().then(data => {
                    result.textContent = statusText + 'Response:\\n' + JSON.stringify(data, null, 2);
                    result.style.background = res.status === 200 ? '#d1e7dd' : '#f8d7da';
                });
            })
            .catch(err => {
                result.textContent = 'Fetch Error: ' + err.message;
                result.style.background = '#f8d7da';
            });
    }
    
    function clearRateLimit() {
        fetch('clear_rate_limit.php')
            .then(res => res.text())
            .then(text => {
                alert('Rate limit cleared: ' + text);
            })
            .catch(err => {
                alert('Error clearing rate limit: ' + err.message);
            });
    }
    </script>";
} else {
    echo "<p style='color:red'>Cannot test API - please login first</p>";
}
?>