class ProjectsManager {
    constructor() {
        this.projects = [];
        this.filters = {
            status: 'all'
        };
        this.init();
    }

    init() {
        this.bindEvents();
        this.animateStats();
        this.setupAutoRefresh();
        this.initializeProjectCards();
    }

    bindEvents() {
        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleFilter(e));
        });

        // Project card interactions
        document.querySelectorAll('.project-card').forEach(card => {
            card.addEventListener('mouseenter', (e) => this.handleCardHover(e));
            card.addEventListener('mouseleave', (e) => this.handleCardLeave(e));
        });

        // Action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleAction(e));
        });

        // Search functionality
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearch(e));
        }
    }

    handleFilter(e) {
        const filter = e.currentTarget.dataset.filter;
        
        // Update active state
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        e.currentTarget.classList.add('active');

        // Animate filter change
        this.animateFilterChange(filter);
    }

    animateFilterChange(filter) {
        const projects = document.querySelectorAll('.project-item');
        
        projects.forEach((project, index) => {
            const status = project.dataset.status;
            
            if (filter === 'all' || status === filter) {
                // Show with animation
                setTimeout(() => {
                    project.style.display = 'block';
                    project.style.animation = 'fadeInUp 0.5s ease forwards';
                }, index * 100);
            } else {
                // Hide with animation
                project.style.animation = 'fadeOutDown 0.5s ease forwards';
                setTimeout(() => {
                    project.style.display = 'none';
                }, 500);
            }
        });
    }

    handleCardHover(e) {
        const card = e.currentTarget;
        const progressFill = card.querySelector('.progress-fill');
        
        if (progressFill) {
            const targetWidth = progressFill.style.width;
            progressFill.style.width = '0%';
            setTimeout(() => {
                progressFill.style.width = targetWidth;
            }, 50);
        }

        // Add hover effects
        card.style.transform = 'translateY(-10px) scale(1.02)';
    }

    handleCardLeave(e) {
        const card = e.currentTarget;
        card.style.transform = 'translateY(0) scale(1)';
    }

    handleAction(e) {
        e.preventDefault();
        const button = e.currentTarget;
        const action = button.dataset.action;
        const projectId = button.dataset.projectId;

        // Add click animation
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
            button.style.transform = 'scale(1)';
        }, 150);

        switch (action) {
            case 'view':
                this.viewProject(projectId);
                break;
            case 'proposals':
                this.viewProposals(projectId);
                break;
            case 'edit':
                this.editProject(projectId);
                break;
            case 'publish':
                this.publishProject(projectId);
                break;
            case 'progress':
                this.viewProgress(projectId);
                break;
            case 'chat':
                this.openChat(projectId);
                break;
            case 'review':
                this.giveReview(projectId);
                break;
        }
    }

    viewProject(projectId) {
        // Show loading state
        this.showLoading();
        
        // Simulate API call
        setTimeout(() => {
            window.location.href = `view-project.php?id=${projectId}`;
        }, 500);
    }

    viewProposals(projectId) {
        this.showNotification('Membuka halaman proposal...', 'info');
        setTimeout(() => {
            window.location.href = `project-proposals.php?id=${projectId}`;
        }, 300);
    }

    editProject(projectId) {
        this.showNotification('Membuka editor proyek...', 'info');
        setTimeout(() => {
            window.location.href = `edit-project.php?id=${projectId}`;
        }, 300);
    }

    publishProject(projectId) {
        if (confirm('Apakah Anda yakin ingin mempublish proyek ini?')) {
            this.showLoading();
            // Simulate API call
            setTimeout(() => {
                this.showNotification('Proyek berhasil dipublish!', 'success');
                this.refreshProjects();
            }, 1000);
        }
    }

    viewProgress(projectId) {
        this.showNotification('Membuka halaman progress...', 'info');
        setTimeout(() => {
            window.location.href = `project-progress.php?id=${projectId}`;
        }, 300);
    }

    openChat(projectId) {
        this.showNotification('Membuka chat...', 'info');
        setTimeout(() => {
            window.location.href = `project-messages.php?id=${projectId}`;
        }, 300);
    }

    giveReview(projectId) {
        this.showNotification('Membuka halaman review...', 'info');
        setTimeout(() => {
            window.location.href = `project-review.php?id=${projectId}`;
        }, 300);
    }

    animateStats() {
        const statNumbers = document.querySelectorAll('.stat-number');
        
        statNumbers.forEach(stat => {
            const target = parseInt(stat.textContent);
            let current = 0;
            const increment = target / 30;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                stat.textContent = Math.round(current);
            }, 50);
        });
    }

    initializeProjectCards() {
        const cards = document.querySelectorAll('.project-card');
        
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 150);
        });
    }

    setupAutoRefresh() {
        // Auto-check for new proposals every 30 seconds
        setInterval(() => {
            this.checkNewProposals();
        }, 30000);
    }

    checkNewProposals() {
        // Simulate API call to check for new proposals
        console.log('Checking for new proposals...');
        
        // In a real application, you would make an AJAX request here
        // and update the badge counts accordingly
    }

    showLoading() {
        // Create loading overlay
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="spinner"></div>
            <p>Memuat...</p>
        `;
        
        document.body.appendChild(overlay);
        
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 2000);
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    refreshProjects() {
        // Simulate refresh
        this.showLoading();
        
        setTimeout(() => {
            // In a real application, you would reload the data
            // and re-render the projects list
            location.reload();
        }, 1000);
    }
}

// Additional utility functions
class ProjectUtils {
    static formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    static formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }

    static truncateText(text, maxLength = 120) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    }

    static getCategoryIcon(category) {
        const icons = {
            'website': 'globe',
            'logo': 'palette',
            'social_media': 'hashtag',
            'video': 'video',
            'content': 'file-alt',
            'marketing': 'bullhorn',
            'other': 'ellipsis-h'
        };
        return icons[category] || 'folder';
    }

    static getStatusColor(status) {
        const colors = {
            'draft': '#6c757d',
            'open': '#28a745',
            'in_progress': '#ffc107',
            'completed': '#17a2b8',
            'cancelled': '#dc3545'
        };
        return colors[status] || '#6c757d';
    }
}

// CSS Animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeOutDown {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(20px);
        }
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        color: white;
    }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid rgba(255, 255, 255, 0.3);
        border-top: 5px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        transform: translateX(400px);
        transition: transform 0.3s ease;
        z-index: 10000;
        border-left: 4px solid var(--primary-color);
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success { border-left-color: var(--success-color); }
    .notification-error { border-left-color: var(--danger-color); }
    .notification-warning { border-left-color: var(--warning-color); }
    .notification-info { border-left-color: var(--primary-color); }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .notification i {
        font-size: 1.2rem;
    }
    
    .notification-success i { color: var(--success-color); }
    .notification-error i { color: var(--danger-color); }
    .notification-warning i { color: var(--warning-color); }
    .notification-info i { color: var(--primary-color); }
`;
document.head.appendChild(style);

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ProjectsManager();
});

// Export for global access
window.ProjectsManager = ProjectsManager;
window.ProjectUtils = ProjectUtils;