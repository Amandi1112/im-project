<?php 
require_once 'db_connection.php';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($position)) {
        header("Location: signup.php?error=Please fill in all fields&name=$name&email=$email&position=$position");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: signup.php?error=Invalid email format&name=$name&position=$position");
        exit();
    }
    
    if ($password !== $confirm_password) {
        header("Location: signup.php?error=Passwords don't match&name=$name&email=$email&position=$position");
        exit();
    }
    
    if (strlen($password) < 6) {
        header("Location: signup.php?error=Password must be at least 6 characters&name=$name&email=$email&position=$position");
        exit();
    }
    
    // Check if email already exists
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        header("Location: signup.php?error=Email already exists&name=$name&position=$position");
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $insert_sql = "INSERT INTO users (name, email, password, position) VALUES (?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "ssss", $name, $email, $hashed_password, $position);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        // Redirect to login page with success message
        header("Location: login.php?success=Account created successfully. Please login.");
        exit();
    } else {
        // If database insertion fails
        header("Location: signup.php?error=Something went wrong. Please try again.&name=$name&position=$position");
        exit();
    }
}
// Handle AJAX email check
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['email_check'])) {
    $email = mysqli_real_escape_string($conn, $_GET['email_check']);
    
    // Only check if email format is valid
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        header('Content-Type: application/json');
        echo json_encode(['exists' => mysqli_stmt_num_rows($stmt) > 0]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['exists' => false, 'error' => 'Invalid email format']);
    }
    exit();
}
else {
    // If not coming from the signup form or email check
    header("Location: signup.php");
    exit();
}
?>