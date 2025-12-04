[file name]: setup_uploads.php
[file content begin]
<?php
// Script untuk setup folder uploads
echo "Setting up uploads folder...\n";

$uploads_dir = __DIR__ . '/uploads';

// Create uploads directory
if (!is_dir($uploads_dir)) {
    if (mkdir($uploads_dir, 0755, true)) {
        echo "✅ Created uploads directory: $uploads_dir\n";
    } else {
        echo "❌ Failed to create uploads directory\n";
        exit;
    }
} else {
    echo "✅ Uploads directory already exists\n";
}

// Create .htaccess for security
$htaccess_content = <<<HTACCESS
# Prevent direct access to uploads folder
RewriteEngine On
RewriteRule ^uploads/.*\.(php|php5|php7|phtml)$ - [F,L,NC]
<FilesMatch "\.(php|php5|php7|phtml)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
HTACCESS;

$htaccess_file = $uploads_dir . '/.htaccess';
if (file_put_contents($htaccess_file, $htaccess_content)) {
    echo "✅ Created .htaccess file for security\n";
} else {
    echo "❌ Failed to create .htaccess file\n";
}

// Create test file
$test_file = $uploads_dir . '/test.txt';
if (file_put_contents($test_file, 'Test file for uploads directory')) {
    echo "✅ Created test file\n";
} else {
    echo "❌ Failed to create test file\n";
}

// Check permissions
if (is_writable($uploads_dir)) {
    echo "✅ Uploads directory is writable\n";
} else {
    echo "⚠️  Uploads directory is NOT writable. Run: chmod 755 $uploads_dir\n";
}

echo "\nSetup complete!\n";
echo "1. Uploads folder: $uploads_dir\n";
echo "2. Files will be accessible at: http://yourdomain.com/uploads/filename.ext\n";
echo "3. Download handler: http://yourdomain.com/download_file.php\n";
?>
[file content end]