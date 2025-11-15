<?php

/**
 * Logout Handler
 */

session_start();

// Clear session data
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();
?>
<!DOCTYPE html>
<html>

<head>
    <title>Logging out...</title>
</head>

<body>
    <script>
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        // ✅ FIXED: Relative path
        window.location.href = '/views/auth/login.php';
    </script>
</body>

</html>