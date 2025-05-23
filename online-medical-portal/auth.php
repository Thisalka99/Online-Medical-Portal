<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // Store a message in the session to display on the login page.
    $_SESSION['message'] = "Please login to access this page.";
    header("Location: login.php");
    exit(); // Ensure no further script execution
}

// The following variables can be used by any script that includes auth.php
$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$current_user_role = $_SESSION['role'];

// Example of how to restrict access based on role:
/*
if ($current_user_role !== 'doctor') {
    // Redirect to a different page or show an error
    $_SESSION['message'] = "You do not have permission to access this page.";
    header("Location: dashboard.php"); // Or an error page
    exit();
}
*/
?>
