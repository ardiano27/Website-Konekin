// messages.js
class ChatApp {
  constructor() {
    this.currentUserId = parseInt(document.getElementById('currentUserId')?.value) || 1;
    this.currentChatUser = null;
    this.lastMessageId = 0;
    this.pollingInterval = null;
    this.lastDate = null;
    this.uploadedFiles = [];
    this.wallpaper = localStorage.getItem('chatWallpaper') || 'default';
    
    this.initializeApp();
  }

  initializeApp() {
    this.initializeSearch();
    this.initializeFileUpload();
    this.initializeAutoResize();
    this.setupUserSelection();
    this.setupSendMessage();
    this.setupWallpaper();
    this.setupEventListeners();
    
    // Check initial connection status
    this.updateConnectionStatus(navigator.onLine, 'Online');
  }

  initializeSearch() {
    const userSearch = document.getElementById('userSearch');
    if (userSearch) {
      userSearch.addEventListener('keyup', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.user-item').forEach(item => {
          const name = item.querySelector('.user-name').textContent.toLowerCase();
          item.style.display = name.includes(query) ? 'flex' : 'none';
        });
      });
    }
  }

  initializeFileUpload() {
    const fileAttach = document.getElementById('fileAttach');
    const fileInput = document.getElementById('fileInput');

    if (fileAttach && fileInput) {
      fileAttach.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', (e) => this.handleFileUpload(e));
    }
  }

  initializeAutoResize() {
    const textarea = document.getElementById('chatText');
    if (textarea) {
      textarea.addEventListener('input', () => {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
      });
    }
  }

  setupUserSelection() {
    const userItems = document.querySelectorAll('.user-item');
    const chatDefaultState = document.getElementById('chatDefaultState');
    const chatHeader = document.getElementById('chatHeader');
    const chatInputArea = document.getElementById('chatInputArea');

    userItems.forEach(item => {
      item.addEventListener('click', () => {
        this.stopPolling();
        
        userItems.forEach(u => u.classList.remove('active'));
        item.classList.add('active');
        
        const userId = item.getAttribute('data-user-id');
        const userName = item.querySelector('.user-name').textContent;
        const userAvatar = item.querySelector('.profile-avatar').src;
        
        this.currentChatUser = userId;
        sessionStorage.setItem('receiver_id', userId);
        
        document.getElementById('headerName').textContent = userName;
        document.getElementById('headerAvatar').src = userAvatar;
        
        if (chatDefaultState) chatDefaultState.style.display = 'none';
        if (chatHeader) chatHeader.style.display = 'flex';
        if (chatInputArea) chatInputArea.style.display = 'flex';

        this.loadMessages(userId, false);
      });
    });

    // Try to restore previous chat
    const savedReceiver = sessionStorage.getItem('receiver_id');
    if (savedReceiver) {
      const userItem = document.querySelector(`.user-item[data-user-id="${savedReceiver}"]`);
      if (userItem) {
        userItem.click();
      } else {
        this.showDefaultState();
      }
    } else {
      this.showDefaultState();
    }
  }

  showDefaultState() {
    const chatDefaultState = document.getElementById('chatDefaultState');
    const chatHeader = document.getElementById('chatHeader');
    const chatInputArea = document.getElementById('chatInputArea');
    
    if (chatDefaultState) chatDefaultState.style.display = 'flex';
    if (chatHeader) chatHeader.style.display = 'none';
    if (chatInputArea) chatInputArea.style.display = 'none';
  }

  setupSendMessage() {
    const chatSend = document.getElementById('chatSend');
    const chatText = document.getElementById('chatText');

    if (chatSend) {
      chatSend.addEventListener('click', () => this.sendMessage());
    }

    if (chatText) {
      chatText.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });
    }
  }

  setupWallpaper() {
    // Apply saved wallpaper
    this.applyWallpaper(this.wallpaper);
    
    // Setup wallpaper change functionality if needed
    // This would typically be in a settings modal
  }

  applyWallpaper(wallpaperName) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    // Remove existing wallpaper classes
    chatMessages.classList.remove('wallpaper-default', 'wallpaper-gradient', 'wallpaper-pattern');
    
    // Add new wallpaper class
    chatMessages.classList.add(`wallpaper-${wallpaperName}`);
    
    // Save to localStorage
    localStorage.setItem('chatWallpaper', wallpaperName);
    this.wallpaper = wallpaperName;
  }

  setupEventListeners() {
    window.addEventListener('beforeunload', () => this.stopPolling());
    
    window.addEventListener('online', () => {
      this.updateConnectionStatus(true, 'Online');
      if (this.currentChatUser) {
        this.loadMessages(this.currentChatUser, false);
      }
    });

    window.addEventListener('offline', () => {
      this.updateConnectionStatus(false);
    });
  }

  updateConnectionStatus(isOnline, message = 'Online') {
    const statusEl = document.getElementById('connectionStatus');
    const statusText = document.getElementById('statusText');
    
    if (!statusEl || !statusText) return;
    
    statusEl.style.display = 'none';
    
    if (isOnline) {
      statusEl.className = 'connection-status online';
      statusText.textContent = message;
      statusEl.style.display = 'block';
      setTimeout(() => { statusEl.style.display = 'none'; }, 2000);
    } else {
      statusEl.className = 'connection-status offline';
      statusText.textContent = 'Offline. Periksa koneksi Anda.';
      statusEl.style.display = 'block';
    }
  }

  stopPolling() {
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
      this.pollingInterval = null;
    }
  }

  getDisplayDate(dateString) {
    const today = new Date();
    const messageDate = new Date(dateString + 'T00:00:00'); 
    
    today.setHours(0, 0, 0, 0);
    messageDate.setHours(0, 0, 0, 0);

    const diff = today.getTime() - messageDate.getTime();
    const dayInMs = 24 * 60 * 60 * 1000;

    if (messageDate.toDateString() === today.toDateString()) {
        return 'Hari Ini';
    } else if (diff < 2 * dayInMs && diff > 0) {
        return 'Kemarin';
    } else if (diff < 7 * dayInMs && diff > 0) {
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return days[messageDate.getDay()];
    } else {
        const options = { day: 'numeric', month: 'short', year: 'numeric' };
        return messageDate.toLocaleDateString('id-ID', options);
    }
  }

  renderMessage(message, currentUserId) {
    let html = '';

    // Date separator logic
    if (message.created_at_date && message.created_at_date !== this.lastDate) {
        const displayDate = this.getDisplayDate(message.created_at_date);
        html += `<div class="date-separator"><span class="date-separator-text">${displayDate}</span></div>`;
        this.lastDate = message.created_at_date;
    }

    const isSender = message.sender_id == currentUserId;
    const messageClass = isSender ? 'message-bubble sent' : 'message-bubble received';

    // Attachment rendering
    let attachmentHtml = '';
    if (message.attachment_urls && message.attachment_urls.length > 0) {
        attachmentHtml = message.attachment_urls.map(url => {
            const parts = url.split('/');
            const filenameWithId = parts[parts.length - 1];
            const fileName = filenameWithId.substring(filenameWithId.indexOf('_') + 1);

            const fileExtension = fileName.split('.').pop().toLowerCase();
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (imageExtensions.includes(fileExtension)) {
                return `<div class="attachment-item image-attachment"><img src="${url}" class="img-fluid rounded" alt="Attachment" onclick="window.open('${url}', '_blank')"></div>`;
            } else {
                const iconClass = fileExtension === 'pdf' ? 'bi-file-pdf-fill' : 
                                  fileExtension.includes('doc') ? 'bi-file-word-fill' : 
                                  fileExtension.includes('xls') ? 'bi-file-excel-fill' :
                                  fileExtension.includes('ppt') ? 'bi-file-ppt-fill' : 'bi-file-earmark-fill';
                return `<div class="attachment-item file-item"><i class="bi ${iconClass}"></i> <a href="${url}" target="_blank" download="${fileName}">${fileName}</a></div>`;
            }
        }).join('');
    }

    // Main message bubble
    html += `
        <div class="message-container ${isSender ? 'align-self-end' : 'align-self-start'}">
            <div class="${messageClass}">
                ${attachmentHtml}
                ${message.message_text ? `<p class="message-text">${message.message_text}</p>` : ''}
                <div class="message-time">${message.created_at_time}</div>
            </div>
        </div>
    `;

    return html;
  }

  async loadMessages(receiverId, polling = true) {
    if (this.currentChatUser != receiverId) return; 

    this.stopPolling();

    if (!polling) {
      document.getElementById('chatMessages').innerHTML = ''; 
      this.lastDate = null; 
      this.lastMessageId = 0; 
      document.getElementById('loadingIndicator').style.display = 'block';
    }
    
    const messagesContainer = document.getElementById('chatMessages');

    try {
      const url = `fetch_message.php?other_user=${receiverId}&since_id=${this.lastMessageId}`;
      const response = await fetch(url);
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      
      if (data.error) {
         throw new Error(data.error);
      }
      
      if (data.messages.length > 0) {
        let newMessagesHtml = '';
        data.messages.forEach(msg => {
          newMessagesHtml += this.renderMessage(msg, this.currentUserId);
          if (msg.id > this.lastMessageId) {
            this.lastMessageId = msg.id;
          }
        });

        messagesContainer.insertAdjacentHTML('beforeend', newMessagesHtml);
        
        if (!polling || data.messages.some(msg => msg.sender_id == this.currentUserId)) {
             messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
      }

    } catch (e) {
      console.error('Fetch Error:', e.message);
      if (!polling) {
        messagesContainer.innerHTML = '<div class="alert alert-danger text-center m-3">Gagal memuat pesan: ' + e.message + '</div>';
      }
    } finally {
      if (!polling) {
        document.getElementById('loadingIndicator').style.display = 'none';
        messagesContainer.scrollTop = messagesContainer.scrollHeight; 
      }

      if (this.currentChatUser === receiverId) {
        this.pollingInterval = setTimeout(() => this.loadMessages(receiverId, true), 3000);
      }
    }
  }

  async handleFileUpload(e) {
    const files = e.target.files;
    if (files.length === 0) return;

    const statusEl = document.getElementById('connectionStatus');
    statusEl.className = 'connection-status uploading';
    statusEl.style.display = 'block';
    document.getElementById('statusText').textContent = 'Uploading...';

    const uploadPromises = [];
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const formData = new FormData();
        formData.append('file', file);
        
        const promise = fetch('upload-message.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error('Upload failed with status: ' + res.status);
            return res.json();
        })
        .then(data => {
            if (data.success) {
                this.uploadedFiles.push(data.fileUrl);
            } else {
                alert('Upload failed: ' + data.error);
            }
        })
        .catch(err => {
            console.error('Upload Error:', err);
            alert('Upload failed: ' + err.message);
        });
        uploadPromises.push(promise);
    }

    await Promise.all(uploadPromises);
    
    this.updateConnectionStatus(navigator.onLine);

    const messageText = document.getElementById('chatText').value.trim();
    if (this.uploadedFiles.length > 0 && messageText === '') {
        this.sendMessage();
    }
  }

  async sendMessage() {
    const messageText = document.getElementById('chatText').value.trim();
    const receiverId = this.currentChatUser;

    if (receiverId === null) {
        alert('Pilih pengguna untuk memulai chat.');
        return;
    }

    if (messageText === '' && this.uploadedFiles.length === 0) {
        return;
    }

    // Prepare Data
    const formData = new FormData();
    formData.append('receiver_id', receiverId);
    formData.append('message_text', messageText);
    formData.append('attachment_urls', JSON.stringify(this.uploadedFiles));

    // Reset Input/State
    document.getElementById('chatText').value = '';
    this.uploadedFiles = [];
    document.getElementById('fileInput').value = '';
    document.getElementById('chatText').style.height = 'auto';

    try {
        const response = await fetch('send-message.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status} ${response.statusText}`);
        }
        
        const data = await response.json();

        if (data.success) {
            this.loadMessages(receiverId, false);
        } else {
            alert('Gagal mengirim pesan: ' + data.error);
            console.error('Send Error:', data.error);
        }
    } catch (e) {
        alert('Terjadi error jaringan atau server: ' + e.message);
        console.error('Network Send Error:', e);
    }
  }
}

// Initialize the chat app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  new ChatApp();
});