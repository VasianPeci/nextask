<?php
session_start();
$code_verified = isset($_SESSION['code_verified']) ? 'verified' : null;
$password_updated = isset($_SESSION['password_updated']) ? 'updated' : null;

$_SESSION = [];
require_once("connection.php");
require_once("authorization.php");

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_id'] == 1) {
        header("Location: admin.php");
    } else {
        header("Location: profile.php");
    }
    exit;
}

if (isset($_SESSION['recovery_time']) && time() >= $_SESSION['recovery_time']) {
    unset($_SESSION['recovery_time']);
    unset($_SESSION['failed_attempts']);
}

if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
}

$user_exists = false;
$incorrect_password = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_SESSION['recovery_time'])) {
        return;
    }

    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $user_id = 0;

    // check if user with this username exists
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_exists = true;
        $row = $result->fetch_assoc();
        $hashedPassword = $row['password_hash'];

        if (!password_verify($password, $hashedPassword)) {
            $incorrect_password = true;
        }
    }

    $stmt->close();

    if (!$user_exists || $incorrect_password) {
        $_SESSION['failed_attempts']++;
    }

    if ($_SESSION['failed_attempts'] >= 7 && !isset($_SESSION['recovery_time'])) {
        $_SESSION['recovery_time'] = time() + 60;
    }

    $is_successful = $user_exists && !$incorrect_password;

    if ($user_exists) {
        // get user id
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $user_id = (int) $row['user_id'];
        }

        $stmt->close();

        // insert log attempt into login_attempts
        $stmt = $conn->prepare("INSERT INTO login_attempts (user_id, is_successful) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $is_successful);
        $stmt->execute();
        $stmt->close();
    }

    // redirect to profile page if user credentials are correct
    if ($is_successful) {
        if (isset($_POST['remember-me'])) {
            // 1. generate secure token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);

            // 2. expiration (30 days)
            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

            // 3. store in DB
            $stmt = $conn->prepare("
                INSERT INTO remember_tokens (user_id, token_hash, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iss", $user_id, $tokenHash, $expiresAt);
            $stmt->execute();
            $stmt->close();

            // 4. set cookie
            setcookie(
                'remember_me',
                $rawToken,
                [
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }

        unset($_SESSION['failed_attempts'], $_SESSION['recovery_time']);
        $_SESSION['user_id'] = $user_id;
        if ($username == "admin") {
            header("Location: admin.php");
        } else {
            header("Location: profile.php");
        }
        exit;
    }
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Login</title>
        <link href="style.css" rel="stylesheet">
    </head>
    <body class="centered-body">
        <?php
            if ($password_updated == 'updated') {
                echo "<script>alert('Password successfully updated!')</script>";
            }
        ?>
        <?php 
            if ($code_verified == 'verified') {
                echo "<script>alert('Verification successful!')</script>";
            }
        ?>
        <h1>Login</h1>
        <form id="login-form" action="" method="POST">
            <input id="username" name="username" type="text" placeholder="Username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            <p id="username-error" class="error-msg">Username cannot be empty!</p>
            <?php 
                if (!$user_exists && isset($_POST['username'])) {
                    echo "<p id='existing-user' class='server-error' style='display: block'>User does not exist!</p>";
                } 
            ?>
            <input id="password" name="password" type="password" placeholder="Password">
            <p id="password-error" class="error-msg">Password cannot be empty!</p>
            <?php 
                if ($incorrect_password && isset($_POST['password'])) {
                    echo "<p id='incorrect-password' class='server-error' style='display: block'>Incorrect password!</p>";
                } 
            ?>
            <?php
                if (!isset($_SESSION['recovery_time'])) {
                    echo "<button type='submit'>Login</button>
                            <div id='checkbox-container'>
                                <input name='remember-me' id='remember-me' type='checkbox'>
                                <label for='remember-me'>Remember me</label>
                            </div>";
                } else {
                    echo "<p style='color: red;'>Too many failed attempts! Retry in <span id='timer'></span></p>";
                }
            ?>
            <p>Haven't registered yet? Click <a href="register.php">here</a> to register.</p>
            <p>Forgot password? Click <a href="forgot-password.php">here</a>.</p>
        </form>
        <script>
            const form = document.getElementById('login-form');
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const usernameErr = document.getElementById('username-error');
            const passwordErr = document.getElementById('password-error');
            const usernameServerErr = document.getElementById('existing-user');
            const passwordServerErr = document.getElementById('incorrect-password');
            const timer = document.getElementById('timer');
            const checkboxContainer = document.getElementById('checkbox-container');

            <?php if (isset($_SESSION['recovery_time'])): ?>
                const recoveryTime = <?= $_SESSION['recovery_time'] * 1000 ?>;
                function updateTimer() {
                    const now = new Date();
                    const diffMs = recoveryTime - now;

                    if (diffMs <= 0) {
                        location.reload();
                        return;
                    }

                    const totalSeconds = Math.floor(diffMs / 1000);
                    const minutes = Math.floor(totalSeconds / 60);
                    const seconds = totalSeconds % 60;

                    timer.innerText =
                        String(minutes).padStart(2, '0') + ":" +
                        String(seconds).padStart(2, '0');
                }

                updateTimer();
                const interval = setInterval(updateTimer, 1000);
            <?php endif; ?>

            form.addEventListener('submit', (e) => {
                usernameErr.style.display = "none";
                passwordErr.style.display = "none";
                if (usernameServerErr) usernameServerErr.style.display = "none";
                if (passwordServerErr) passwordServerErr.style.display = "none";

                if (!username.value.trim()) {
                    e.preventDefault();
                    usernameErr.style.display = "block";
                    return;
                }
                if (!password.value) {
                    e.preventDefault();
                    passwordErr.style.display = "block";
                    return;
                }
            });
        </script>
    </body>
</html>