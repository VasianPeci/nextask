<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_COOKIE['remember_me'])) {

    $rawToken = $_COOKIE['remember_me'];
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $conn->prepare("
        SELECT user_id
        FROM remember_tokens
        WHERE token_hash = ?
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $_SESSION['user_id'] = (int)$row['user_id'];
    } else {
        setcookie('remember_me', '', time() - 3600, '/');
    }

    $stmt->close();
}
?>
