<?php
session_start();
require_once("connection.php");
require_once("mailer.php");
if (!isset($_SESSION['username'], $_SESSION['email'], $_SESSION['firstname'])) {
    header("Location: register.php");
    exit;
}

$code = random_int(100000, 999999);
$user_id = null;
$dateObj = new DateTime('now', new DateTimeZone('UTC'));
$dateObj->modify('+5 minutes');

// Format 1: For MySQL (Standard format)
$db_datetime = $dateObj->format('Y-m-d H:i:s'); 

// Format 2: For JavaScript (ISO 8601 with the Z)
$js_datetime = $dateObj->format('Y-m-d\TH:i:s\Z');

$username = $_SESSION["username"];
$firstName = $_SESSION["firstname"];
$email = $_SESSION["email"];
// get user id
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $user_id = (int) $row['user_id'];
}

$stmt->close();

// insert the email verification log
$stmt = $conn->prepare("
    INSERT INTO email_verifications (user_id, verification_code, expires_at)
    VALUES (?, ?, ?)
");

$stmt->bind_param(
    "iss",
    $user_id,
    $code,
    $db_datetime
);

$stmt->execute();
$stmt->close();

Mailer::sendCustomEmail(
    "nextasksystem@gmail.com",
    "NexTask Team",
    $email,
    $firstName,
    "Verify Your Account",
    "<h1>Hello $firstName!</h1><p>Thanks for joining NexTask. Verify your account under 5 minutes using the code below:</p>
    <div><p style='font-size: 40px'>$code</p></div>"
);
$_SESSION["username"] = $username;
$_SESSION["datetime"] = $js_datetime;
$_SESSION["code"] = $code;
header("Location: codeverification.php");
exit;
?>