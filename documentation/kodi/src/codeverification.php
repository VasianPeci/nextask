<?php
session_start();
require_once("connection.php");
$expiresAt = isset($_SESSION["datetime"]) ? $_SESSION["datetime"] : null;
$code = isset($_SESSION["code"]) ? $_SESSION["code"] : null;
$username = isset($_SESSION["username"]) ? $_SESSION["username"] : null;
$codes_match = false;
$codes_no_match = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && isset($_POST['code-input'])) {
    if ($_POST['code-input'] == $code) {
        $codes_match = true;
    } else {
        $codes_no_match = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    header("Location: sendemail.php");
    exit;
}

if ($codes_match) {
    // update user as verified
    $stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
    $_SESSION['code_verified'] = 'verified';
    header("Location: login.php");
    exit();
}
?>
<html>
    <head>
        <meta charset="utf-8">
        <title>Register</title>
        <link href="style.css" rel="stylesheet">
    </head>
    <body class="centered-body">
        <h1 id="h1-text">Enter the code sent to your email.</h1>
        <p id="expiration">Expires in: <span id="timer"></span></p>
        <form id="code-form" action="" method="post" style="margin-top: 20px;">
            <input name="code-input" id="code-input" type="text" maxlength="6">
            <?php
                if ($codes_no_match) {
                    echo "<p id='code-error' class='error-msg' style='display: block;'>Incorrect Code!</p>";
                }
            ?>
            <button type="submit" id="confirm-btn" name="confirm">Confirm</button>
        </form>
        <form action="" method="post" style="padding: 0px; margin-top: 20px;">
            <button type="submit" id="resend-btn" style="display: none;" name="resend">Resend code</button>
        </form>
        <script>
            const expirationTime = new Date("<?= $expiresAt ?>");
            const timerSpan = document.getElementById('timer');
            const expirationMsg = document.getElementById('expiration');
            const resendBtn = document.getElementById('resend-btn');
            const confirmBtn = document.getElementById('confirm-btn');
            const codeForm = document.getElementById('code-form');
            const h1Text = document.getElementById('h1-text');

            function updateTimer() {
                const now = new Date();
                const diffMs = expirationTime - now;

                if (diffMs <= 0) {
                    h1Text.innerText = "Code expired!";
                    expirationMsg.style.display = "none";
                    codeForm.style.display = "none";
                    resendBtn.style.display = "block";
                    clearInterval(interval);
                    return;
                }

                const totalSeconds = Math.floor(diffMs / 1000);
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;

                timerSpan.innerText =
                    String(minutes).padStart(2, '0') + ":" +
                    String(seconds).padStart(2, '0');
            }

            updateTimer();
            const interval = setInterval(updateTimer, 1000);
        </script>
    </body>
</html>