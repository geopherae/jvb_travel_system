<?php
session_start();

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

session_unset();

session_destroy();

$redirect = $isAdmin 
    ? "../admin/admin_login.php" 
    : "../client/login.php";

header("Location: $redirect");
exit;
?>