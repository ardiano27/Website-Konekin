<?php
session_start();
require_once 'Database.php';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$results = [];
$recommendations = [];

if (!empty($query)) {
    // Cari creative worker berdasarkan keahlian atau nama
    $search_sql = "
        SELECT 
            u.id, u.full_name, u.avatar_url,
            cp.tagline, cp.bio, cp.experience_level, cp.rating, cp.completed_projects,
            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as skills
        FROM users u
        LEFT JOIN creative_profiles cp ON u.id = cp.user_id
        LEFT JOIN creative_skills cs ON cp.id = cs.creative_profile_id
        LEFT JOIN skills s ON cs.skill_id = s.id
        WHERE u.user_type = 'creative' AND u.is_active = 1
        AND (s.name LIKE ? OR u.full_name LIKE ? OR cp.tagline LIKE ?)
        GROUP BY u.id
        ORDER BY cp.rating DESC, cp.completed_projects DESC
        LIMIT 20
    ";
    
    $search_term = "%$query%";
    $stmt = $conn->prepare($search_sql);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    
    $stmt->close();
}

// Ambil rekomendasi creative worker (top rated)
$rec_sql = "
    SELECT 
        u.id, u.full_name, u.avatar_url,
        cp.tagline, cp.experience_level, cp.rating, cp.completed_projects,
        GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as skills
    FROM users u
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    LEFT JOIN creative_skills cs ON cp.id = cs.creative_profile_id
    LEFT JOIN skills s ON cs.skill_id = s.id
    WHERE u.user_type = 'creative' AND u.is_active = 1
    GROUP BY u.id
    ORDER BY cp.rating DESC, cp.completed_projects DESC
    LIMIT 6
";

$rec_result = $conn->query($rec_sql);
while ($row = $rec_result->fetch_assoc()) {
    $recommendations[] = $row;
}
?>