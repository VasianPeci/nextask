<?php 
$server_name = "db";
$username = "root";
$password = "vasian26_root_calisthenics";
$database = "nextask";

$conn = mysqli_connect($server_name, $username, $password, $database);
if (!$conn) {
    error_log(mysqli_connect_error());
    echo "<p>Cannot establish database connection!</p>";
    exit;
}
?>