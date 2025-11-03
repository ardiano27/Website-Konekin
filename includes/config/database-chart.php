<?php
class DashboardCharts {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Query untuk Admin
    public function getAdminStats() {
        $query = "SELECT 
            (SELECT COUNT(*) FROM projects) as total_projects,
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid') as total_revenue,
            (SELECT COALESCE(AVG(rating), 0) FROM reviews) as avg_rating";
        
        return $this->db->query($query)->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getProjectStatusStats() {
        $query = "SELECT status, COUNT(*) as count FROM projects GROUP BY status";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserDistribution() {
        $query = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Query untuk UMKM
    public function getUMKMStats($user_id) {
        $query = "SELECT 
            (SELECT COUNT(*) FROM projects WHERE umkm_user_id = :user_id) as total_projects,
            (SELECT COUNT(*) FROM users WHERE user_type = 'creative') as total_creative,
            (SELECT COUNT(*) FROM contracts WHERE umkm_user_id = :user_id AND status = 'active') as active_contracts,
            (SELECT COALESCE(SUM(amount), 0) FROM payments p 
             JOIN contracts c ON p.contract_id = c.id 
             WHERE c.umkm_user_id = :user_id AND p.status = 'paid') as total_spent";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getUMKMProjectStatus($user_id) {
        $query = "SELECT status, COUNT(*) as count FROM projects 
                  WHERE umkm_user_id = :user_id GROUP BY status";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Query untuk Creative Worker
    public function getCWStats($user_id) {
        $query = "SELECT 
            (SELECT COUNT(*) FROM projects WHERE status = 'open') as available_projects,
            (SELECT COUNT(*) FROM portfolios WHERE creative_profile_id IN 
                (SELECT id FROM creative_profiles WHERE user_id = :user_id)) as total_portfolios,
            (SELECT COUNT(*) FROM contracts WHERE creative_user_id = :user_id) as total_contracts,
            (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE reviewed_id = :user_id) as avg_rating";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getCWProjectCategories() {
        $query = "SELECT category, COUNT(*) as count FROM projects 
                  WHERE status = 'open' GROUP BY category";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>