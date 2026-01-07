/**
 * Authentication & Session Management
 * Include this in all protected pages
 */

// Check if user is logged in
function checkAuth() {
    const isLoggedIn = sessionStorage.getItem('isLoggedIn');
    if (!isLoggedIn || isLoggedIn !== 'true') {
        window.location.href = 'index.html';
        return false;
    }
    return true;
}

// Logout function
function logout() {
    if (confirm('Yakin ingin logout?')) {
        // Clear session
        sessionStorage.clear();
        
        // Redirect to login
        window.location.href = 'index.html';
    }
    return false;
}

// Get current user info
function getCurrentUser() {
    return {
        username: sessionStorage.getItem('username') || 'Guest',
        role: sessionStorage.getItem('userRole') || 'user',
        isLoggedIn: sessionStorage.getItem('isLoggedIn') === 'true'
    };
}

// Auto-check auth on page load
if (window.location.pathname.indexOf('index.html') === -1 && 
    window.location.pathname !== '/' && 
    !window.location.pathname.endsWith('/')) {
    
    // Only check auth if not on login page
    window.addEventListener('DOMContentLoaded', function() {
        checkAuth();
    });
}

// Prevent going back to login after logged in
if (window.location.pathname.indexOf('index.html') !== -1 || 
    window.location.pathname === '/' || 
    window.location.pathname.endsWith('/')) {
    
    window.addEventListener('DOMContentLoaded', function() {
        const isLoggedIn = sessionStorage.getItem('isLoggedIn');
        if (isLoggedIn === 'true') {
            window.location.href = 'dashboard.html';
        }
    });
}
