<?php
require_once 'config/Database.php';
class NotificationManager {
    private $db;
    
    public function __construct() {
        $database = new DatabaseConnection();
        $this->db = $database->getConnection();
    }

    public function createNotification($user_id, $title, $message, $notification_type = 'system', $related_entity_type = null, $related_entity_id = null) {
        try {
            $sql = "INSERT INTO notifications (user_id, title, message, notification_type, related_entity_type, related_entity_id) 
                    VALUES (:user_id, :title, :message, :notification_type, :related_entity_type, :related_entity_id)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':user_id' => $user_id,
                ':title' => $title,
                ':message' => $message,
                ':notification_type' => $notification_type,
                ':related_entity_type' => $related_entity_type,
                ':related_entity_id' => $related_entity_id
            ]);
            
            if ($result) {
                error_log("Notification created successfully for user: $user_id - $title");
                return $this->db->lastInsertId();
            } else {
                error_log("Failed to create notification for user: $user_id");
                return false;
            }
        } catch (PDOException $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for user
     */
    public function getUserNotifications($user_id, $limit = 10, $unread_only = false) {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
            
            if ($unread_only) {
                $sql .= " AND is_read = 0";
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            if ($limit > 0) {
                $sql .= " LIMIT :limit";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($limit > 0) {
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Retrieved " . count($notifications) . " notifications for user: $user_id");
            return $notifications;
        } catch (PDOException $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id' => $notification_id,
                ':user_id' => $user_id
            ]);
        } catch (PDOException $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($user_id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([':user_id' => $user_id]);
            
            error_log("Marked all as read for user: $user_id - Result: " . ($result ? 'success' : 'failed'));
            return $result;
        } catch (PDOException $e) {
            error_log("Mark all as read error: " . $e->getMessage());
            return false;
        }
    }
  
    public function getUnreadCount($user_id) {
        try {
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'] ?? 0;
            
            error_log("Unread count for user $user_id: $count");
            return $count;
        } catch (PDOException $e) {
            error_log("Unread count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create proposal submitted notification for UMKM
     */
    public function notifyProposalSubmitted($proposal_id, $creative_user_id, $project_id, $umkm_user_id) {
        $creative_name = $this->getUserName($creative_user_id);
        $project_title = $this->getProjectTitle($project_id);
        
        $title = "📩 Proposal Baru Diterima";
        $message = "{$creative_name} telah mengajukan proposal untuk proyek \"{$project_title}\"";
        
        error_log("Creating proposal notification: $creative_name -> $project_title for UMKM: $umkm_user_id");
        
        return $this->createNotification(
            $umkm_user_id,
            $title,
            $message,
            'proposal',
            'proposal',
            $proposal_id
        );
    }
    
    /**
     * Create proposal accepted notification for Creative Worker
     */
    public function notifyProposalAccepted($proposal_id, $creative_user_id, $umkm_user_id, $project_id) {
        $umkm_name = $this->getUserName($umkm_user_id);
        $project_title = $this->getProjectTitle($project_id);
        
        $title = "🎉 Proposal Diterima!";
        $message = "Proposal Anda untuk proyek \"{$project_title}\" telah diterima oleh {$umkm_name}";
        
        return $this->createNotification(
            $creative_user_id,
            $title,
            $message,
            'proposal',
            'proposal',
            $proposal_id
        );
    }
    
    /**
     * Create project published notification for Creative Workers
     */
    public function notifyNewProject($project_id, $umkm_user_id) {
        $umkm_name = $this->getUserName($umkm_user_id);
        $project_title = $this->getProjectTitle($project_id);
        
        $title = "🚀 Proyek Baru Tersedia";
        $message = "{$umkm_name} mempublikasikan proyek baru: \"{$project_title}\"";
        
        // Get creative workers with matching skills
        $creative_users = $this->getCreativeUsersByProjectSkills($project_id);
        
        $notification_count = 0;
        foreach ($creative_users as $user) {
            $this->createNotification(
                $user['id'],
                $title,
                $message,
                'project',
                'project',
                $project_id
            );
            $notification_count++;
        }
        
        error_log("Created $notification_count new project notifications");
        return $notification_count;
    }
    
    /**
     * Create message notification
     */
    public function notifyNewMessage($sender_id, $receiver_id, $project_id = null) {
        $sender_name = $this->getUserName($sender_id);
        
        $title = "💬 Pesan Baru";
        $message = "Anda memiliki pesan baru dari {$sender_name}";
        
        return $this->createNotification(
            $receiver_id,
            $title,
            $message,
            'message',
            'message',
            null
        );
    }
    
    // Helper methods
    private function getUserName($user_id) {
        try {
            $sql = "SELECT full_name FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['full_name'] ?? 'User';
        } catch (PDOException $e) {
            error_log("Get user name error: " . $e->getMessage());
            return 'User';
        }
    }
    
    private function getProjectTitle($project_id) {
        try {
            $sql = "SELECT title FROM projects WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $project_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['title'] ?? 'Proyek';
        } catch (PDOException $e) {
            error_log("Get project title error: " . $e->getMessage());
            return 'Proyek';
        }
    }
    
    private function getCreativeUsersByProjectSkills($project_id) {
        try {
            $sql = "SELECT DISTINCT u.id 
                    FROM users u 
                    INNER JOIN creative_profiles cp ON u.id = cp.user_id
                    LEFT JOIN creative_skills cs ON cp.id = cs.creative_profile_id
                    LEFT JOIN project_skills ps ON cs.skill_id = ps.skill_id
                    WHERE u.user_type = 'creative' 
                    AND u.is_active = 1
                    AND ps.project_id = :project_id
                    LIMIT 50";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':project_id' => $project_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get creative users error: " . $e->getMessage());
            return [];
        }
    }
}
?>