<?php
require_once 'config.php';

// Only run this in development environment
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    die("Access denied");
}

// Admin account details
$adminDetails = [
    'first_name' => 'System',
    'last_name' => 'Admin',
    'email' => 'admin@admin.eyelearn',
    'password' => 'SecureAdminPassword123!', // Change this to a strong password
    'gender' => 'Other'
];

// Check if admin already exists
if (!userExists($adminDetails['email'], $pdo)) {
    if (createAdminAccount(
        $adminDetails['first_name'],
        $adminDetails['last_name'],
        $adminDetails['email'],
        $adminDetails['password'],
        $adminDetails['gender'],
        $pdo
    )) {
        echo "Admin account created successfully!";
    } else {
        echo "Failed to create admin account.";
    }
} else {
    echo "Admin account already exists.";
}