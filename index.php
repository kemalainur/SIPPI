<?php
require_once 'config/database.php';
session_start();

if (isset($_SESSION['user'])) {
    header("Location: dashboard/dashboard.php");
} else {
    header("Location: auth/login.php");
}
exit;
?>


