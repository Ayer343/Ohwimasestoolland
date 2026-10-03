/**
 * Toast Notification System
 * Handles all toast/notification messages
 */
window.Toast = {
    container: null,
    
    init: function() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            this.container.id = 'toastContainer';
            document.body.appendChild(this.container);
        }
    },
    
    show: function(message, type = 'success', duration = 5000) {
        if (!this.container) this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = '';
        let title = '';
        
        switch(type) {
            case 'success':
                icon = 'fa-check-circle';
                title = 'Success!';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                title = 'Error!';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                title = 'Warning!';
                break;
            case 'info':
                icon = 'fa-info-circle';
                title = 'Info!';
                break;
        }
        
        let content = `
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="toast-message">
                    <h4>${title}</h4>
        `;
        
        if (typeof message === 'string') {
            content += `<p>${this.escapeHtml(message)}</p>`;
        } else if (Array.isArray(message)) {
            content += '<ul>';
            message.forEach(msg => {
                content += `<li>${this.escapeHtml(msg)}</li>`;
            });
            content += '</ul>';
        } else if (typeof message === 'object') {
            content += `<p>${this.escapeHtml(message.message || 'An error occurred')}</p>`;
        }
        
        content += `
                </div>
                <button class="toast-close" onclick="this.closest('.toast').classList.add('hiding'); setTimeout(() => this.closest('.toast').remove(), 300)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        toast.innerHTML = content;
        this.container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => window.Toast.init());