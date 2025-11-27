<?php
$folder = 'assets/uploads/';
if (is_writable($folder)) {
    echo "✅ YES! Folder bisa ditulis!<br>";
    
    // Coba buat file
    $test_file = $folder . 'test.txt';
    if (file_put_contents($test_file, 'Hello World')) {
        echo "✅ YES! Bisa buat file!<br>";
        unlink($test_file); // hapus file test
    }
} else {
    echo "❌ NO! Masih tidak bisa menulis";
}
?>