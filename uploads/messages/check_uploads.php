<?php
// check_uploads.php
echo "<h3>Checking Uploads Directory Setup</h3>";

$base_dir = __DIR__;
$uploads_dir = $base_dir . '/uploads';
$messages_dir = $uploads_dir . '/messages';

echo "Base Directory: " . htmlspecialchars($base_dir) . "<br>";
echo "Uploads Directory: " . htmlspecialchars($uploads_dir) . "<br>";
echo "Messages Directory: " . htmlspecialchars($messages_dir) . "<br><br>";

// Check directories
echo "1. Checking directories:<br>";
echo "- uploads/ exists: " . (is_dir($uploads_dir) ? '✅ Yes' : '❌ No') . "<br>";
echo "- uploads/messages exists: " . (is_dir($messages_dir) ? '✅ Yes' : '❌ No') . "<br><br>";

// Check permissions
echo "2. Checking permissions:<br>";
echo "- uploads/ is writable: " . (is_writable($uploads_dir) ? '✅ Yes' : '❌ No') . "<br>";
echo "- uploads/messages is writable: " . (is_writable($messages_dir) ? '✅ Yes' : '❌ No') . "<br><br>";

// Test file creation
echo "3. Testing file creation:<br>";
$test_file = $messages_dir . '/test_' . time() . '.txt';
if (file_put_contents($test_file, 'Test content')) {
    echo "- Can create file: ✅ Yes<br>";
    echo "- File size: " . filesize($test_file) . " bytes<br>";
    unlink($test_file);
    echo "- Test file deleted: ✅ Yes<br>";
} else {
    echo "- Can create file: ❌ No<br>";
}

// Check .htaccess
echo "<br>4. Checking .htaccess:<br>";
$htaccess_file = $messages_dir . '/.htaccess';
if (file_exists($htaccess_file)) {
    echo "- .htaccess exists: ✅ Yes<br>";
    echo "- Content:<br><pre>" . htmlspecialchars(file_get_contents($htaccess_file)) . "</pre>";
} else {
    echo "- .htaccess exists: ❌ No<br>";
}

echo "<br><hr>";
echo "<h4>Manual Setup Commands:</h4>";
echo "<pre>";
echo "cd /path/to/KONEKIN\n";
echo "mkdir -p uploads/messages\n";
echo "chmod 755 uploads uploads/messages\n";
echo "# For .htaccess:\n";
echo "echo 'Deny from all\n&lt;Files ~ \"\\.(jpg|jpeg|png|gif|pdf|txt|doc|docx)$\"&gt;\n    Allow from all\n&lt;/Files&gt;' > uploads/messages/.htaccess";
echo "</pre>";

// Delete this file after checking for security
echo "<br><br><strong>Note:</strong> Delete this file after checking.";
?>