/**
 * Notification System for Konekin
 */

class NotificationSystem {
    constructor() {
        this.pollingInterval = 30000; // 30 seconds
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.startPolling();
    }
    
    bindEvents() {
        // Mark notification as read when clicked
        document.addEventListener('click', (e) => {
            const notificationItem = e.target.closest('.notification-item');
            if (notificationItem) {
                this.markAsRead(notificationItem);
            }
            
            // Mark all as read button
            if (e.target.closest('.mark-all-read')) {
                e.preventDefault();
                this.markAllAsRead();
            }
        });
        
        // Real-time notification sound (optional)
        this.initNotificationSound();
    }
    
    async markAsRead(notificationElement) {
        const notificationId = notificationElement.dataset.notificationId;
        
        try {
            const response = await fetch('notifikasi-baca.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove unread styling
                notificationElement.classList.remove('unread');
                
                // Update badge count
                this.updateBadgeCount(-1);
                
                // Navigate to related page if available
                const url = notificationElement.dataset.url;
                if (url && url !== '#') {
                    setTimeout(() => {
                        window.location.href = url;
                    }, 300);
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        try {
            const response = await fetch('notifikasi-bacasemua.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove unread class from all notifications
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                
                // Remove badge
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.remove();
                }
                
                // Hide mark all button
                const markAllBtn = document.querySelector('.mark-all-read');
                if (markAllBtn) {
                    markAllBtn.remove();
                }
                
                // Show success message
                this.showToast('Semua notifikasi telah ditandai dibaca', 'success');
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
            this.showToast('Gagal menandai notifikasi', 'error');
        }
    }
    
    updateBadgeCount(change) {
        const badge = document.querySelector('.notification-badge');
        if (!badge) return;
        
        const currentCount = parseInt(badge.textContent) || 0;
        const newCount = Math.max(0, currentCount + change);
        
        if (newCount > 0) {
            badge.textContent = newCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    async fetchNewNotifications() {
        try {
            const response = await fetch('notifikasi-check.php?timestamp=' + Date.now());
            const data = await response.json();
            
            if (data.has_new) {
                this.playNotificationSound();
                this.showToast('Notifikasi baru tersedia', 'info');
                this.refreshNotificationList();
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }
    
    refreshNotificationList() {
        // Reload the notification dropdown or use AJAX to update
        const dropdown = document.querySelector('.notification-dropdown');
        if (dropdown && dropdown.classList.contains('show')) {
            // If dropdown is open, refresh content
            this.loadNotificationDropdown();
        }
    }
    
    async loadNotificationDropdown() {
        try {
            const response = await fetch('notifikasi-dropdown.php');
            const html = await response.text();
            
            const dropdown = document.querySelector('.notification-dropdown');
            if (dropdown) {
                dropdown.innerHTML = html;
            }
        } catch (error) {
            console.error('Error loading notification dropdown:', error);
        }
    }
    
    initNotificationSound() {
        // Create notification sound (optional)
        this.notificationSound = new Audio('assets/sounds/notification.mp3');
    }
    
    playNotificationSound() {
        if (this.notificationSound && Notification.permission === 'granted') {
            this.notificationSound.play().catch(console.error);
        }
    }
    
    showToast(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `notification-toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${this.getToastIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close">&times;</button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    getToastIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            info: 'info-circle',
            warning: 'exclamation-triangle'
        };
        return icons[type] || 'bell';
    }
    
    startPolling() {
        // Check for new notifications periodically
        setInterval(() => {
            this.fetchNewNotifications();
        }, this.pollingInterval);
    }
    
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.notificationSystem = new NotificationSystem();
});