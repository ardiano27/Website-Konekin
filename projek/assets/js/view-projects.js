document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality
    initProposalForm();
    initAnimations();
    initInteractions();
    initModalHandling();
});

function initProposalForm() {
    const coverLetter = document.getElementById('cover_letter');
    const charCount = document.getElementById('charCount');
    
    if (coverLetter && charCount) {
        // Character counter
        function updateCharCount() {
            const count = coverLetter.value.length;
            charCount.textContent = count;
            
            if (count < 100) {
                charCount.style.color = '#e63946';
            } else if (count < 1000) {
                charCount.style.color = '#f8961e';
            } else {
                charCount.style.color = '#4cc9f0';
            }
        }
        
        coverLetter.addEventListener('input', updateCharCount);
        updateCharCount();
        
        // Auto-resize textarea
        function autoResize() {
            coverLetter.style.height = 'auto';
            coverLetter.style.height = (coverLetter.scrollHeight) + 'px';
        }
        
        coverLetter.addEventListener('input', autoResize);
        autoResize();
    }
    
    // Budget validation
    const budgetInput = document.getElementById('proposed_budget');
    if (budgetInput) {
        budgetInput.addEventListener('blur', function() {
            const value = parseInt(this.value);
            if (value < 10000) {
                this.value = 10000;
                showToast('Budget minimum adalah Rp 10.000', 'warning');
            }
        });
    }
    
    // Timeline validation
    const timelineInput = document.getElementById('timeline_days');
    if (timelineInput) {
        timelineInput.addEventListener('blur', function() {
            const value = parseInt(this.value);
            if (value < 1) {
                this.value = 1;
                showToast('Timeline minimum adalah 1 hari', 'warning');
            } else if (value > 365) {
                this.value = 365;
                showToast('Timeline maksimum adalah 365 hari', 'warning');
            }
        });
    }
}

function initAnimations() {
    // Add fade-in animation to cards
    const cards = document.querySelectorAll('.sidebar-card, .project-header-card, .project-details-card');
    cards.forEach((card, index) => {
        card.classList.add('fade-in');
        setTimeout(() => {
            card.classList.add('visible');
        }, index * 100);
    });
    
    // Animate stats on scroll
    const stats = document.querySelectorAll('.stat-number');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    stats.forEach(stat => observer.observe(stat));
}

function initInteractions() {
    // Save project button
    const saveBtn = document.querySelector('.btn-save');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            const isSaved = this.classList.toggle('saved');
            
            if (isSaved) {
                this.innerHTML = '<i class="fas fa-bookmark me-2"></i>Disimpan';
                this.classList.add('btn-primary');
                this.classList.remove('btn-outline-secondary');
                showToast('Proyek berhasil disimpan', 'success');
            } else {
                this.innerHTML = '<i class="fas fa-bookmark me-2"></i>Simpan Proyek';
                this.classList.remove('btn-primary');
                this.classList.add('btn-outline-secondary');
                showToast('Proyek dihapus dari simpanan', 'info');
            }
        });
    }
    
    // Skill tags hover effect
    const skillTags = document.querySelectorAll('.skill-tag');
    skillTags.forEach(tag => {
        tag.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        tag.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

function initModalHandling() {
    const proposalModal = document.getElementById('proposalModal');
    
    if (proposalModal) {
        // Clear form when modal is hidden
        proposalModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('proposalForm');
            if (form) {
                form.reset();
                
                // Reset character count
                const charCount = document.getElementById('charCount');
                if (charCount) {
                    charCount.textContent = '0';
                    charCount.style.color = '#6c757d';
                }
                
                // Reset textarea height
                const coverLetter = document.getElementById('cover_letter');
                if (coverLetter) {
                    coverLetter.style.height = 'auto';
                }
            }
        });
        
        // Handle form submission
        const proposalForm = document.getElementById('proposalForm');
        if (proposalForm) {
            proposalForm.addEventListener('submit', function(e) {
                const coverLetter = document.getElementById('cover_letter');
                const budget = document.getElementById('proposed_budget');
                const timeline = document.getElementById('timeline_days');
                
                // Validation
                if (coverLetter && coverLetter.value.length < 100) {
                    e.preventDefault();
                    showToast('Cover letter minimal 100 karakter', 'warning');
                    coverLetter.focus();
                    return;
                }
                
                if (budget && parseInt(budget.value) < 10000) {
                    e.preventDefault();
                    showToast('Budget minimal Rp 10.000', 'warning');
                    budget.focus();
                    return;
                }
                
                if (timeline && (parseInt(timeline.value) < 1 || parseInt(timeline.value) > 365)) {
                    e.preventDefault();
                    showToast('Timeline harus antara 1-365 hari', 'warning');
                    timeline.focus();
                    return;
                }
                
                // Show loading state
                const submitBtn = document.querySelector('.btn-submit');
                if (submitBtn) {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengajukan...';
                    submitBtn.disabled = true;
                }
            });
        }
    }
}

function animateCounter(element) {
    const target = parseInt(element.textContent);
    const duration = 2000; // 2 seconds
    const step = target / (duration / 16); // 60fps
    let current = 0;
    
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 16);
}

function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1060';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'danger' ? 'danger' : 'primary'} border-0`;
    toast.setAttribute('id', toastId);
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas ${getToastIcon(type)} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
    bsToast.show();
    
    // Remove toast from DOM after hide
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function getToastIcon(type) {
    const icons = {
        'success': 'fa-check-circle',
        'info': 'fa-info-circle',
        'warning': 'fa-exclamation-triangle',
        'danger': 'fa-exclamation-circle'
    };
    
    return icons[type] || 'fa-info-circle';
}

// Utility function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Page became visible again
        initAnimations();
    }
});