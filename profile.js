// Profile Page JavaScript - Updated for minimalist design
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Cropper
    let cropper;
    const image = document.getElementById('cropImage');
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarData = document.getElementById('avatarData');
    const avatarImage = document.getElementById('avatarImage');
    
    // Avatar Modal Elements
    const avatarModal = document.getElementById('avatarModal');
    const cropSave = document.getElementById('cropSave');
    const zoomIn = document.getElementById('zoomIn');
    const zoomOut = document.getElementById('zoomOut');
    const rotateLeft = document.getElementById('rotateLeft');
    const rotateRight = document.getElementById('rotateRight');

    // File input change handler
    avatarInput.addEventListener('change', function(e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const file = files[0];
            
            // Check file type
            if (!file.type.match('image.*')) {
                showAlert('Please select an image file.', 'error');
                return;
            }
            
            // Check file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showAlert('File size should be less than 5MB.', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(event) {
                image.src = event.target.result;
                
                // Destroy previous cropper instance
                if (cropper) {
                    cropper.destroy();
                }
                
                // Initialize cropper
                cropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    guides: true,
                    background: false,
                    responsive: true,
                    restore: true,
                    checkCrossOrigin: false,
                    checkOrientation: false,
                    modal: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                    minCropBoxWidth: 100,
                    minCropBoxHeight: 100,
                });
            };
            reader.readAsDataURL(file);
        }
    });

    // Crop control handlers
    zoomIn.addEventListener('click', function() {
        if (cropper) {
            cropper.zoom(0.1);
        }
    });

    zoomOut.addEventListener('click', function() {
        if (cropper) {
            cropper.zoom(-0.1);
        }
    });

    rotateLeft.addEventListener('click', function() {
        if (cropper) {
            cropper.rotate(-90);
        }
    });

    rotateRight.addEventListener('click', function() {
        if (cropper) {
            cropper.rotate(90);
        }
    });

    // Save crop handler
    cropSave.addEventListener('click', function() {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({
                width: 300,
                height: 300,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            
            if (canvas) {
                const croppedImage = canvas.toDataURL('image/png');
                
                // Update preview
                avatarPreview.src = croppedImage;
                
                // Store data for form submission
                avatarData.value = JSON.stringify(cropper.getData());
                avatarImage.value = croppedImage;
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(avatarModal);
                modal.hide();
                
                showAlert('Profile photo updated successfully!', 'success');
            }
        }
    });

    // Reset modal when closed
    avatarModal.addEventListener('hidden.bs.modal', function() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        avatarInput.value = '';
        image.src = '';
    });

    // Skill checkbox handlers
    const skillCheckboxes = document.querySelectorAll('.skill-checkbox');
    skillCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const details = this.closest('.skill-item').querySelector('.skill-details');
            if (details) {
                details.style.display = this.checked ? 'block' : 'none';
            }
        });
    });

    // Trigger change event for pre-checked skills
    document.querySelectorAll('.skill-checkbox:checked').forEach(checkbox => {
        checkbox.dispatchEvent(new Event('change'));
    });

    // Form submission handler
    const profileForm = document.getElementById('profileForm');
    profileForm.addEventListener('submit', function(e) {
        // Add loading state
        const submitBtn = profileForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        
        // Re-enable button after 3 seconds (in case of error)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    });

    // Enhanced form validation
    profileForm.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = profileForm.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                // Add error message
                if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'This field is required';
                    field.parentNode.appendChild(errorDiv);
                }
            } else {
                field.classList.remove('is-invalid');
                const errorDiv = field.parentNode.querySelector('.invalid-feedback');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showAlert('Please complete all required fields.', 'error');
        }
    });

    // Remove validation on input
    const formInputs = profileForm.querySelectorAll('input, textarea, select');
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const errorDiv = this.parentNode.querySelector('.invalid-feedback');
            if (errorDiv) {
                errorDiv.remove();
            }
        });
    });

    // Show alert function
    function showAlert(message, type = 'info') {
        const alertClass = type === 'error' ? 'alert-danger' : 
                          type === 'success' ? 'alert-success' : 'alert-info';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show custom-alert`;
        alert.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.querySelector('.profile-container').insertBefore(alert, document.querySelector('.profile-container').firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    console.log('Profile page initialized successfully!');
});