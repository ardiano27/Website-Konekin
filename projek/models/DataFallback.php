<?php
class DataFallback {
    public static function getUMKMStats() {
        return [
            'total_projects' => 12,
            'total_budget' => 25000000,
            'avg_duration' => 14,
            'avg_rating' => 4.5
        ];
    }
    
    public static function getCreativeStats() {
        return [
            'total_contracts' => 8,
            'total_income' => 18500000,
            'avg_rating' => 4.7
        ];
    }
    
    public static function getAdminStats() {
        return [
            'total_users' => 150,
            'creative_users' => 85,
            'umkm_users' => 65,
            'total_projects' => 89,
            'active_projects' => 23,
            'completed_projects' => 45,
            'total_disputes' => 5,
            'open_disputes' => 2,
            'total_transactions' => 125000000,
            'avg_rating' => 4.3
        ];
    }
    
    public static function getEmptyChartData() {
        return [['name' => 'No Data', 'y' => 0]];
    }
}
?>