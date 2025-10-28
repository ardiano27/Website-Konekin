<?php
class Notification {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function create($userId, $title, $message, $type, $entityType = null, $entityId = null) {
        $sql = "INSERT INTO notifications 
                (user_id, title, message, notification_type, related_entity_type, related_entity_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        return $this->db->execute($sql, [
            $userId, $title, $message, $type, $entityType, $entityId
        ]);
    }
    
    public function getUserNotifications($userId, $limit = 10, $page = 1) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT n.*, 
                       DATE_FORMAT(n.created_at, '%e %b %Y %H:%i') as time_ago,
                       CASE 
                           WHEN TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) < 60 THEN 'just now'
                           WHEN TIMESTAMPDIFF(HOUR, n.created_at, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR, n.created_at, NOW()), 'h ago')
                           ELSE DATE_FORMAT(n.created_at, '%e %b')
                       END as display_time
                FROM notifications n
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT $offset, $limit";
        
        return $this->db->query($sql, [$userId]);
    }
    
    public function markAsRead($notificationId, $userId) {
        $sql = "UPDATE notifications SET is_read = 1 
                WHERE id = ? AND user_id = ?";
        
        return $this->db->execute($sql, [$notificationId, $userId]);
    }
    
    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE user_id = ? AND is_read = 0";
        
        $result = $this->db->query($sql, [$userId]);
        return $result[0]['count'];
    }
}
?>