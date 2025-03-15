<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php?info=You have been logged out successfully");
exit();
?>