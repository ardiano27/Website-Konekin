document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initProfileInteractions();
    initTooltips();
    initAnimations();
    initImageLoaders();
});

function initProfileInteractions() {
    // Skill progress animation
    animateSkillProgress();
    
    // Portfolio hover effects
    initPortfolioHover();
    
    // Stats counter animation
    animateStatsCounters();
    
    // Avatar upload preview
    initAvatarUploadPreview();
    
    // Share functionality
    initShareButtons();
    
    // Availability toggle
    initAvailabilityToggle();
}

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize animations
 */
function initAnimations() {
    // Add scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe all profile sections
    document.querySelectorAll('.profile-section').forEach(section => {
        observer.observe(section);
    });

    // Animate stat cards on load
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('animate__animated', 'animate__fadeInUp');
        }, index * 100);
    });
}

/**
 * Animate skill progress bars
 */
function animateSkillProgress() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        
        setTimeout(() => {
            bar.style.width = width;
        }, 300);
    });
}

/**
 * Initialize portfolio hover effects
 */
function initPortfolioHover() {
    const portfolioCards = document.querySelectorAll('.portfolio-card');
    
    portfolioCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('portfolio-card-hover');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('portfolio-card-hover');
        });
    });
}

/**
 * Animate stats counters
 */
function animateStatsCounters() {
    const statValues = document.querySelectorAll('.stat-value');
    const animationDuration = 2000;
    const frameDuration = 1000 / 60;
    const totalFrames = Math.round(animationDuration / frameDuration);
    
    statValues.forEach(statValue => {
        const target = parseInt(statValue.textContent);
        let current = 0;
        const increment = target / totalFrames;
        
        const updateCounter = () => {
            current += increment;
            if (current < target) {
                statValue.textContent = Math.floor(current);
                setTimeout(updateCounter, frameDuration);
            } else {
                statValue.textContent = target;
            }
        };
        
        // Start animation when element is in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(statValue);
    });
}

/**
 * Initialize avatar upload preview
 */
function initAvatarUploadPreview() {
    const avatarInput = document.getElementById('avatarInput');
    const currentAvatar = document.getElementById('currentAvatar');
    
    if (avatarInput && currentAvatar) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    currentAvatar.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

/**
 * Initialize share buttons functionality
 */
function initShareButtons() {
    // Share via WhatsApp
    window.shareWhatsApp = function() {
        const text = encodeURIComponent("Lihat profil kreatif saya di Konekin!");
        const url = encodeURIComponent(window.location.href);
        window.open(`https://wa.me/?text=${text}%20${url}`, '_blank');
    };
    
    // Share via Email
    window.shareEmail = function() {
        const subject = encodeURIComponent("Profil Kreatif Saya");
        const body = encodeURIComponent(`Lihat profil kreatif saya di Konekin: ${window.location.href}`);
        window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
    };
    
    // Share via LinkedIn
    window.shareLinkedIn = function() {
        const url = encodeURIComponent(window.location.href);
        window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${url}`, '_blank');
    };
}

/**
 * Initialize availability toggle
 */
function initAvailabilityToggle() {
    const availabilityBadge = document.querySelector('.availability-badge');
    if (availabilityBadge) {
        availabilityBadge.addEventListener('click', function() {
            // In real implementation, this would make an AJAX call
            const isAvailable = this.classList.contains('bg-success');
            
            if (isAvailable) {
                this.classList.remove('bg-success');
                this.classList.add('bg-secondary');
                this.textContent = 'Tidak Tersedia';
                this.setAttribute('data-bs-title', 'Tidak menerima proyek baru');
            } else {
                this.classList.remove('bg-secondary');
                this.classList.add('bg-success');
                this.textContent = 'Tersedia';
                this.setAttribute('data-bs-title', 'Tersedia untuk proyek baru');
            }
            
            // Update tooltip
            const tooltip = bootstrap.Tooltip.getInstance(this);
            if (tooltip) {
                tooltip.dispose();
                new bootstrap.Tooltip(this);
            }
        });
    }
}

/**
 * Initialize image lazy loading
 */
function initImageLoaders() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

/**
 * Copy profile link to clipboard
 */
window.copyProfileLink = function() {
    const profileLink = document.getElementById('profileLink');
    if (profileLink) {
        profileLink.select();
        profileLink.setSelectionRange(0, 99999);
        
        navigator.clipboard.writeText(profileLink.value)
            .then(() => {
                showToast('Link profil berhasil disalin!', 'success');
            })
            .catch(err => {
                console.error('Failed to copy: ', err);
                showToast('Gagal menyalin link', 'error');
            });
    }
};

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

/**
 * Export profile data
 */
window.exportProfileData = function() {
    const profileData = {
        name: document.querySelector('.profile-name')?.textContent,
        tagline: document.querySelector('.profile-tagline')?.textContent,
        bio: document.querySelector('.profile-bio')?.textContent,
        skills: Array.from(document.querySelectorAll('.skill-name')).map(el => el.textContent),
        stats: {
            projects: document.querySelector('.stat-value:nth-child(1)')?.textContent,
            rating: document.querySelector('.stat-value:nth-child(2)')?.textContent,
            activeProjects: document.querySelector('.stat-value:nth-child(3)')?.textContent,
            proposals: document.querySelector('.stat-value:nth-child(4)')?.textContent
        }
    };
    
    const dataStr = JSON.stringify(profileData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    
    // Create download link
    const downloadLink = document.createElement('a');
    downloadLink.href = URL.createObjectURL(dataBlob);
    downloadLink.download = 'profile-data.json';
    downloadLink.click();
    
    showToast('Data profil berhasil diekspor!', 'success');
};

/**
 * Print profile
 */
window.printProfile = function() {
    // Store original styles
    const originalStyles = Array.from(document.styleSheets)
        .map(sheet => sheet.href)
        .filter(Boolean);
    
    // Create print styles
    const printStyles = `
        @media print {
            body * {
                visibility: hidden;
            }
            .creative-profile-wrapper,
            .creative-profile-wrapper * {
                visibility: visible;
            }
            .creative-profile-wrapper {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .btn, .section-edit-btn, .avatar-upload-btn {
                display: none !important;
            }
            .profile-header-wrapper {
                background: #3E7FD5 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    `;
    
    const styleSheet = document.createElement('style');
    styleSheet.textContent = printStyles;
    document.head.appendChild(styleSheet);
    
    window.print();
    
    // Clean up
    styleSheet.remove();
};

/**
 * Download profile as PDF
 */
window.downloadProfilePDF = function() {
    // This would typically use a library like jsPDF or html2pdf
    // For now, we'll show a message
    showToast('Fitur download PDF sedang dalam pengembangan', 'info');
};

/**
 * Initialize responsive behavior
 */
window.addEventListener('resize', function() {
    // Handle responsive adjustments
    if (window.innerWidth < 768) {
        document.body.classList.add('mobile-view');
    } else {
        document.body.classList.remove('mobile-view');
    }
});

/**
 * Initialize theme toggle
 */
window.initThemeToggle = function() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                showToast('Mode gelap diaktifkan', 'info');
            } else {
                localStorage.setItem('theme', 'light');
                showToast('Mode terang diaktifkan', 'info');
            }
        });
        
        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
    }
};

/**
 * Add dark mode styles
 */
function addDarkModeStyles() {
    const darkModeStyles = `
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        
        body.dark-mode .profile-section,
        body.dark-mode .stat-card {
            background-color: #1e1e1e;
            border-color: #333;
        }
        
        body.dark-mode .contact-item,
        body.dark-mode .info-card,
        body.dark-mode .quick-action-card {
            background-color: #2d2d2d;
        }
        
        body.dark-mode .text-muted {
            color: #aaa !important;
        }
        
        body.dark-mode .section-title {
            color: #fff;
        }
    `;
    
    const style = document.createElement('style');
    style.textContent = darkModeStyles;
    document.head.appendChild(style);
}

// Add dark mode styles if needed
addDarkModeStyles();