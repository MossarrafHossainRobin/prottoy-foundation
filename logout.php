<?php
session_start();
require_once 'db.php';

// --- DELETE THE "REMEMBER ME" TOKEN FROM THE DATABASE ---
if (isset($_COOKIE['remember_me'])) {
    $cookie_token = $_COOKIE['remember_me'];
    $token_hash = hash('sha256', $cookie_token);

    $sql = "DELETE FROM auth_tokens WHERE token_hash = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $stmt->close();
    }
    
    // Unset the cookie by setting its expiration to the past
    setcookie('remember_me', '', time() - 3600, '/');
}

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>