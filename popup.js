// Function to show a popup message
function showPopup(type, title, message, duration = 5000) {
    // Create container if it doesn't exist
    let container = document.querySelector('.popup-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'popup-container';
        document.body.appendChild(container);
    }

    // Create popup element
    const popup = document.createElement('div');
    popup.className = `popup-message ${type}`;

    // Set icon based on type
    let iconHtml = '';
    switch (type) {
        case 'success':
            iconHtml = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#4caf50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
            break;
        case 'error':
            iconHtml = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f44336" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
            break;
        case 'info':
            iconHtml = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2196f3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
            break;
        case 'warning':
            iconHtml = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff9800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
            break;
    }

    // Set content
    popup.innerHTML = `
        <div class="popup-icon">${iconHtml}</div>
        <div class="popup-content">
            <div class="popup-title">${title}</div>
            <div class="popup-text">${message}</div>
        </div>
        <button class="popup-close">&times;</button>
    `;

    // Add to container
    container.appendChild(popup);

    // Show the popup with animation
    setTimeout(() => {
        popup.classList.add('show');
    }, 10);

    // Set up close button
    const closeBtn = popup.querySelector('.popup-close');
    closeBtn.addEventListener('click', () => {
        closePopup(popup);
    });

    // Auto-close after duration
    if (duration) {
        setTimeout(() => {
            closePopup(popup);
        }, duration);
    }
}

// Function to close popup with animation
function closePopup(popup) {
    popup.classList.remove('show');
    setTimeout(() => {
        if (popup.parentNode) {
            popup.parentNode.removeChild(popup);
        }
    }, 300);
}

// Check for messages in URL parameters when page loads
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has('success')) {
        showPopup('success', 'Success', urlParams.get('success'));
        // Clean URL without reloading
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (urlParams.has('error')) {
        showPopup('error', 'Error', urlParams.get('error'));
        // Clean URL without reloading
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (urlParams.has('info')) {
        showPopup('info', 'Information', urlParams.get('info'));
        // Clean URL without reloading
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (urlParams.has('warning')) {
        showPopup('warning', 'Warning', urlParams.get('warning'));
        // Clean URL without reloading
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});