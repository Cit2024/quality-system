// lib/utlis.js


/**
 * Create loading spinner element
 */
function createLoadingSpinner() {
    const spinner = document.createElement('div');
    spinner.className = 'pdf-loading-spinner';
    document.body.appendChild(spinner);
    return spinner;
}

/**
 * Remove loading spinner
 */
function removeLoadingSpinner(spinner) {
    if (document.body.contains(spinner)) {
        setTimeout(() => document.body.removeChild(spinner), 1000);
    }
}

/**
 * Arabic text processing
 */
function arabicText(text) {
    return text;
}

/**
 * Show success toast
 */
function showSuccessToast(message) {
    showToast(message, 'success');
}

/**
 * Show error toast
 */
function showErrorToast(message) {
    showToast(message, 'error');
}

/**
 * Show toast notification
 */
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 5000);
}

// Add sanitization helper
function sanitizeText(text) {
    return text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

/**
* validate Arabic Text
*/
function validateArabicText(text) {
    const arabicPattern = /[\u0600-\u06FF]/;
    return arabicPattern.test(text) ? text : `INVALID_ARABIC:${text}`;
}

/**
* enforce RTL
*/
function enforceRTL(content) {
    return content.map(item => {
        if(item.text) return { ...item, text: `\u202B${item.text}\u202C` };
        return item;
    });
}


// utils.js - Loader functions
function createLoadingSpinnerBar() {
    const loader = document.createElement('div');
    loader.className = 'loader';
    loader.innerHTML = `
        <span class="bar"></span>
        <span class="bar"></span>
        <span class="bar"></span>
    `;
    return loader;
}

function removeLoadingSpinnerBar(loader) {
    if (loader && loader.parentNode) {
        loader.parentNode.removeChild(loader);
    }
}