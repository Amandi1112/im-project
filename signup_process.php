<?php require_once 'db_connection.php';

if(isset($_POST['signup'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $position = mysqli_real_escape_string($conn, $_POST['position']); // Add this line
    
    // Validate input
    if(empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($position)) { // Add position check
        header("Location: signup.php?error=Please fill in all fields");
        exit();
    }
    
    // Rest of your validation code...
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user - modified to include position
    $sql = "INSERT INTO users (name, email, password, position) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed_password, $position);
    
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