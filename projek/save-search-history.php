<?php
// save-search-history.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_SESSION['search_history'])) {
        $_SESSION['search_history'] = [];
    }
    
    $new_entry = [
        'query' => $data['query'] ?? '',
        'url' => $data['url'] ?? '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Hapus duplikat
    $_SESSION['search_history'] = array_filter($_SESSION['search_history'], 
        function($item) use ($new_entry) {
            return $item['query'] !== $new_entry['query'];
        }
    );
    
    // Tambahkan di awal
    array_unshift($_SESSION['search_history'], $new_entry);
    
    // Batasi hanya 10 item
    $_SESSION['search_history'] = array_slice($_SESSION['search_history'], 0, 10);
    
    echo json_encode(['success' => true]);
}
?>