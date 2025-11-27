document.addEventListener('DOMContentLoaded', function() {
    // Initialize progress slider
    const progressSlider = document.getElementById('progressSlider');
    const progressValue = document.getElementById('progressValue');
    
    if (progressSlider && progressValue) {
        progressSlider.addEventListener('input', function() {
            progressValue.textContent = this.value + '%';
        });
    }

    // Update task button handlers
    const updateTaskButtons = document.querySelectorAll('.update-task-btn');
    updateTaskButtons.forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            document.getElementById('updateTaskId').value = taskId;
            
            // You can fetch current task progress here and set the slider
            // For now, we'll set it to 0
            if (progressSlider) {
                progressSlider.value = 0;
                progressValue.textContent = '0%';
            }
        });
    });

    // Revision button handlers
    const revisionButtons = document.querySelectorAll('.request-revision-btn');
    revisionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            document.getElementById('revisionTaskId').value = taskId;
        });
    });

    // Update task form submission
    const updateTaskForm = document.getElementById('updateTaskForm');
    if (updateTaskForm) {
        updateTaskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const taskId = formData.get('task_id');
            
            // Simulate API call - replace with actual API endpoint
            simulateAPICall('update_task.php', formData)
                .then(response => {
                    showNotification('Progress berhasil diperbarui!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                })
                .catch(error => {
                    showNotification('Error: ' + error.message, 'error');
                });
        });
    }

    // Final submission form
    const finalSubmissionForm = document.getElementById('finalSubmissionForm');
    if (finalSubmissionForm) {
        finalSubmissionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Validate all tasks are completed
            const incompleteTasks = document.querySelectorAll('.task-status:not(.status-completed)');
            if (incompleteTasks.length > 0) {
                showNotification('Harap selesaikan semua tugas sebelum submit final project!', 'warning');
                return;
            }
            
            // Simulate API call
            simulateAPICall('submit_final.php', formData)
                .then(response => {
                    showNotification('Final project berhasil disubmit! Menunggu review dari UMKM.', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                })
                .catch(error => {
                    showNotification('Error: ' + error.message, 'error');
                });
        });
    }

    // Revision form submission
    const revisionForm = document.getElementById('revisionForm');
    if (revisionForm) {
        revisionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            simulateAPICall('request_revision.php', formData)
                .then(response => {
                    showNotification('Permintaan revisi berhasil dikirim!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                })
                .catch(error => {
                    showNotification('Error: ' + error.message, 'error');
                });
        });
    }

    // Utility function to simulate API calls
    function simulateAPICall(url, formData) {
        return new Promise((resolve, reject) => {
            // In real implementation, use fetch API
            // fetch(url, { method: 'POST', body: formData })
            //     .then(response => response.json())
            //     .then(data => resolve(data))
            //     .catch(error => reject(error));
            
            // Simulate network delay
            setTimeout(() => {
                // Simulate random success (90% success rate)
                if (Math.random() > 0.1) {
                    resolve({ success: true, message: 'Operation completed successfully' });
                } else {
                    reject(new Error('Network error or server unavailable'));
                }
            }, 1000);
        });
    }

    // Notification function
    function showNotification(message, type = 'info') {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type];

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        `;
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }

    // Add smooth animations for progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
});