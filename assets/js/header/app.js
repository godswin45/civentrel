// Header Module Bootstrap
Promise.all([
    window.loadCiventralScript('assets/js/header/clock.js'),
    window.loadCiventralScript('assets/js/header/theme.js'),
    window.loadCiventralScript('assets/js/header/dropdown.js'),
    window.loadCiventralScript('assets/js/header/logout-modal.js'),
    window.loadCiventralScript('assets/js/header/inactivity.js'),
    window.loadCiventralScript('assets/js/header/notifications.js'),
    window.loadCiventralScript('assets/js/header/sidebar.js')
]).then(() => {
    // Initialization executes after all header scripts have loaded
    if (typeof updateHeaderClock === 'function') {
        updateHeaderClock();
        setInterval(updateHeaderClock, 10000);
    }
    if (typeof syncThemeToggleIcon === 'function') {
        syncThemeToggleIcon();
    }

    const activityEvents = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'];
    activityEvents.forEach(event => {
        document.addEventListener(event, () => {
            if (typeof resetInactivityTimer === 'function') {
                resetInactivityTimer();
            }
        }, { passive: true });
    });

    // Make sure countdownInterval is accessible (declared in inactivity.js)
    if (typeof updateCountdownDisplay === 'function') {
        countdownInterval = setInterval(updateCountdownDisplay, 1000);
        if (typeof resetInactivityTimer === 'function') {
            resetInactivityTimer();
        }
    }
}).catch(err => {
    console.error('Failed to load header scripts:', err);
});
