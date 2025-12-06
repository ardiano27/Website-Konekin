<?php
// search-handler.php
include "check_login.php";
require_once 'config/Database.php';

$database = new DatabaseConnection();
$conn = $database->getConnection();

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];
$results = [];

if (!empty($query) && strlen($query) >= 2) {
    
    // 1. Pencarian Projects (untuk creative)
    if ($user_type === 'creative') {
        // Cari proyek terbuka
        $sql_projects = "
            SELECT 
                'project' as type,
                p.id,
                p.title as name,
                p.description,
                CONCAT('Lihat proyek: ', p.title) as display_text,
                CONCAT('view-project1.php?id=', p.id) as url,
                'fas fa-project-diagram' as icon,
                p.created_at
            FROM projects p
            WHERE p.status = 'open'
            AND (p.title LIKE :query1 OR p.description LIKE :query2)
            ORDER BY p.created_at DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($sql_projects);
        $search_term = "%$query%";
        $stmt->bindParam(':query1', $search_term);
        $stmt->bindParam(':query2', $search_term);
        $stmt->execute();
        $project_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $project_results);
    }
    
    // 2. Pencarian Users/Kreator (untuk UMKM)
    if ($user_type === 'umkm') {
        // Cari creative worker
        $sql_creatives = "
            SELECT 
                'creative' as type,
                u.id,
                u.full_name as name,
                cp.tagline as description,
                CONCAT('Profil kreator: ', u.full_name) as display_text,
                CONCAT('view-creative-profile.php?id=', u.id) as url,
                'fas fa-user-tie' as icon,
                u.created_at
            FROM users u
            LEFT JOIN creative_profiles cp ON u.id = cp.user_id
            WHERE u.user_type = 'creative' 
            AND u.is_active = 1
            AND (u.full_name LIKE :query1 OR cp.tagline LIKE :query2)
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($sql_creatives);
        $search_term = "%$query%";
        $stmt->bindParam(':query1', $search_term);
        $stmt->bindParam(':query2', $search_term);
        $stmt->execute();
        $creative_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $creative_results);
    }
    
    // 3. Pencarian Fitur/Menu berdasarkan tipe user
    $features = [];
    
    if ($user_type === 'creative') {
        $features = [
            ['type' => 'feature', 'name' => 'Buat Proposal', 'display_text' => 'Buat proposal untuk proyek', 'url' => 'create-proposal.php', 'icon' => 'fas fa-file-alt'],
            ['type' => 'feature', 'name' => 'Portfolio Saya', 'display_text' => 'Kelola portfolio karya Anda', 'url' => 'portfolio.php', 'icon' => 'fas fa-briefcase'],
            ['type' => 'feature', 'name' => 'Proposal Saya', 'display_text' => 'Lihat status proposal Anda', 'url' => 'my-proposals.php', 'icon' => 'fas fa-paper-plane'],
            ['type' => 'feature', 'name' => 'Proyek Disimpan', 'display_text' => 'Proyek yang Anda simpan', 'url' => 'saved-projects.php', 'icon' => 'fas fa-bookmark'],
            ['type' => 'feature', 'name' => 'Edit Profil', 'display_text' => 'Perbarui profil Anda', 'url' => 'creative-profile.php', 'icon' => 'fas fa-user-edit'],
            ['type' => 'feature', 'name' => 'Pesan', 'display_text' => 'Buka kotak pesan', 'url' => 'messages.php', 'icon' => 'fas fa-envelope'],
            ['type' => 'feature', 'name' => 'Cari Proyek', 'display_text' => 'Temukan proyek baru', 'url' => 'find-projects.php', 'icon' => 'fas fa-search']
        ];
    } else if ($user_type === 'umkm') {
        $features = [
            ['type' => 'feature', 'name' => 'Buat Proyek', 'display_text' => 'Buat proyek baru', 'url' => 'create-project.php', 'icon' => 'fas fa-plus-circle'],
            ['type' => 'feature', 'name' => 'Proyek Saya', 'display_text' => 'Kelola proyek Anda', 'url' => 'projects.php', 'icon' => 'fas fa-project-diagram'],
            ['type' => 'feature', 'name' => 'Temukan Kreator', 'display_text' => 'Cari creative worker', 'url' => 'find-creatives.php', 'icon' => 'fas fa-search'],
            ['type' => 'feature', 'name' => 'Edit Profil UMKM', 'display_text' => 'Perbarui profil bisnis', 'url' => 'umkm-profile.php', 'icon' => 'fas fa-store'],
            ['type' => 'feature', 'name' => 'Pesan', 'display_text' => 'Buka kotak pesan', 'url' => 'messages.php', 'icon' => 'fas fa-envelope'],
            ['type' => 'feature', 'name' => 'Kontrak', 'display_text' => 'Lihat kontrak aktif', 'url' => 'contracts.php', 'icon' => 'fas fa-file-contract']
        ];
    }
    
    // Filter features berdasarkan query
    foreach ($features as $feature) {
        if (stripos($feature['name'], $query) !== false || 
            stripos($feature['display_text'], $query) !== false) {
            $results[] = array_merge($feature, ['id' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        }
    }
    
    // 4. Pencarian history (simpan dalam session)
    if (!isset($_SESSION['search_history'])) {
        $_SESSION['search_history'] = [];
    }
    
    // Filter history berdasarkan query
    foreach ($_SESSION['search_history'] as $history) {
        if (stripos($history['query'], $query) !== false) {
            $results[] = [
                'type' => 'history',
                'id' => 0,
                'name' => $history['query'],
                'display_text' => 'Pencarian sebelumnya: ' . $history['query'],
                'url' => $history['url'],
                'icon' => 'fas fa-history',
                'created_at' => $history['timestamp']
            ];
        }
    }
    
    // Urutkan hasil: features dulu, lalu history, lalu konten
    usort($results, function($a, $b) {
        $order = ['feature' => 1, 'history' => 2, 'project' => 3, 'creative' => 4];
        return ($order[$a['type']] ?? 5) <=> ($order[$b['type']] ?? 5);
    });
    
    // Batasi maksimal 10 hasil
    $results = array_slice($results, 0, 10);
}

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'query' => $query,
    'results' => $results,
    'user_type' => $user_type
]);
?>