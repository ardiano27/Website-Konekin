<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test - Detailed Debug</h2>";

try {
    require_once __DIR__ . '/config/Database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "✓ Database connected successfully<br>";
    
    $current_user = 2;
    $other_user = 7;
    
    // Tampilkan query yang akan dijalankan
    $sql = "SELECT id, sender_id, receiver_id, message_text, created_at
            FROM messages
            WHERE (sender_id = :current_user AND receiver_id = :other_user)
               OR (sender_id = :other_user AND receiver_id = :current_user)
            ORDER BY created_at ASC LIMIT 100";
    
    echo "SQL Query: <pre>" . htmlspecialchars($sql) . "</pre><br>";
    echo "Parameters: current_user = $current_user, other_user = $other_user<br>";
    
    $stmt = $conn->prepare($sql);
    
    // Debug binding parameters
    echo "Binding parameters...<br>";
    $stmt->bindValue(':current_user', $current_user, PDO::PARAM_INT);
    $stmt->bindValue(':other_user', $other_user, PDO::PARAM_INT);
    
    echo "Executing query...<br>";
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Query executed successfully: " . count($messages) . " messages found<br>";
    
    if (count($messages) > 0) {
        echo "Sample messages:<br>";
        foreach (array_slice($messages, 0, 3) as $msg) {
            echo "ID: {$msg['id']} | From: {$msg['sender_id']} | To: {$msg['receiver_id']} | ";
            echo "Text: " . htmlspecialchars(substr($msg['message_text'], 0, 50)) . "...<br>";
        }
    } else {
        echo "No messages found between user $current_user and user $other_user<br>";
    }
    
} catch (PDOException $e) {
    echo "✗ PDO Error: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
    
    // Debug tambahan
    echo "SQL State: " . $e->errorInfo[0] . "<br>";
    echo "Driver Code: " . $e->errorInfo[1] . "<br>";
    echo "Driver Message: " . $e->errorInfo[2] . "<br>";
} catch (Exception $e) {
    echo "✗ General Error: " . $e->getMessage() . "<br>";
}
?>