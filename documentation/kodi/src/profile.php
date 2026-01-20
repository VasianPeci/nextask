<?php
require_once("connection.php");
require_once("authorization.php");
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$invalid_email = false;
$invalid_username = false;
$invalid_team = false;

$tasks = [];
$projects = [];
$contributor_tasks = [];

$manager_error = false;
$user_error = false;
$project_error = false;

// fetch user data
$stmt = $conn->prepare("SELECT first_name, last_name, username, email, team_id, task_id, verified, role FROM users WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$first_name = $user['first_name'];
$last_name = $user['last_name'];
$username = $user['username'];
$email = $user['email'];
$team_id = $user['team_id'];
$task_id = $user['task_id'];
$verified = $user['verified'];
$role = $user['role'];
$project = null;

$_SESSION['username'] = $username;
$_SESSION['firstname'] = $first_name;
$_SESSION['email'] = $email;

$stmt->close();

// set team name
$stmt = $conn->prepare("SELECT team_name FROM teams WHERE team_id = ?");
$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();
$team_result = $result->fetch_assoc();

$team = $team_result['team_name'] ?? null;

$stmt->close();

if ($role == 'PROJECT_OWNER') {
    // get all projects and store in array
    if ($team) {
        $stmt = $conn->prepare("SELECT p.project_id, p.project_name, p.description, p.created_at, p.manager_id, u.username FROM projects p JOIN users u ON (p.manager_id = u.user_id) WHERE p.team_id = ?");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
    }
} else if ($role == 'PROJECT_MANAGER') {
    // find project
    $stmt = $conn->prepare("SELECT project_id, project_name, description FROM projects WHERE manager_id = ?");
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $project_result = $result->fetch_assoc();
    $stmt->close();

    $project = $project_result ?? null;
    $project_name = $project['project_name'] ?? null;
    $project_description = $project['description'] ?? null;

    // determine project progress in percentage
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM tasks WHERE project_id = ?");
    $stmt->bind_param("i", $project["project_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_tasks = $result->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS completed FROM tasks WHERE project_id = ? AND status = 'COMPLETED'");
    $stmt->bind_param("i", $project["project_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $completed_tasks = $result->fetch_assoc()['completed'];
    $stmt->close();

    if ($total_tasks != 0) {
        $project_progress = floor(100 * $completed_tasks / $total_tasks);
    } else {
        $project_progress = null;
    }
    if ($project) {
        // get all tasks and store in array
        $stmt = $conn->prepare("SELECT t.task_id, t.title, t.description, t.deadline, t.contributor_id, u.username FROM tasks t JOIN users u ON (t.contributor_id = u.user_id) WHERE t.project_id = ?");
        $stmt->bind_param("i", $project['project_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    } 
} else {
    // firstly update status of tasks if overdue
    $stmt = $conn->prepare("UPDATE tasks SET status = 'OVERDUE' WHERE status = 'IN_PROGRESS' AND deadline < NOW()");
    $stmt->execute();
    $stmt->close();

    // get all tasks of contributor and store in array
    $stmt = $conn->prepare("SELECT task_id, title, description, deadline, status, full_code FROM tasks WHERE contributor_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    while ($row = $result->fetch_assoc()) {
        $contributor_tasks[] = $row;
    }
}

// data from POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // edit first name
    if (isset($_POST['first-name'])) {
        if ($_POST['first-name'] == $first_name) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET first_name = ? WHERE user_id = ?");
        $stmt->bind_param("si", $_POST['first-name'], $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // edit last name
    if (isset($_POST['last-name'])) {
        if ($_POST['last-name'] == $last_name) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET last_name = ? WHERE user_id = ?");
        $stmt->bind_param("si", $_POST['last-name'], $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // edit username
    if (isset($_POST['username'])) {
        if ($_POST['username'] == $username) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->bind_param("s", $_POST['username']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $invalid_username = true;
        }
        $stmt->close();

        if (!$invalid_username) {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE user_id = ?");
            $stmt->bind_param("si", $_POST['username'], $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // edit email
    if (isset($_POST['email'])) {
        if ($_POST['email'] == $email) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $_POST['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $invalid_email = true;
        }
        $stmt->close();

        if (!$invalid_email) {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->bind_param("si", $_POST['email'], $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // edit team
    if (isset($_POST['team'])) {
        if ($_POST['team'] == $team) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE team_id = ?");
        $stmt->bind_param("s", $_POST['team']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            $invalid_team = true;
        }
        $stmt->close();

        if (!$invalid_team) {
            $stmt = $conn->prepare("UPDATE users SET team_id = ? WHERE user_id = ?");
            $stmt->bind_param("si", $_POST['team'], $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // logout
    if (isset($_POST['logout'])) {
        header("Location: logout.php");
        exit;
    }

    // send code
    if (isset($_POST['sendcode'])) {
        header("Location: sendemail.php");
        exit;
    }

    // delete account
    if (isset($_POST['delete'])) {
        $stmt = $conn->prepare("SELECT project_id FROM projects WHERE manager_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc()['project_id'];
        $stmt->close();

        if ($result) {
            // delete all tasks related to the project
            $stmt = $conn->prepare("DELETE FROM tasks WHERE project_id = ?");
            $stmt->bind_param("i", $result);
            $stmt->execute();
            $stmt->close();

            // project deletion
            $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
            $stmt->bind_param("i", $result);
            $stmt->execute();
            $stmt->close();
        } 

        // delete sendgrid_logs
        $stmt = $conn->prepare("DELETE FROM sendgrid_logs WHERE user_id = ?");
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        header("Location: logout.php");

        // account deletion
        $stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->close();
        exit;
    }

    // rename team
    if (isset($_POST['team_name'])) {
        $stmt = $conn->prepare("UPDATE teams SET team_name = ? WHERE team_id = ?");
        $stmt->bind_param("si", $_POST['team_name'], $team_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // delete team
    if (isset($_POST['team-delete'])) {
        // delete all tasks related to the team
        $stmt = $conn->prepare("DELETE FROM tasks WHERE team_id = ?");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $stmt->close();

        // delete all projects related to the team
        $stmt = $conn->prepare("DELETE FROM projects WHERE team_id = ?");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $stmt->close();

        // team deletion
        $stmt = $conn->prepare("DELETE FROM teams WHERE team_id = ?");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // rename project
    if (isset($_POST['project_name'])) {
        $stmt = $conn->prepare("UPDATE projects SET project_name = ? WHERE project_id = ?");
        $stmt->bind_param("si", $_POST['project_name'], $project['project_id']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // new description for project
    if (isset($_POST['project_description'])) {
        $stmt = $conn->prepare("UPDATE projects SET description = ? WHERE project_id = ?");
        $stmt->bind_param("si", $_POST['project_description'], $project['project_id']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // delete project
    if (isset($_POST['project-delete'])) {
        // delete all tasks related to the project
        $stmt = $conn->prepare("DELETE FROM tasks WHERE project_id = ?");
        $stmt->bind_param("i", $project['project_id']);
        $stmt->execute();
        $stmt->close();

        // project deletion
        $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project['project_id']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // add new project
    if (isset($_POST['manager_username'])) {
        $manager = trim($_POST['manager_username']);
        
        $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $manager);
        $stmt->execute();
        $result = $stmt->get_result();
        $manager_error = !($result->num_rows > 0);
        if ($row = $result->fetch_assoc()) {
            $role = $row['role'];
            $manager_error = $role != 'PROJECT_MANAGER';
        }
        $stmt->close();

        if (!$manager_error) {
            $user_id = $row['user_id'];
            $stmt = $conn->prepare("SELECT username FROM projects p JOIN users u ON (p.manager_id = u.user_id) WHERE username = ?");
            $stmt->bind_param("s", $manager);
            $stmt->execute();
            $result = $stmt->get_result();
            $project_error = $result->num_rows > 0;
            $stmt->close();

            if (!$project_error) {
                $stmt = $conn->prepare("INSERT INTO projects (project_name, description, manager_id, team_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $_POST['new-project_name'], $_POST['new-project_description'], $user_id, $team_id);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("SELECT project_id FROM projects WHERE manager_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $project_id = $row['project_id'];
                }
                $stmt->close();
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }

    // change project data
    if (isset($_POST['manager'])) {
        $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $_POST['manager']);
        $stmt->execute();
        $result = $stmt->get_result();
        $manager_error = !($result->num_rows > 0);
        if ($row = $result->fetch_assoc()) {
            $role = $row['role'];
            $manager_error = $role != 'PROJECT_MANAGER';
        }
        $stmt->close();

        if (!$manager_error) {
            $user_id = $row['user_id'];
            $stmt = $conn->prepare("SELECT username FROM projects p JOIN users u ON (p.manager_id = u.user_id) WHERE u.username = ?");
            $stmt->bind_param("s", $_POST['manager']);
            $stmt->execute();
            $result = $stmt->get_result();
            $project_error = $result->num_rows > 0;
            if ($project_error) {
                $row = $result->fetch_assoc();
                if ($_POST['manager'] == $row['username']) {
                    $project_error = false;
                }
            }
            $stmt->close();

            if (!$project_error) {
                $stmt = $conn->prepare("UPDATE projects SET project_name = ?, description = ?, manager_id = ? WHERE project_id = ?");
                $stmt->bind_param("ssii", $_POST['project-name'], $_POST['project-description'], $_POST['manager_id'], $_POST['project_id']);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("SELECT project_id FROM projects WHERE manager_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $project_id = $row['project_id'];
                }
                $stmt->close();
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }

    // add new task
    if (isset($_POST['contributor_username'])) {
        $contributor = trim($_POST['contributor_username']);
        
        $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $contributor);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_error = !($result->num_rows > 0);
        if ($row = $result->fetch_assoc()) {
            $role = $row['role'];
            $user_error = $role != 'USER';
        }
        $stmt->close();

        if (!$user_error) {
            $user_id = $row['user_id'];

            $stmt = $conn->prepare("INSERT INTO tasks (title, description, deadline, contributor_id, project_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $_POST['task_name'], $_POST['task_description'], $_POST['task_deadline'], $user_id, $project['project_id']);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("SELECT task_id FROM tasks WHERE contributor_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $task_id = $row['task_id'];
            }
            $stmt->close();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // delete task
    if (isset($_POST['action'])) {
        $stmt = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $_POST['action']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // change task data
    if (isset($_POST['contributor'])) {
        $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $_POST['contributor']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_error = !($result->num_rows > 0);
        if ($row = $result->fetch_assoc()) {
            $role = $row['role'];
            $user_error = $role != 'USER';
        }
        $stmt->close();

        if (!$user_error) {
            $user_id = $row['user_id'];

            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, deadline = ?, contributor_id = ? WHERE task_id = ?");
            $stmt->bind_param("sssii", $_POST['task-name'], $_POST['task-description'], $_POST['task-deadline'], $user_id, $_POST['task_id']);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("SELECT task_id FROM tasks WHERE contributor_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $project_id = $row['task_id'];
            }
            $stmt->close();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // code submission
    if (isset($_POST['contributor_code'])) {
        $stmt = $conn->prepare("UPDATE tasks SET full_code = ?, status = 'COMPLETED' WHERE task_id = ?");
        $stmt->bind_param("si", $_POST['contributor_code'], $_POST['task_id']);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>
<html>
    <head>
        <meta charset="utf-8">
        <title>Profile</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="centered-body">
        <?php
            if ($manager_error) {
                echo "<script>alert('User does not exist or is not a project manager!')</script>";
                $manager_error = false;
            }
            if ($user_error) {
                echo "<script>alert('User does not exist or is not a contributor!')</script>";
                $user_error = false;
            }
            if ($project_error) {
                echo "<script>alert('User is already a project manager!')</script>";
                $project_error = false;
            }
        ?>
        <?php if ($verified == 1): ?>
            <?php if ($invalid_username) {
                echo "<script>alert('This username exists!')</script>";
                $invalid_username = false;
             } ?>
            <?php if ($invalid_email) {
                echo "<script>alert('This email exists!')</script>";
                $invalid_email = false;
             } ?>
            <?php if ($invalid_team) {
                echo "<script>alert('Team with this id does not exist!')</script>";
                $invalid_team = false;
             } ?>
            <nav>
                <ul>
                    <li class="active" id="profile-item"><button id="profile-btn">Profile</button></li>
                    <?php if ($role == 'PROJECT_OWNER'): ?>
                    <li id="working-section-item"><button id="working-section-btn">Team</button></li>
                    <?php elseif ($role == 'PROJECT_MANAGER'): ?>
                    <li id="working-section-item"><button id="working-section-btn">Project</button></li>
                    <?php else: ?>
                    <li id="working-section-item"><button id="working-section-btn">Tasks</button></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <main id="profile">
                <div id="settings">
                    <h2><?php echo "$username"; ?></h2>
                    <button id="account-btn" class="active">Account</button>
                    <button id="delete-btn">Delete Account</button>
                    <button id="logout-btn">Log Out</button>
                </div>
                <div id="account">
                    <form id="first-name-form" action="" method="post" class="data-field">
                        <h3>First Name:</h3>
                        <p><?php echo "$first_name"; ?></p>
                        <button type="button" class="change">Change</button>
                        <input id="first-name-input" name="first-name" type="text" style="display: none;">
                        <button type="submit">Confirm</button>
                        <button type="button" class="cancel">Cancel</button>
                    </form>
                    <form id="last-name-form" action="" method="post" class="data-field">
                        <h3>Last Name:</h3>
                        <p><?php echo "$last_name"; ?></p>
                        <button type="button" class="change">Change</button>
                        <input id="last-name-input" name="last-name" type="text" style="display: none;">
                        <button type="submit">Confirm</button>
                        <button type="button" class="cancel">Cancel</button>
                    </form>
                    <form id="username-form" action="" method="post" class="data-field">
                        <h3>Username:</h3>
                        <p><?php echo "$username"; ?></p>
                        <button type="button" class="change">Change</button>
                        <input id="username-input" name="username" type="text" style="display: none;">
                        <button type="submit">Confirm</button>
                        <button type="button" class="cancel">Cancel</button>
                    </form>
                    <form id="email-form" action="" method="post" class="data-field">
                        <h3>Email:</h3>
                        <p><?php echo "$email"; ?></p>
                        <button type="button" class="change">Change</button>
                        <input id="email-input" name="email" type="text" style="display: none;">
                        <button type="submit">Confirm</button>
                        <button type="button" class="cancel">Cancel</button>
                    </form>
                    <form id="team-form" action="" method="post" class="data-field">
                        <h3>Team:</h3>
                        <p><?= $team ?? 'None' ?></p>
                        <button type="button" class="change">Change</button>
                        <input id="team-input" name="team" type="text" style="display: none;">
                        <button type="submit">Confirm</button>
                        <button type="button" class="cancel">Cancel</button>
                    </form>
                </div>
                <form action="" method="post" id="delete-account" style="display: none;">
                    <h3>Are you sure you want to delete your account?</h3>
                    <div>
                        <button type="submit">Yes</button>
                        <input name="delete" style="display: none;">
                        <button id="delete-cancel" type="button">No</button>
                    </div>
                </form>
                <form action="" method="post" id="log-out" style="display: none;">
                    <h3>Are you sure you want to log out?</h3>
                    <div>
                        <button type="submit">Yes</button>
                        <input name="logout" style="display: none;">
                        <button id="logout-cancel" type="button">No</button>
                    </div>
                </form>
            </main>
            <main id="working-section" style="display: none;">
                <?php if ($role == 'PROJECT_OWNER'): ?>
                    <div id="settings">
                        <?php if ($team): ?>
                        <h2>Team: <?php echo "$team"; ?></h2>
                        <button id="change-name-btn">Change name</button>
                        <form action="" method="post" id="team-name-form" style="display: none;" class="data-field">
                            <input id="team-name-input" type="text" name="team_name">
                            <button style="display: inline-block; color: rgb(45, 203, 45);" type="submit">Confirm</button>
                            <button style="display: inline-block; color: red;" type="button" class="cancel">Cancel</button>
                        </form>
                        <button id="delete-team-btn">Delete Team</button>
                        <form action="" method="post" id="delete-team-form" style="display: none;" class="data-field">
                            <p>Are you sure?</p>
                            <input type="text" style="display: none;" name="team-delete">
                            <button style="display: inline-block; color: rgb(45, 203, 45);" type="submit">Yes</button>
                            <button style="display: inline-block; color: red;" type="button" class="cancel">No</button>
                        </form>
                        <form action="" method="post" id="new-project" class="project-data-field">
                            <button type='button' class='change' id="new-project-btn">Add New Project</button>
                            <input id="new-project-name" placeholder="Project Name" type='text' name='new-project_name' style='display: none;'>
                            <input id="new-project-manager" placeholder="Manager" type='text' name='manager_username' style='display: none;'>
                            <input id="new-project-description" placeholder="Description" type='text' name='new-project_description' style='display: none;'>
                            <button type='submit' style="display: none; color: rgb(45, 203, 45); text-align: center;">Confirm</button>
                            <button type='button' class='cancel' style="display: none; color: red; text-align: center;">Cancel</button>
                        </form>
                        <?php else: ?>
                        <h2>No Team</h2>
                        <?php endif; ?>
                    </div>
                    <?php if ($team): ?>
                    <main id="projects">
                    </main>
                    <?php endif; ?>
                <?php elseif ($role == 'PROJECT_MANAGER'): ?>
                    <div id="settings" class="<?= ($project_progress ?? 0) == 100 ? 'project-completed' : '' ?>">
                        <?php if ($project): ?>
                        <h2>Project: <?php if ($project_name) {
                            echo "$project_name";
                        } ?></h2>
                        <h2>Description: <?php if ($project_description) {
                            echo "$project_description";
                        } ?></h2></h2>
                        <button id="change-name-btn">Change name</button>
                        <form action="" method="post" id="project-name-form" style="display: none;" class="data-field">
                            <input id="project-name-input" type="text" name="project_name">
                            <button style="display: inline-block; color: rgb(45, 203, 45);" type="submit">Confirm</button>
                            <button style="display: inline-block; color: red;" type="button" class="cancel">Cancel</button>
                        </form>
                        <button id="change-description-btn">Change description</button>
                        <form action="" method="post" id="project-description-form" style="display: none;" class="data-field">
                            <input id="project-description-input" type="text" name="project_description">
                            <button style="display: inline-block; color: rgb(45, 203, 45);" type="submit">Confirm</button>
                            <button style="display: inline-block; color: red;" type="button" class="cancel">Cancel</button>
                        </form>
                        <button id="delete-project-btn">Delete Project</button>
                        <form action="" method="post" id="delete-project-form" style="display: none;" class="data-field">
                            <p>Are you sure?</p>
                            <input type="text" style="display: none;" name="project-delete">
                            <button style="display: inline-block; color: rgb(45, 203, 45);" type="submit">Yes</button>
                            <button style="display: inline-block; color: red;" type="button" class="cancel">No</button>
                        </form>
                        <?php if ($project_progress && ($project_progress ?? 0) < 100): ?>
                        <div id="progress-container">
                            <div 
                                id="progress-bar" 
                                class="<?= $project_progress == 100 ? 'completed' : 'in-progress' ?>" 
                                style="width: <?= $project_progress ?>%">
                                <?= $project_progress ?>%
                            </div>
                        </div>
                        <?php endif; ?>
                        <form action="" method="post" id="new-task" class="task-data-field">
                            <button type='button' class='change' id="new-task-btn">Add New Task</button>
                            <input id="new-task-name" placeholder="Task Name" type='text' name='task_name' style='display: none;'>
                            <input id="new-task-contributor" placeholder="Contributor" type='text' name='contributor_username' style='display: none;'>
                            <input id="new-task-description" placeholder="Description" type='text' name='task_description' style='display: none;'>
                            <input id="new-task-deadline" type='date' value='<?= date('Y-m-d', strtotime('+1 day')) ?>' name='task_deadline' style='display: none;'>
                            <button type='submit' style="display: none; color: rgb(45, 203, 45); text-align: center;">Confirm</button>
                            <button type='button' class='cancel' style="display: none; color: red; text-align: center;">Cancel</button>
                        </form>
                        <?php else: ?>
                        <h2>No Project</h2>
                        <?php endif; ?>
                    </div>
                    <?php if ($project): ?>
                    <main id="tasks">
                    </main>
                    <?php endif; ?>
                <?php else: ?>
                    <div id="settings">
                        <?php if ($contributor_tasks): ?>
                        <?php else: ?>
                        <h2>No Tasks</h2>
                        <?php endif; ?>
                    </div>
                    <?php if ($contributor_tasks): ?>
                    <main id="contributor_tasks">
                    </main>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        <?php else: ?>
            <form action="" method="post" id="nonverified-form">
                <div id="centered-text">You are not verified yet!</div>
                <input type="text" style="display: none;" name="logout">
                <button id="nonverified-log-out-btn" type="submit">Log Out</button>
            </form>
            <form action="" method="post" id="nonverified-form">
                <div id="centered-text">Send Verification Code!</div>
                <input type="text" style="display: none;" name="sendcode">
                <button id="nonverified-log-out-btn" type="submit">Send</button>
            </form>
        <?php endif; ?>
        <script>
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

            const accountBtn = document.getElementById('account-btn');
            const deleteBtn = document.getElementById('delete-btn');
            const logoutBtn = document.getElementById('logout-btn');
            const account = document.getElementById('account');
            const deleteAccount = document.getElementById('delete-account');
            const logout = document.getElementById('log-out');
            const logoutCancelBtn = document.getElementById('logout-cancel');
            const deleteCancelBtn = document.getElementById('delete-cancel');
            const profileBtn = document.getElementById('profile-btn');
            const workingSectionBtn = document.getElementById('working-section-btn');
            const profileItem = document.getElementById('profile-item');
            const workingSectionItem = document.getElementById('working-section-item');
            const profile = document.getElementById('profile');
            const workingSection = document.getElementById('working-section');
            const changeNameBtn = document.getElementById('change-name-btn');
            const changeDescriptionBtn = document.getElementById('change-description-btn');
            const deleteTeamBtn = document.getElementById('delete-team-btn');
            const deleteProjectBtn = document.getElementById('delete-project-btn');
            const teamNameForm = document.getElementById('team-name-form');
            const projectNameForm = document.getElementById('project-name-form');
            const projectDescriptionForm = document.getElementById('project-description-form');
            const deleteTeamForm = document.getElementById('delete-team-form');
            const deleteProjectForm = document.getElementById('delete-project-form');
            const teamNameCancel = document.querySelector('#team-name-form .cancel');
            const projectNameCancel = document.querySelector('#project-name-form .cancel');
            const projectDescriptionCancel = document.querySelector('#project-description-form .cancel');
            const deleteTeamCancel = document.querySelector('#delete-team-form .cancel');
            const deleteProjectCancel = document.querySelector('#delete-project-form .cancel');
            const teamNameInput = document.getElementById('team-name-input');
            const projectNameInput = document.getElementById('project-name-input');
            const projectDescriptionInput = document.getElementById('project-description-input');
            const projectsSection = document.getElementById("projects");
            const tasksSection = document.getElementById("tasks");
            const contributorTasksSection = document.getElementById("contributor_tasks");

            profileBtn.addEventListener("click", () => {
                profileItem.classList.add("active");
                workingSectionItem.classList.remove("active");
                profile.style.display = "grid";
                workingSection.style.display = "none";
            });

            accountBtn.addEventListener("click", () => {
                account.style.display = "grid";
                deleteAccount.style.display = "none";
                logout.style.display = "none";
                deleteBtn.classList.remove('active');
                logoutBtn.classList.remove('active');
                accountBtn.classList.add('active');
            });

            deleteBtn.addEventListener("click", () => {
                account.style.display = "none";
                deleteAccount.style.display = "block";
                logout.style.display = "none";
                deleteBtn.classList.add('active');
                logoutBtn.classList.remove('active');
                accountBtn.classList.remove('active');
            });

            logoutBtn.addEventListener("click", () => {
                account.style.display = "none";
                deleteAccount.style.display = "none";
                logout.style.display = "block";
                deleteBtn.classList.remove('active');
                logoutBtn.classList.add('active');
                accountBtn.classList.remove('active');
            });

            logoutCancelBtn.addEventListener("click", () => {
                account.style.display = "grid";
                deleteAccount.style.display = "none";
                logout.style.display = "none";
                deleteBtn.classList.remove('active');
                logoutBtn.classList.remove('active');
                accountBtn.classList.add('active');
            });

            const projects = <?= json_encode($projects, JSON_UNESCAPED_UNICODE); ?>;
            for (const project of projects) {
                projectsSection.innerHTML += `
                    <form action='' method='post' class='data-field project-field'>
                        <p><span style="font-weight: bold;">Project:</span> ${project.project_name}</p>
                        <p><span style="font-weight: bold;">Manager:</span> ${project.username}</p>
                        <p>Created at: ${project.created_at}</p>
                        <h3>Description:</h3>
                        <p>${project.description}</p>
                        <button type='button' class='change'>Change</button>
                        <div class='project-inputs'>
                        <label style='display: none;'>Project Manager:
                        <input id='change-project-manager' class='project-input' type='text' value='${project.username}' name='manager' style='display: none;'>
                        </label>
                        <label style='display: none;'>Project Name:
                        <input id='change-project-name' class='project-input' type='text' value='${project.project_name}' name='project-name' style='display: none;'>
                        </label>
                        <label style='display: none;'>Project Description:
                        <input id='change-project-description' class='project-input' type='text' value='${project.description}' name='project-description' style='display: none;'>
                        </label>
                        </div>
                        <input class='project-id' type='text' name='project_id' value='${project.project_id}' style='display: none;'>
                        <input class='project-manager' type='text' name='manager_id' value='${project.manager_id}' style='display: none;'>
                        <button type='submit'>Confirm</button>
                        <button type='button' class='cancel'>Cancel</button>
                    </form>
                `;
            }

            const tasks = <?= json_encode($tasks, JSON_UNESCAPED_UNICODE); ?>;
            for (const task of tasks) {
                tasksSection.innerHTML += `
                    <form action='' method='post' class='data-field task-field'>
                        <p><span style="font-weight: bold;">Task:</span> ${task.title}</p>
                        <p><span style="font-weight: bold;">Contributor:</span> ${task.username}</p>
                        <p>Deadline: ${task.deadline}</p>
                        <h3>Description:</h3>
                        <p>${task.description}</p>
                        <button type='button' class='change'>Change</button>
                        <div class='task-inputs'>
                            <label style='display: none;'>Task Contributor:
                            <input id='change-task-contributor' class='task-input' type='text' value='${task.username}' name='contributor' style='display: none;'>
                            </label>
                            <label style='display: none;'>Task Name:
                            <input id='change-task-name' class='task-input' type='text' value='${task.title}' name='task-name' style='display: none;'>
                            </label>
                            <label style='display: none;'>Task Description:
                            <input id='change-task-description' class='task-input' type='text' value='${task.description}' name='task-description' style='display: none;'>
                            </label>
                            <label style='display: none;'>Task Deadline:
                            <input id='change-task-deadline' class='task-input' type='date' value='${task.deadline.split(" ")[0]}' name='task-deadline' style='display: none;'>
                            </label>
                        </div>
                        <input class='task-id' type='text' name='task_id' value='${task.task_id}' style='display: none;'>
                        <input class='task-contributor' type='text' name='contributor_id' value='${task.contributor_id}' style='display: none;'>
                        <button type='submit'>Confirm</button>
                        <button type='submit' name='action' value='${task.task_id}' class='delete-task-btn' style='display: inline-block; color: red;'>Delete</button>
                        <button type='button' class='cancel'>Cancel</button>
                    </form>
                `;
            }

            const contributor_tasks = <?= json_encode($contributor_tasks, JSON_UNESCAPED_UNICODE); ?>;
            for (const task of contributor_tasks) {
                contributorTasksSection.innerHTML += `
                    <form action='' method='post' class='data-field task-field ${task.status === "COMPLETED" ? 'completed' : task.status === "OVERDUE" ? 'overdue' : ''}'>
                        <p><span style="font-weight: bold;">Task:</span> ${task.title}</p>
                        <p><span style="font-weight: bold;">Status:</span> ${task.status}</p>
                        <p>Deadline: ${task.deadline}</p>
                        <h3>Description:</h3>
                        <p>${task.description}</p>
                        ${task.status === 'OVERDUE' ? '' : (task.status === 'COMPLETED' ? "<button type='button' class='change'>Resubmit Code</button>" : "<button type='button' class='change'>Submit Code</button>")}
                        <div class='task-inputs'>
                            <textarea
                                class="task-input submission"
                                name="contributor_code"
                                style="display:none;"
                                rows="30"
                                cols = "40"
                                spellcheck="false"
                            >${task.full_code ?? "f"}</textarea>
                        </div>
                        <input class='task-id' type='text' name='task_id' value='${task.task_id}' style='display: none;'>
                        <button type='submit'>Submit</button>
                        <button type='button' class='cancel'>Cancel</button>
                    </form>
                `;
            }

            const newProject = document.getElementById("new-project");
            const newProjectButton = document.getElementById("new-project-btn");
            const newProjectConfirm = document.querySelector("#new-project button[type='submit']");
            const newProjectCancel = document.querySelector("#new-project .cancel");
            const newProjectNameInput = document.getElementById("new-project-name");
            const newProjectManagerInput = document.getElementById("new-project-manager");
            const newProjectDescriptionInput = document.getElementById("new-project-description");
            const changeProjectForm = document.querySelectorAll(".project-field");
            
            changeProjectForm.forEach(form => {
                const changeProjectName = document.getElementById("change-project-name");
                const changeProjectManager = document.getElementById("change-project-manager");
                const changeProjectDescription = document.getElementById("change-project-description");
                form.addEventListener("submit", (e) => {
                    if (!changeProjectName.value.trim() || changeProjectName.value.trim().length >= 20) {
                        e.preventDefault();
                        alert("Invalid Project Name!");
                        return;
                    }
                    if (!changeProjectDescription.value.trim() || changeProjectDescription.value.trim().length >= 500) {
                        e.preventDefault();
                        alert("Invalid Project Description!");
                        return;
                    }
                    if (!changeProjectManager.value.trim()) {
                        e.preventDefault();
                        alert("Invalid Manager Username!");
                        return;
                    }
                });
            });

            if (newProject) {
                newProjectButton.addEventListener("click", () => {
                    newProjectConfirm.style.display = "block";
                    newProjectCancel.style.display = "block";
                    newProjectNameInput.style.display = "block";
                    newProjectManagerInput.style.display = "block";
                    newProjectDescriptionInput.style.display = "block";
                    newProjectButton.style.display = "none";
                });

                newProjectCancel.addEventListener("click", () => {
                    newProjectConfirm.style.display = "none";
                    newProjectCancel.style.display = "none";
                    newProjectNameInput.style.display = "none";
                    newProjectManagerInput.style.display = "none";
                    newProjectDescriptionInput.style.display = "none";
                    newProjectButton.style.display = "block";
                    newProjectNameInput.value = "";
                    newProjectManagerInput.value = "";
                    newProjectDescriptionInput.style.display = "none";
                });

                newProject.addEventListener("submit", (e) => {
                    if (!newProjectNameInput.value.trim() || newProjectNameInput.value.trim().length >= 20) {
                        e.preventDefault();
                        alert("Invalid Project Name!");
                        return;
                    }
                    if (!newProjectDescriptionInput.value.trim() || newProjectDescriptionInput.value.trim().length >= 500) {
                        e.preventDefault();
                        alert("Invalid Project Description!");
                        return;
                    }
                    if (!newProjectManagerInput.value.trim()) {
                        e.preventDefault();
                        alert("Invalid Manager Username!");
                        return;
                    }
                });
            }

            const newTask = document.getElementById("new-task");
            const newTaskButton = document.getElementById("new-task-btn");
            const newTaskConfirm = document.querySelector("#new-task button[type='submit']");
            const newTaskCancel = document.querySelector("#new-task .cancel");
            const newTaskNameInput = document.getElementById("new-task-name");
            const newTaskContributorInput = document.getElementById("new-task-contributor");
            const newTaskDescriptionInput = document.getElementById("new-task-description");
            const newTaskDeadlineInput = document.getElementById('new-task-deadline');
            const changeTaskForm = document.querySelectorAll(".task-field");
            
            changeTaskForm.forEach(form => {
                const changeTaskName = form.querySelector("[name='task-name']");
                const changeTaskContributor = form.querySelector("[name='contributor']");
                const changeTaskDescription = form.querySelector("[name='task-description']");
                const changeTaskDeadline = form.querySelector("[name='task-deadline']");

                form.addEventListener("submit", (e) => {
                    if (e.submitter?.classList.contains("delete-task-btn")) {
                        return;
                    }

                    if (!changeTaskName.value.trim() || changeTaskName.value.trim().length >= 20) {
                        e.preventDefault();
                        alert("Invalid Task Name!");
                        return;
                    }
                    if (!changeTaskDescription.value.trim() || changeTaskDescription.value.trim().length >= 500) {
                        e.preventDefault();
                        alert("Invalid Task Description!");
                        return;
                    }
                    if (!changeTaskContributor.value.trim()) {
                        e.preventDefault();
                        alert("Invalid Contributor Username!");
                        return;
                    }
                });
            });

            if (newTask) {
                newTaskButton.addEventListener("click", () => {
                    newTaskConfirm.style.display = "block";
                    newTaskCancel.style.display = "block";
                    newTaskNameInput.style.display = "block";
                    newTaskContributorInput.style.display = "block";
                    newTaskDescriptionInput.style.display = "block";
                    newTaskDeadlineInput.style.display = "block";
                    newTaskButton.style.display = "none";
                });

                newTaskCancel.addEventListener("click", () => {
                    newTaskConfirm.style.display = "none";
                    newTaskCancel.style.display = "none";
                    newTaskNameInput.style.display = "none";
                    newTaskContributorInput.style.display = "none";
                    newTaskDescriptionInput.style.display = "none";
                    newTaskDeadlineInput.style.display = "none";
                    newTaskButton.style.display = "block";
                    newTaskNameInput.value = "";
                    newTaskContributorInput.value = "";
                    newTaskDescriptionInput.value = "";
                });

                newTask.addEventListener("submit", (e) => {
                    if (!newTaskNameInput.value.trim() || newTaskNameInput.value.trim().length >= 20) {
                        e.preventDefault();
                        alert("Invalid Task Name!");
                        return;
                    }
                    if (!newTaskDescriptionInput.value.trim() || newTaskDescriptionInput.value.trim().length >= 500) {
                        e.preventDefault();
                        alert("Invalid Task Description!");
                        return;
                    }
                    if (!newTaskContributorInput.value.trim()) {
                        e.preventDefault();
                        alert("Invalid Contributor Username!");
                        return;
                    }
                });
            }

            document.querySelectorAll(".data-field").forEach(form => {
                const changeBtn = form.querySelector(".change");
                const deleteBtn = form.querySelector(".delete-task-btn");
                const confirmBtn = form.querySelector("button[type='submit']");
                const cancelBtn = form.querySelector(".cancel");
                const input = form.querySelectorAll("input:not(.project-id, .project-manager, .task-id, .task-contributor), label, textarea");

                if (changeBtn) {
                    changeBtn.addEventListener("click", (e) => {
                        input.forEach(i => {
                            i.style.display = "block";
                        });
                        confirmBtn.style.display = "block";
                        cancelBtn.style.display = "block";
                        e.currentTarget.style.display = "none";
                        if (deleteBtn) deleteBtn.style.display = "none";
                    });

                    cancelBtn.addEventListener("click", () => {
                        for (const i of input) {
                            if (i.value === "delete" || i.classList.contains('submission')) continue;
                            i.value = "";
                        }
                        input.forEach(i => {
                            i.style.display = "none";
                        });
                        confirmBtn.style.display = "none";
                        cancelBtn.style.display = "none";
                        changeBtn.style.display = "block";
                        if (deleteBtn) deleteBtn.style.display = "inline-block";
                    });
                }

                form.addEventListener("submit", (e) => {
                    const formId = e.currentTarget.id;

                    if (formId === "first-name-form") {
                        if (!input.value.trim() || input.value.trim().length >= 20) {
                            e.preventDefault();
                            alert("Not valid first name!");
                            input.value = "";
                            return;
                        }
                    }

                    if (formId === "last-name-form") {
                        if (!input.value.trim() || input.value.trim().length >= 20) {
                            e.preventDefault();
                            alert("Not valid last name!");
                            input.value = "";
                            return;
                        }
                    }

                    if (formId === "username-form") {
                        if (!input.value.trim() || input.value.trim().length >= 20) {
                            e.preventDefault();
                            alert("Not valid username!");
                            input.value = "";
                            return;
                        }
                    }

                    if (formId === "email-form") {
                        if (!input.value.trim() || !emailRegex.test(input.value)) {
                            e.preventDefault();
                            alert("Not valid email!");
                            input.value = "";
                            return;
                        }
                    }

                    if (formId === "team-form") {
                        if (!input.value.trim() || isNaN(Number(input.value.trim()))) {
                            e.preventDefault();
                            alert("Not valid team id!");
                            input.value = "";
                            return;
                        }
                    }
                });
            });

            deleteCancelBtn.addEventListener("click", () => {
                account.style.display = "grid";
                deleteAccount.style.display = "none";
                logout.style.display = "none";
                deleteBtn.classList.remove('active');
                logoutBtn.classList.remove('active');
                accountBtn.classList.add('active');
            });

            workingSectionBtn.addEventListener("click", () => {
                profileItem.classList.remove("active");
                workingSectionItem.classList.add("active");
                profile.style.display = "none";
                workingSection.style.display = "block";
            });

            changeNameBtn.addEventListener("click", () => {
                changeNameBtn.classList.add("active");
                if (teamNameForm) teamNameForm.style.display = "block";
                if (projectNameForm) projectNameForm.style.display = "block";
            });

            if (changeDescriptionBtn) {
                changeDescriptionBtn.addEventListener("click", () => {
                    changeDescriptionBtn.classList.add("active");
                    if (projectDescriptionForm) projectDescriptionForm.style.display = "block";
                });
            }

            if (teamNameCancel) {
                teamNameCancel.addEventListener("click", () => {
                    changeNameBtn.classList.remove("active");
                    teamNameForm.style.display = "none";
                });
            }

            if (projectNameCancel) {
                projectNameCancel.addEventListener("click", () => {
                    changeNameBtn.classList.remove("active");
                    projectNameForm.style.display = "none";
                });
            }

            if (projectDescriptionCancel) {
                projectDescriptionCancel.addEventListener("click", () => {
                    changeDescriptionBtn.classList.remove("active");
                    projectDescriptionForm.style.display = "none";
                });
            }

            if (teamNameForm) {
                teamNameForm.addEventListener("submit", (e) => {
                    if (!teamNameInput.value.trim() || teamNameInput.value.trim().length >= 20) {
                        e.preventDefault();
                        alert("Invalid team name!");
                        return;
                    }
                });
            }

            if (projectNameForm) {
                projectNameForm.addEventListener("submit", (e) => {
                    if (!projectNameInput.value.trim() || projectNameInput.value.trim().length >= 20) {
                        e.preventDefault();
                        alert("Invalid project name!");
                        return;
                    }
                });
            }

            if (projectDescriptionForm) {
                projectDescriptionForm.addEventListener("submit", (e) => {
                    if (!projectDescriptionInput.value.trim() || projectDescriptionInput.value.trim().length >= 500) {
                        e.preventDefault();
                        alert("Invalid project description!");
                        return;
                    }
                });
            }

            if (deleteTeamBtn) {
                deleteTeamBtn.addEventListener("click", () => {
                    deleteTeamBtn.classList.add("active");
                    deleteTeamForm.style.display = "block";
                });
            }
            
            if (deleteTeamCancel) {
                deleteTeamCancel.addEventListener("click", () => {
                    deleteTeamBtn.classList.remove("active");
                    deleteTeamForm.style.display = "none";
                });
            }
            
            if (deleteProjectBtn) {
                deleteProjectBtn.addEventListener("click", () => {
                    deleteProjectBtn.classList.add("active");
                    deleteProjectForm.style.display = "block";
                });
            }
            
            if (deleteProjectCancel) {
                deleteProjectCancel.addEventListener("click", () => {
                    deleteProjectBtn.classList.remove("active");
                    deleteProjectForm.style.display = "none";
                });
            }
        </script>
    </body>
</html>