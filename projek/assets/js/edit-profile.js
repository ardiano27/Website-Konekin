// Variabel global untuk cropper
let avatarCropper = null;
let backgroundCropper = null;

// Inisialisasi ketika dokumen siap
document.addEventListener('DOMContentLoaded', function() {
    initializeAvatarModal();
    initializeBackgroundModal();
    initializeFormValidation();
    updateAvailabilityBadge();
});

// Update availability badge berdasarkan checkbox
function updateAvailabilityBadge() {
    const checkbox = document.getElementById('is_available');
    const badge = document.querySelector('.availability-badge');
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            badge.classList.remove('not-available');
            badge.classList.add('available');
            badge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Tersedia';
        } else {
            badge.classList.remove('available');
            badge.classList.add('not-available');
            badge.innerHTML = '<i class="fas fa-times-circle me-1"></i>Tidak Tersedia';
        }
    });
}

// Inisialisasi modal avatar
function initializeAvatarModal() {
    const avatarModal = document.getElementById('avatarModal');
    
    avatarModal.addEventListener('show.bs.modal', function() {
        // Reset form
        document.getElementById('avatar-upload').value = '';
        document.getElementById('avatar-cropper').style.display = 'none';
        document.getElementById('avatar-preview').src = '';
        
        if (avatarCropper) {
            avatarCropper.destroy();
            avatarCropper = null;
        }
    });
    
    // Handle upload gambar untuk avatar
    document.getElementById('avatar-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validasi tipe file
        if (!file.type.match('image.*')) {
            showAlert('error', 'Silakan pilih file gambar yang valid (JPEG, PNG, dll.)');
            return;
        }
        
        // Validasi ukuran file (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            showAlert('error', 'Ukuran file terlalu besar. Maksimal 5MB.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(event) {
            const image = document.getElementById('avatar-cropper');
            image.src = event.target.result;
            image.style.display = 'block';
            
            // Inisialisasi cropper dengan aspect ratio 1:1 (persegi untuk avatar bulat)
            if (avatarCropper) {
                avatarCropper.destroy();
            }
            
            avatarCropper = new Cropper(image, {
                aspectRatio: 1,
                viewMode: 1,
                guides: false,
                background: false,
                autoCropArea: 0.8,
                responsive: true,
                checkCrossOrigin: false,
                crop: function(event) {
                    // Update preview
                    const canvas = avatarCropper.getCroppedCanvas({
                        width: 200,
                        height: 200,
                    });
                    document.getElementById('avatar-preview').src = canvas.toDataURL();
                }
            });
        };
        reader.readAsDataURL(file);
    });
    
    // Simpan avatar
    document.getElementById('save-avatar').addEventListener('click', function() {
        if (!avatarCropper) {
            showAlert('error', 'Silakan pilih gambar terlebih dahulu');
            return;
        }
        
        const saveButton = this;
        const originalText = saveButton.innerHTML;
        
        // Tampilkan loading
        saveButton.innerHTML = '<div class="loading-spinner"></div>Menyimpan...';
        saveButton.disabled = true;
        
        // Dapatkan gambar yang sudah di-crop
        const canvas = avatarCropper.getCroppedCanvas({
            width: 300,
            height: 300,
        });
        
        // Konversi ke base64
        const avatarDataUrl = canvas.toDataURL('image/png', 0.8);
        
        // Kirim ke server via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'edit-profile.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Update avatar yang ditampilkan
                            const currentAvatar = document.getElementById('current-avatar');
                            if (currentAvatar) {
                                currentAvatar.src = response.avatar_url + '?t=' + new Date().getTime();
                            }
                            
                            // Update badge status
                            updateAvatarBadge(true);
                            
                            // Tutup modal
                            const modal = bootstrap.Modal.getInstance(avatarModal);
                            modal.hide();
                            
                            showAlert('success', 'Avatar berhasil diubah!');
                        } else {
                            showAlert('error', response.error || 'Terjadi kesalahan saat mengupload avatar');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        showAlert('error', 'Terjadi kesalahan saat memproses respons');
                    }
                } else {
                    showAlert('error', 'Terjadi kesalahan jaringan: ' + xhr.status);
                }
            }
        };
        
        xhr.onerror = function() {
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
            showAlert('error', 'Terjadi kesalahan jaringan');
        };
        
        xhr.send('avatar_data=' + encodeURIComponent(avatarDataUrl));
    });
}

// Inisialisasi modal background
function initializeBackgroundModal() {
    const backgroundModal = document.getElementById('backgroundModal');
    
    backgroundModal.addEventListener('show.bs.modal', function() {
        // Reset form
        document.getElementById('background-upload').value = '';
        document.getElementById('background-preview').src = '';
        
        if (backgroundCropper) {
            backgroundCropper.destroy();
            backgroundCropper = null;
        }
    });
    
    // Handle upload gambar untuk background
    document.getElementById('background-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validasi tipe file
        if (!file.type.match('image.*')) {
            showAlert('error', 'Silakan pilih file gambar yang valid (JPEG, PNG, dll.)');
            return;
        }
        
        // Validasi ukuran file (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            showAlert('error', 'Ukuran file terlalu besar. Maksimal 10MB.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(event) {
            const image = new Image();
            image.onload = function() {
                // Buat canvas untuk crop background
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Tentukan rasio aspek untuk background (16:9)
                const targetAspect = 16 / 9;
                const sourceAspect = image.width / image.height;
                
                let sourceX = 0;
                let sourceY = 0;
                let sourceWidth = image.width;
                let sourceHeight = image.height;
                
                if (sourceAspect > targetAspect) {
                    // Gambar lebih lebar, crop sisi kiri/kanan
                    sourceWidth = image.height * targetAspect;
                    sourceX = (image.width - sourceWidth) / 2;
                } else {
                    // Gambar lebih tinggi, crop atas/bawah
                    sourceHeight = image.width / targetAspect;
                    sourceY = (image.height - sourceHeight) / 2;
                }
                
                // Set ukuran canvas
                canvas.width = 1200;
                canvas.height = 675;
                
                // Draw image ke canvas
                ctx.drawImage(
                    image,
                    sourceX, sourceY, sourceWidth, sourceHeight,
                    0, 0, canvas.width, canvas.height
                );
                
                // Tampilkan preview
                document.getElementById('background-preview').src = canvas.toDataURL('image/jpeg', 0.8);
            };
            image.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });
    
    // Simpan background
    document.getElementById('save-background').addEventListener('click', function() {
        const backgroundPreview = document.getElementById('background-preview');
        if (!backgroundPreview.src || backgroundPreview.src === '') {
            showAlert('error', 'Silakan pilih gambar background terlebih dahulu');
            return;
        }
        
        const saveButton = this;
        const originalText = saveButton.innerHTML;
        
        // Tampilkan loading
        saveButton.innerHTML = '<div class="loading-spinner"></div>Menyimpan...';
        saveButton.disabled = true;
        
        // Kirim ke server via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'edit-profile.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Update background yang ditampilkan
                            const backgroundElement = document.getElementById('profile-background');
                            backgroundElement.style.backgroundImage = `url('${response.background_url}?t=${new Date().getTime()}')`;
                            
                            // Update badge status
                            updateBackgroundBadge(true);
                            
                            // Tutup modal
                            const modal = bootstrap.Modal.getInstance(backgroundModal);
                            modal.hide();
                            
                            showAlert('success', 'Background berhasil diubah!');
                        } else {
                            showAlert('error', response.error || 'Terjadi kesalahan saat mengupload background');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        showAlert('error', 'Terjadi kesalahan saat memproses respons');
                    }
                } else {
                    showAlert('error', 'Terjadi kesalahan jaringan: ' + xhr.status);
                }
            }
        };
        
        xhr.onerror = function() {
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
            showAlert('error', 'Terjadi kesalahan jaringan');
        };
        
        xhr.send('background_data=' + encodeURIComponent(backgroundPreview.src));
    });
}

// Update badge avatar
function updateAvatarBadge(hasAvatar) {
    const badges = document.querySelectorAll('.availability-badge');
    badges.forEach(badge => {
        if (badge.textContent.includes('Foto Profil')) {
            if (hasAvatar) {
                badge.classList.remove('not-available');
                badge.classList.add('available');
                badge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Sudah diatur';
            } else {
                badge.classList.remove('available');
                badge.classList.add('not-available');
                badge.innerHTML = '<i class="fas fa-times-circle me-1"></i>Belum diatur';
            }
        }
    });
}

// Update badge background
function updateBackgroundBadge(hasBackground) {
    const badges = document.querySelectorAll('.availability-badge');
    badges.forEach(badge => {
        if (badge.textContent.includes('Background Profil')) {
            if (hasBackground) {
                badge.classList.remove('not-available');
                badge.classList.add('available');
                badge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Sudah diatur';
            } else {
                badge.classList.remove('available');
                badge.classList.add('not-available');
                badge.innerHTML = '<i class="fas fa-times-circle me-1"></i>Belum diatur';
            }
        }
    });
}

// Inisialisasi validasi form
function initializeFormValidation() {
    const form = document.getElementById('profile-form');
    
    form.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const phone = document.getElementById('phone').value;
        let isValid = true;
        
        // Clear previous errors
        clearErrors();
        
        // Validasi email
        if (!isValidEmail(email)) {
            showFieldError('email', 'Format email tidak valid');
            isValid = false;
        }
        
        // Validasi phone (jika diisi)
        if (phone && !isValidPhone(phone)) {
            showFieldError('phone', 'Format nomor telepon tidak valid');
            isValid = false;
        }
        
        // Validasi hourly rate (jika diisi)
        const hourlyRate = document.getElementById('hourly_rate').value;
        if (hourlyRate && (!isValidNumber(hourlyRate) || parseFloat(hourlyRate) < 0)) {
            showFieldError('hourly_rate', 'Tarif per jam harus berupa angka positif');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showAlert('error', 'Terdapat kesalahan dalam pengisian form. Silakan periksa kembali.');
        }
    });
    
    // Real-time validation
    document.getElementById('email').addEventListener('blur', function() {
        if (this.value && !isValidEmail(this.value)) {
            showFieldError('email', 'Format email tidak valid');
        } else {
            clearFieldError('email');
        }
    });
    
    document.getElementById('phone').addEventListener('blur', function() {
        if (this.value && !isValidPhone(this.value)) {
            showFieldError('phone', 'Format nomor telepon tidak valid');
        } else {
            clearFieldError('phone');
        }
    });
    
    document.getElementById('hourly_rate').addEventListener('blur', function() {
        if (this.value && (!isValidNumber(this.value) || parseFloat(this.value) < 0)) {
            showFieldError('hourly_rate', 'Tarif per jam harus berupa angka positif');
        } else {
            clearFieldError('hourly_rate');
        }
    });
}

// Fungsi validasi
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidPhone(phone) {
    const re = /^[0-9+\-\s()]{10,20}$/;
    return re.test(phone);
}

function isValidNumber(value) {
    return !isNaN(value) && isFinite(value);
}

// Fungsi untuk menampilkan error di field
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const formGroup = field.closest('.mb-3');
    
    // Hapus error sebelumnya
    const existingError = formGroup.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Tambahkan class error ke field
    field.classList.add('is-invalid');
    
    // Buat element error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error invalid-feedback d-block';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${message}`;
    
    formGroup.appendChild(errorDiv);
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const formGroup = field.closest('.mb-3');
    
    field.classList.remove('is-invalid');
    
    const existingError = formGroup.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

function clearErrors() {
    const errorFields = document.querySelectorAll('.is-invalid');
    errorFields.forEach(field => {
        field.classList.remove('is-invalid');
    });
    
    const errorMessages = document.querySelectorAll('.field-error');
    errorMessages.forEach(error => error.remove());
}

// Fungsi untuk menampilkan alert
function showAlert(type, message) {
    // Hapus alert sebelumnya
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Buat alert baru
    const alertDiv = document.createElement('div');
    alertDiv.className = `custom-alert alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        border: none;
        border-radius: 12px;
    `;
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const iconColor = type === 'success' ? 'var(--success-color)' : 'var(--danger-color)';
    
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${icon} me-2" style="color: ${iconColor};"></i>
            <div>${message}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove setelah 5 detik
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Tambahkan style untuk field error
const style = document.createElement('style');
style.textContent = `
    .is-invalid {
        border-color: var(--danger-color) !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15) !important;
    }
    
    .field-error {
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
    
    .custom-alert {
        animation: slideInRight 0.3s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);