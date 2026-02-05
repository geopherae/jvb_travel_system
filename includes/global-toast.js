/**
 * Global Toast Notification System
 * Usage: window.showToast(message, type, duration)
 * Types: 'success', 'error', 'info', 'message-received'
 * Duration: milliseconds (default 3000)
 */

if (typeof window.showToast !== 'function') {
  window.showToast = function(message, type = 'info', duration = 3000) {
    const bgColor = type === 'success' ? 'bg-emerald-50 border-emerald-300' : 
                    type === 'error' ? 'bg-red-50 border-red-300' :
                    type === 'message-received' ? 'bg-blue-50 border-blue-300' :
                    'bg-blue-50 border-blue-300';
    const textColor = type === 'success' ? 'text-emerald-800' : 
                      type === 'error' ? 'text-red-800' :
                      type === 'message-received' ? 'text-blue-800' :
                      'text-blue-800';
    
    const toast = document.createElement('div');
    toast.innerHTML = `<div class="fixed top-4 right-4 z-50 ${bgColor} border ${textColor} px-6 py-4 rounded-lg shadow-lg max-w-md w-full animate-fade-in">
      <p class="text-sm">${message}</p>
    </div>`;
    
    document.body.appendChild(toast.firstElementChild);
    const toastEl = toast.firstElementChild;
    
    setTimeout(() => {
      toastEl.style.opacity = '0';
      toastEl.style.transition = 'opacity 0.3s ease-out';
      setTimeout(() => toastEl.remove(), 300);
    }, duration);
  };
}
