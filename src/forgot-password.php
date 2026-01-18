<?php
session_start();
require_once("connection.php");

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_id'] == 1) {
        header("Location: admin.php");
    } else {
        header("Location: profile.php");
    }
    exit;
}

$user_exists = false;
$email_exists = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['password'])) {
        $hashedPassword = password_hash($_POST["password"], PASSWORD_DEFAULT);
        // reset password
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $stmt->bind_param("ss", $hashedPassword, $_SESSION['username']);
        if ($stmt->execute()) {
            $_SESSION['password_updated'] = 'updated';
        }
        $stmt->close();
        header("Location: login.php");
        exit;
    } else {
        if (!isset($_SESSION['username'])) {
            $_SESSION['username'] = trim($_POST["username"]);
        } else {
            if ($_SESSION['username'] != trim($_POST["username"])) {
                $_SESSION['username'] = trim($_POST["username"]);
            }
        }
        $email = trim($_POST["email"]);

        // check if user with this username exists
        $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
        $stmt->bind_param("s", $_SESSION['username']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user_exists = true;
            $row = $result->fetch_assoc();

            if ($email == $row['email']) {
                $email_exists = true;
            }
        }

        $stmt->close(); 
    }
}
$is_successful = $user_exists && $email_exists;
?>
<html>
    <head>
        <meta charset="utf-8">
        <title>Reset Password</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="centered-body">
        <h1>Reset Password</h1>
        <form id="reset-form" action="" method="post">
            <?php if (!$is_successful): ?>
                <input id="username" name="username" type="text" placeholder="Username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                <p id="username-error" class="error-msg">Username cannot be empty!</p>
                <?php 
                    if (isset($_POST['username']) && !$user_exists) {
                        echo "<p id='existing-user' class='server-error' style='display: block'>User does not exist!</p>";
                    } 
                ?>
                <h3>Enter your email, we will send a verification code for you to reset your password:</h3>
                <input id="email" name="email" type="text">
                <p id="email-error" class="error-msg">Email must be a valid one!</p>
                <?php
                    if (isset($_POST['email']) && !$email_exists) {
                        echo "<p id='existing-email' class='server-error' style='display: block'>This email does not match the user!</p>";
                    }
                ?>
            <?php else: ?>
                <h3>Type the new password:</h3>
                <input id="password" name="password" type="password" placeholder="Password" value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>">
                <p id="password-error" class="error-msg">Password must have a length of 8-20 characters!</p>
                <input id="reentered-password" name="reentered" type="password" placeholder="Re-enter Password" value="<?php echo htmlspecialchars($_POST['reentered'] ?? ''); ?>">
                <p id="reentered-password-error" class="error-msg">Passwords do not match!</p>
            <?php endif; ?>
            <button type='submit'>Confirm</button>
        </form>
    </body>
    <script>
        const form = document.getElementById('reset-form');
        const email = document.getElementById('email');
        const emailErr = document.getElementById('email-error');
        const emailServerErr = document.getElementById('existing-email');
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const username = document.getElementById('username');
        const usernameErr = document.getElementById('username-error');
        const usernameServerErr = document.getElementById('existing-user');
        const codeErr = document.getElementById('code-error');
        const password = document.getElementById('password');
        const reenteredPassword = document.getElementById('reentered-password');
        const passwordErr = document.getElementById('password-error');
        const reenteredErr = document.getElementById('reentered-password-error');

        form.addEventListener("submit", (e) => {
            if (emailErr) emailErr.style.display = "none";
            if (usernameErr) usernameErr.style.display = "none";
            if (passwordErr) passwordErr.style.display = "none";
            if (reenteredErr) reenteredErr.style.display = "none";
            if (codeErr) codeErr.style.display = "none";
            if (emailServerErr) emailServerErr.style.display = "none";
            if (usernameServerErr) usernameServerErr.style.display = "none";

            if (username) {
                if (!username.value.trim()) {
                    e.preventDefault();
                    usernameErr.style.display = "block";
                    return;
                }
                if (!email.value.trim() || !emailRegex.test(email.value)) {
                    e.preventDefault();
                    emailErr.style.display = "block";
                    return;
                }
            } else {
                if (password.value.length > 20 || password.value.length < 8) {
                    e.preventDefault();
                    passwordErr.style.display = "block";
                    return;
                }
                if (reenteredPassword.value !== password.value) {
                    e.preventDefault();
                    reenteredErr.style.display = "block";
                    return;
                }
            }
        });
    </script>
</html>