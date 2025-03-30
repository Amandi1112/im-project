<?php
session_start();
require_once 'db_connection.php';

if(isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    if(empty($email) || empty($password)) {
        header("Location: login.php?error=Please fill in all fields");
        exit();
    }
    
    // Check if user exists
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $stored_password = $row['password'];
        
        // Verify password
        if(password_verify($password, $stored_password)) {
            // Password is correct, create session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['position'] = $row['position']; // Make sure to store position in session
            
            // Redirect based on position
            if($_SESSION['position'] == 'admin') {
                header("Location: home.php?success=You have successfully logged in");
            } else if($_SESSION['position'] == 'clerk') {
                header("Location: clerk_home.php?success=You have successfully logged in");
            } else if($_SESSION['position'] == 'accountant') {
                header("Location: accountant_home.php?success=You have successfully logged in");
            } else {
                // Fallback for unexpected position values
                header("Location: home.php?success=You have successfully logged in");
            }
            exit();
        } else {
            // Wrong password
            header("Location: login.php?error=Incorrect email or password");
            exit();
        }
    } else {
        // User doesn't exist
        header("Location: login.php?error=Incorrect email or password");
        exit();
    }
} else {
    // If not coming from the login form
    header("Location: login.php");
    exit();
}
?>