<?php
require_once("connection.php");
require_once("authorization.php");
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $conn->prepare("SELECT team_id FROM teams WHERE team_id = 1");
$stmt->execute();
$result = $stmt->get_result();
if (!$result->fetch_assoc()) {
    $stmt = $conn->prepare("INSERT INTO teams (team_name, owner_id) VALUES ('DEFAULT', 1)");
    $stmt->execute();
    $stmt->close();
}

$user_error = false;
$team_error = false;

// get all users and store in array
$users = [];
$stmt = $conn->prepare("SELECT username, role, verified FROM users");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// get all teams and store in array
$teams = [];
$stmt = $conn->prepare("SELECT t.team_id, t.team_name, u.username FROM teams t JOIN users u ON (t.owner_id = u.user_id)");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $teams[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['log-out'])) {
        header("Location: logout.php");
        exit;
    }

    // change role
    if (isset($_POST['role'])) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE username = ?");
        $stmt->bind_param("ss", $_POST['role'], $_POST['username']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // change project owner of team
    if (isset($_POST['owner'])) {
        $owner = trim($_POST['owner']);
        
        $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $owner);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_error = !($result->num_rows > 0);
        if ($row = $result->fetch_assoc()) {
            $role = $row['role'];
            $user_error = $role != 'PROJECT_OWNER';
        }
        $stmt->close();

        if (!$user_error) {
            $user_id = $row['user_id'];
            $stmt = $conn->prepare("SELECT username FROM teams t JOIN users u ON (t.owner_id = u.user_id) WHERE username = ?");
            $stmt->bind_param("s", $owner);
            $stmt->execute();
            $result = $stmt->get_result();
            $team_error = $result->num_rows > 0;
            $stmt->close();

            if (!$team_error) {
                $stmt = $conn->prepare("UPDATE teams SET owner_id = ? WHERE team_id = ?");
                $stmt->bind_param("is", $user_id, $_POST['username']);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }

    // add new team
    if (isset($_POST['owner_username'])) {
        $owner = trim($_POST['owner_username']);
        
        $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $owner);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_error = !($result->num_rows > 0);
        if ($row = $result->fetch_assoc()) {
            $role = $row['role'];
            $user_error = $role != 'PROJECT_OWNER';
        }
        $stmt->close();

        if (!$user_error) {
            $user_id = $row['user_id'];
            $stmt = $conn->prepare("SELECT username FROM teams t JOIN users u ON (t.owner_id = u.user_id) WHERE username = ?");
            $stmt->bind_param("s", $owner);
            $stmt->execute();
            $result = $stmt->get_result();
            $team_error = $result->num_rows > 0;
            $stmt->close();

            if (!$team_error) {
                $stmt = $conn->prepare("INSERT INTO teams (team_name, owner_id) VALUES (?, ?)");
                $stmt->bind_param("si", $_POST['team_name'], $user_id);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("SELECT team_id FROM teams WHERE owner_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $team_id = $row['team_id'];
                }
                $stmt->close();
                $stmt = $conn->prepare("UPDATE users SET team_id = ? WHERE user_id = ?");
                $stmt->bind_param("ii", $team_id, $user_id);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }
}

?>
<html>
    <head>
        <meta charset="utf-8">
        <title>Admin</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <?php
            if ($user_error) {
                echo "<script>alert('User does not exist or is not a project owner!')</script>";
                $user_error = false;
            }
            if ($team_error) {
                echo "<script>alert('User is already a project owner!')</script>";
                $team_error = false;
            }
        ?>
        <nav id="admin-nav">
            <ul>
                <li class="active" id="users-li"><button id="users-btn">Users</button></li>
                <li id="teams-li"><button id="teams-btn">Teams</button></li>
            </ul>
            <form id="admin-logout" action="" method='post'>
                <input type="text" name="log-out" style="display: none;">
                <button type="submit">Log Out</button>
            </form>
        </nav>
        <main id="users"></main>
        <main id="teams" style="display: none;">
            <form action="" method="post" id="new-team" class="team-data-field">
                <button type='button' class='change' id="new-team-btn">Add New Team</button>
                <input id="new-team-name" placeholder="Team Name" type='text' name='team_name' style='display: none;'>
                <input id="new-team-owner" placeholder="Owner" type='text' name='owner_username' style='display: none;'>
                <button type='submit' style="display: none;">Confirm</button>
                <button type='button' class='cancel' style="display: none;">Cancel</button>
            </form>
        </main>
        <script>
            const usersSection = document.getElementById("users");
            const teamsSection = document.getElementById("teams");
            const usersButton = document.getElementById("users-btn");
            const teamsButton = document.getElementById("teams-btn");
            const usersListItem = document.getElementById("users-li");
            const teamsListItem = document.getElementById("teams-li");

            teamsButton.addEventListener("click", () => {
                usersListItem.classList.remove("active");
                teamsListItem.classList.add("active");
                usersSection.style.display = "none";
                teamsSection.style.display = "block";
            });

            usersButton.addEventListener("click", () => {
                usersListItem.classList.add("active");
                teamsListItem.classList.remove("active");
                usersSection.style.display = "block";
                teamsSection.style.display = "none";
            });
            
            const users = <?= json_encode($users, JSON_UNESCAPED_UNICODE); ?>;
            for (const user of users) {
                usersSection.innerHTML += `
                    <form action='' method='post' class='data-field'>
                        <p>${user.username}</p>
                        <p>${user.role}</p>
                        ${user.verified === 0 ? `<p style='color: red;'>${user.verified === 1 ? "" : "Unverified"}</p>` : ""}
                        ${user.verified === 1 ? "<button type='button' class='change'>Change</button>" : ""}
                        <select name='role' style='display: none;'>
                            <option value='USER' ${user.role === 'USER' ? 'selected' : ''}>USER</option>
                            <option value='PROJECT_MANAGER' ${user.role === 'PROJECT_MANAGER' ? 'selected' : ''}>PROJECT MANAGER</option>
                            <option value='PROJECT_OWNER' ${user.role === 'PROJECT_OWNER' ? 'selected' : ''}>PROJECT OWNER</option>
                        </select>
                        <input type='text' name='username' value='${user.username}' style='display: none;'>
                        <button type='submit'>Confirm</button>
                        <button type='button' class='cancel'>Cancel</button>
                    </form>
                `;
            }

            const teams = <?= json_encode($teams, JSON_UNESCAPED_UNICODE); ?>;
            for (const team of teams) {
                teamsSection.innerHTML += `
                    <form action='' method='post' class='data-field'>
                        <p>${team.team_name}</p>
                        <p>Owner: ${team.username}</p>
                        <button type='button' class='change'>Change Owner</button>
                        <p>Team ID: ${team.team_id}</p>
                        <input class='team-input' type='text' name='owner' style='display: none;'>
                        <input type='text' name='username' value='${team.team_id}' style='display: none;'>
                        <button type='submit'>Confirm</button>
                        <button type='button' class='cancel'>Cancel</button>
                    </form>
                `;
            }

            document.querySelectorAll(".data-field").forEach(form => {
                const changeBtn = form.querySelector(".change");
                const confirmBtn = form.querySelector("button[type='submit']");
                const cancelBtn = form.querySelector(".cancel");
                const input = form.querySelector("select, .team-input");

                changeBtn.addEventListener("click", (e) => {
                    input.style.display = "block";
                    confirmBtn.style.display = "block";
                    cancelBtn.style.display = "block";
                    input.focus();
                    e.currentTarget.style.display = "none";
                });

                cancelBtn.addEventListener("click", () => {
                    input.value = "";
                    input.style.display = "none";
                    confirmBtn.style.display = "none";
                    cancelBtn.style.display = "none";
                    changeBtn.style.display = "block";
                });
            });

            const newTeam = document.getElementById("new-team");
            const newTeamButton = document.getElementById("new-team-btn");
            const newTeamConfirm = document.querySelector("#new-team button[type='submit']");
            const newTeamCancel = document.querySelector("#new-team .cancel");
            const newTeamNameInput = document.getElementById("new-team-name");
            const newTeamOwnerInput = document.getElementById("new-team-owner");

            newTeamButton.addEventListener("click", () => {
                newTeamConfirm.style.display = "block";
                newTeamCancel.style.display = "block";
                newTeamNameInput.style.display = "block";
                newTeamOwnerInput.style.display = "block";
                newTeamButton.style.display = "none";
            });

            newTeamCancel.addEventListener("click", () => {
                newTeamConfirm.style.display = "none";
                newTeamCancel.style.display = "none";
                newTeamNameInput.style.display = "none";
                newTeamOwnerInput.style.display = "none";
                newTeamButton.style.display = "block";
                newTeamNameInput.value = "";
                newTeamOwnerInput.value = "";
            });

            newTeam.addEventListener("submit", (e) => {
                if (!newTeamNameInput.value.trim() || newTeamNameInput.value.trim().length >= 20) {
                    e.preventDefault();
                    alert("Invalid Team Name!");
                    return;
                }
                if (!newTeamOwnerInput.value.trim()) {
                    e.preventDefault();
                    alert("Invalid Owner Username!");
                    return;
                }
            });
        </script>
    </body>
</html>