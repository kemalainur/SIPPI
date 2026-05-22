<?php
require_once 'config/database.php';
session_start();

if (isset($_SESSION['user'])) {
    header("Location: " . base_url('dashboard/dashboard.php'));
} else {
    header("Location: " . base_url('auth/login.php'));
}
exit;
?>