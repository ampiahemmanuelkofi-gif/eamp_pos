// Auto Logout Script
// Relies on SETTINGS global object defined in header.php

(function () {
    // Default to 30 minutes if not set
    const timeoutMinutes = (SETTINGS && SETTINGS.auto_logout_minutes) ? parseInt(SETTINGS.auto_logout_minutes) : 30;

    // Convert to milliseconds
    const timeoutDuration = timeoutMinutes * 60 * 1000;

    let logoutTimer;

    function startTimer() {
        // Clear existing timer
        if (logoutTimer) clearTimeout(logoutTimer);

        // Set new timer
        logoutTimer = setTimeout(logoutUser, timeoutDuration);
    }

    function logoutUser() {
        // Redirect to logout
        window.location.href = BASE_URL + '/modules/auth/logout.php?reason=timeout';
    }

    // Reset timer on user activity
    function resetTimer() {
        startTimer();
    }

    // Event listeners for activity
    document.addEventListener('mousemove', resetTimer);
    document.addEventListener('keypress', resetTimer);
    document.addEventListener('click', resetTimer);
    document.addEventListener('scroll', resetTimer);
    document.addEventListener('touchstart', resetTimer); // For touch devices

    // Initialize
    console.log(`Auto-logout initialized: ${timeoutMinutes} minutes`);
    startTimer();
})();
