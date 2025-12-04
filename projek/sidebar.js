class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.sidebarClose = document.getElementById('sidebarClose');
        this.sidebarOverlay = document.getElementById('sidebarOverlay');
        this.mainContent = document.querySelector('.main-content');
        this.toggleIcon = document.getElementById('toggleIcon');
        
        this.isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        this.init();
    }
    
    init() {
        this.applySidebarState();
        
        this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        if (this.sidebarClose) {
            this.sidebarClose.addEventListener('click', () => this.closeSidebar());
        }
        if (this.sidebarOverlay) {
            this.sidebarOverlay.addEventListener('click', () => this.closeSidebar());
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.handleEscapeKey();
            }
        });
        
        window.addEventListener('resize', () => this.handleResize());
        

        this.highlightCurrentPage();
        

        this.addTooltipAttributes();
    }
    
    applySidebarState() {
        if (this.isCollapsed) {
            this.collapseSidebar();
        } else {
            this.expandSidebar();
        }
    }
    
    toggleSidebar() {
        if (window.innerWidth <= 768) {

            this.toggleMobileSidebar();
        } else {

            this.toggleDesktopSidebar();
        }
    }
    
    toggleMobileSidebar() {
        this.sidebar.classList.toggle('active');
        this.sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = this.sidebar.classList.contains('active') ? 'hidden' : '';
    }
    
    toggleDesktopSidebar() {
        this.isCollapsed = !this.isCollapsed;
        localStorage.setItem('sidebarCollapsed', this.isCollapsed);
        
        if (this.isCollapsed) {
            this.collapseSidebar();
        } else {
            this.expandSidebar();
        }
    }
    
    collapseSidebar() {
        this.sidebar.classList.add('collapsed');
        this.mainContent.classList.add('sidebar-collapsed');
        this.toggleIcon.className = 'fas fa-bars';
    }
    
    expandSidebar() {
        this.sidebar.classList.remove('collapsed');
        this.mainContent.classList.remove('sidebar-collapsed');
        this.toggleIcon.className = 'fas fa-times';
    }
    
    openSidebar() {
        if (window.innerWidth <= 768) {
            this.sidebar.classList.add('active');
            this.sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    closeSidebar() {
        if (window.innerWidth <= 768) {
            this.sidebar.classList.remove('active');
            this.sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    handleEscapeKey() {
        if (window.innerWidth <= 768) {
            this.closeSidebar();
        }
    }
    
    handleResize() {
        if (window.innerWidth > 768) {

            this.sidebar.classList.remove('active');
            this.sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
            
            this.applySidebarState();
        } else {
  
            this.sidebar.classList.remove('collapsed');
            this.mainContent.classList.remove('sidebar-collapsed');
        }
    }
    
    highlightCurrentPage() {
        const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }
    
    addTooltipAttributes() {
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(link => {
            const textElement = link.querySelector('.nav-text');
            if (textElement) {
                link.setAttribute('data-tooltip', textElement.textContent.trim());
            }
        });
    }
    
 
    updateUserInfo(userData) {
        const userNameElement = this.sidebar.querySelector('.user-name');
        const userTypeElement = this.sidebar.querySelector('.user-type');
        
        if (userNameElement && userData.full_name) {
            userNameElement.textContent = userData.full_name;
        }
        
        if (userTypeElement && userData.user_type) {
            userTypeElement.textContent = userData.user_type === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker';
        }
    }
}


document.addEventListener('DOMContentLoaded', function() {
    const sidebarManager = new SidebarManager();
    

    window.sidebarManager = sidebarManager;
});


document.addEventListener('DOMContentLoaded', function() {
    const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
    
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Jika di mobile, tutup sidebar setelah klik link
            if (window.innerWidth <= 768) {
                const sidebarManager = window.sidebarManager;
                if (sidebarManager) {
                    sidebarManager.closeSidebar();
                }
            }
            
            // Tambahan: bisa ditambahkan smooth scroll ke section tertentu
            const href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    document.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const swipeDistance = touchEndX - touchStartX;
        
        if (Math.abs(swipeDistance) > swipeThreshold) {
            if (swipeDistance > 0 && touchStartX <= 50) {
                // Swipe kanan - buka sidebar
                const sidebarManager = window.sidebarManager;
                if (sidebarManager && window.innerWidth <= 768) {
                    sidebarManager.openSidebar();
                }
            } else if (swipeDistance < 0) {
                // Swipe kiri - tutup sidebar
                const sidebarManager = window.sidebarManager;
                if (sidebarManager && window.innerWidth <= 768) {
                    sidebarManager.closeSidebar();
                }
            }
        }
    }
});