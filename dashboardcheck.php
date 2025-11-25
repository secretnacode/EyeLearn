<?php
// dashboard-check.php
// This file handles redirecting users to the appropriate dashboard based on their role

require_once 'config.php';

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user role from session
$userRole = $_SESSION['user_role'];

// Redirect based on role
if ($userRole === 'admin') {
    header('Location: admin-dashboard.php');
    exit;
} else {
    header('Location: student-dashboard.php');
    exit;
}