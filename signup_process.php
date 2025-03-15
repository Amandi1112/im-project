<?php
require_once 'db_connection.php';

if(isset($_POST['signup'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if(empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: signup.php?error=Please fill in all fields");
        exit();
    }
    
    // Validate email format
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: signup.php?error=Invalid email format");
        exit();
    }
    
    // Check if passwords match
    if($password !== $confirm_password) {
        header("Location: signup.php?error=Passwords do not match");
        exit();
    }
    
    // Check password length
    if(strlen($password) < 6) {
        header("Location: signup.php?error=Password must be at least 6 characters long");
        exit();
    }
    
    // Check if email already exists
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) > 0) {
        header("Location: signup.php?error=Email already exists");
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user - modified to remove created_at field
    $sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $name, $email, $hashed_password);
    
    if(mysqli_stmt_execute($stmt)) {
        header("Location: login.php?success=Account created successfully. Please login.");
        exit();
    } else {
        header("Location: signup.php?error=Something went wrong. Please try again.");
        exit();
    }
} else {
    // If not coming from the signup form
    header("Location: signup.php");
    exit();
}
?>