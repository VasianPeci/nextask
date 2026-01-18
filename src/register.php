<?php
session_start();
require_once("connection.php");
require_once 'mailer.php';

$user_exists = false;
$email_exists = false;
$team_exists = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username  = trim($_POST["username"]);
    $email     = trim($_POST["email"]);
    $firstName = trim($_POST["first-name"]) ?: null;
    $lastName  = trim($_POST["last-name"]) ?: null;
    $teamId    = $_POST["team"] !== "" ? (int)$_POST["team"] : null;
    $passwordHash = password_hash($_POST["password"], PASSWORD_DEFAULT);

    // check if user with this username already exists
    $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_exists = true;
    }

    $stmt->close();

    // check if user with this email already exists
    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $email_exists = true;
    }

    $stmt->close();

    // necessary actions for setting admin
    if ($username == "admin") {
        if (!$user_exists && !$email_exists) {
            $stmt = $conn->prepare("
                INSERT INTO users (email, username, first_name, last_name, verified, role, password_hash, team_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $verified = 1;
            $role = "ADMIN";
            $team_id = null;

            $stmt->bind_param(
                "ssssissi",
                $email,
                $username,
                $firstName,
                $lastName,
                $verified,
                $role,
                $passwordHash,
                $team_id
            );

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: login.php?success=1");
                exit;
            }
            $stmt->close();
        }
    }

    // check if team with this id exists or not
    $stmt = $conn->prepare("SELECT team_id FROM teams WHERE team_id = ?");
    $stmt->bind_param("i", $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $team_exists = true;
    }

    $stmt->close();

    // user insertion into users
    if (!$user_exists && !$email_exists && $team_exists) {
        $stmt = $conn->prepare("
            INSERT INTO users (email, username, first_name, last_name, password_hash, team_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssi",
            $email,
            $username,
            $firstName,
            $lastName,
            $passwordHash,
            $teamId
        );

        // when successful send verification email to user
        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['firstname'] = $firstName;
            header("Location: sendemail.php");
            exit;
        }
        $stmt->close();
    }
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Register</title>
        <link href="style.css" rel="stylesheet">
    </head>
    <body class="centered-body">
        <h1>Register</h1>
        <form id="register-form" action="" method="POST">
            <input id="first-name" name="first-name" type="text" placeholder="First Name" value="<?php echo htmlspecialchars($_POST['first-name'] ?? ''); ?>">
            <p id="first-name-error" class="error-msg">First name too long or empty!</p>
            <input id="last-name" name="last-name" type="text" placeholder="Last Name" value="<?php echo htmlspecialchars($_POST['last-name'] ?? ''); ?>">
            <p id="last-name-error" class="error-msg">Last name too long or empty!</p>
            <input id="username" name="username" type="text" placeholder="Username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            <p id="username-error" class="error-msg">Username too long or empty!</p>
            <?php 
                if ($user_exists && isset($_POST['username'])) {
                    echo "<p id='existing-user' class='server-error' style='display: block'>Username already exists!</p>";
                } 
            ?>
            <input id="email" name="email" type="text" placeholder="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            <p id="email-error" class="error-msg">Email must be a valid one!</p>
            <?php 
                if ($email_exists && isset($_POST['email'])) {
                    echo "<p id='existing-email' class='server-error' style='display: block'>This email is already registered!</p>";
                }
            ?>
            <input id="team" name="team" type="text" placeholder="Team ID" value="<?php echo htmlspecialchars($_POST['team'] ?? ''); ?>">
            <p id="team-error" class="error-msg">Team ID must be a number!</p>
            <?php 
                if (!$team_exists && isset($_POST['team'])) {
                    echo "<p id='existing-team' class='server-error' style='display: block'>Team does not exist!</p>";
                }
            ?>
            <input id="password" name="password" type="password" placeholder="Password" value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>">
            <p id="password-error" class="error-msg">Password must have a length of 8-20 characters!</p>
            <input id="reentered-password" name="reentered" type="password" placeholder="Re-enter Password" value="<?php echo htmlspecialchars($_POST['reentered'] ?? ''); ?>">
            <p id="reentered-password-error" class="error-msg">Passwords do not match!</p>
            <button type="submit">Register</button>
            <p>Already registered? Click <a href="login.php">here</a> to log in.</p>
        </form>
        <script>
            const form = document.getElementById('register-form');
            const firstName = document.getElementById('first-name');
            const lastName = document.getElementById('last-name');
            const username = document.getElementById('username');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const reenteredPassword = document.getElementById('reentered-password');
            const firstNameErr = document.getElementById('first-name-error');
            const lastNameErr = document.getElementById('last-name-error');
            const usernameErr = document.getElementById('username-error');
            const passwordErr = document.getElementById('password-error');
            const teamErr = document.getElementById('team-error');
            const team = document.getElementById('team');
            const reenteredPasswordErr = document.getElementById('reentered-password-error');
            const emailErr = document.getElementById('email-error');
            const emailServerErr = document.getElementById('existing-email');
            const usernameServerErr = document.getElementById('existing-user');
            const teamServerErr = document.getElementById('existing-team');
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

            form.addEventListener('submit', (e) => {
                firstNameErr.style.display = "none";
                lastNameErr.style.display = "none";
                usernameErr.style.display = "none";
                passwordErr.style.display = "none";
                reenteredPasswordErr.style.display = "none";
                teamErr.style.display = "none";
                emailErr.style.display = "none";
                if (emailServerErr) emailServerErr.style.display = "none";
                if (usernameServerErr) usernameServerErr.style.display = "none";
                if (teamServerErr) teamServerErr.style.display = "none";

                if (!firstName.value.trim() || firstName.value.trim().length >= 20) {
                    e.preventDefault();
                    firstNameErr.style.display = "block";
                    return;
                }
                if (!lastName.value.trim() || lastName.value.trim().length >= 20) {
                    e.preventDefault();
                    lastNameErr.style.display = "block";
                    return;
                }
                if (!username.value.trim() || username.value.trim().length >= 20) {
                    e.preventDefault();
                    usernameErr.style.display = "block";
                    return;
                }
                if (!email.value.trim() || !emailRegex.test(email.value)) {
                    e.preventDefault();
                    emailErr.style.display = "block";
                    return;
                }
                if (username.value !== "admin" && (!team.value.trim() || isNaN(Number(team.value.trim())))) {
                    e.preventDefault();
                    teamErr.style.display = "block";
                    return;
                }
                if (password.value.length > 20 || password.value.length < 8) {
                    e.preventDefault();
                    passwordErr.style.display = "block";
                    return;
                }
                if (reenteredPassword.value !== password.value) {
                    e.preventDefault();
                    reenteredPasswordErr.style.display = "block";
                    return;
                }
            });
        </script>
    </body>
</html>