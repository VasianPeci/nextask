<?php
session_start();
require_once("connection.php");

if (isset($_COOKIE['remember_me'])) {
    $tokenHash = hash('sha256', $_COOKIE['remember_me']);

    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE token_hash = ?");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $stmt->close();

    setcookie('remember_me', '', time() - 3600, '/');
}

session_destroy();
header("Location: login.php");
exit;
?>