<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1 || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginpage.php");
    exit;
}

// Database connection for real data
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch dashboard data
try {
    // 1. Get total students count
    $studentCountQuery = "SELECT COUNT(*) as total_students FROM users WHERE role = 'student'";
    $result = $conn->query($studentCountQuery);
    $totalStudents = $result->fetch_assoc()['total_students'];
    
    // 2. Get total active modules count
    $moduleCountQuery = "SELECT COUNT(*) as total_modules FROM modules WHERE status = 'published'";
    $result = $conn->query($moduleCountQuery);
    $totalModules = $result->fetch_assoc()['total_modules'];
    
    // 3. Calculate completion rate
    $progressQuery = "SELECT 
        COUNT(DISTINCT up.user_id) as users_with_progress,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students
        FROM user_progress up 
        WHERE up.completion_percentage > 0";
    $result = $conn->query($progressQuery);
    $progressData = $result->fetch_assoc();
    $completionRate = $progressData['total_students'] > 0 ? 
        ($progressData['users_with_progress'] / $progressData['total_students']) * 100 : 0;
    
    // 4. Calculate average score
    $avgScoreQuery = "SELECT AVG(completion_percentage) as avg_score FROM user_progress WHERE completion_percentage > 0";
    $result = $conn->query($avgScoreQuery);
    $avgScore = $result->fetch_assoc();
    $averageScore = $avgScore['avg_score'] ?? 0;
    
    // 5. Get growth stats for this month vs last month
    $growthQuery = "SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_students_30d,
        (SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_students_prev_30d";
    $result = $conn->query($growthQuery);
    $growthData = $result->fetch_assoc();
    
    $studentGrowth = 0;
    if ($growthData['new_students_prev_30d'] > 0) {
        $studentGrowth = (($growthData['new_students_30d'] - $growthData['new_students_prev_30d']) / $growthData['new_students_prev_30d']) * 100;
    } elseif ($growthData['new_students_30d'] > 0) {
        $studentGrowth = 100;
    }
    
} catch (Exception $e) {
    // Fallback to default values if there's an error
    $totalStudents = 0;
    $totalModules = 0;
    $completionRate = 0;
    $averageScore = 0;
    $studentGrowth = 0;
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeLearn - AI-Enhanced E-Learning System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/src/output.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#3B82F6',
                        'secondary': '#10B981',
                        'background': '#F9FAFB',
                        'ibm-blue': '#0f62fe'
                    }
                }
            }
        }
    </script>
    <style>
        /* Sidebar styling */
        .sidebar {
            width: 240px;
            background-color: white;
            transition: width 0.3s ease;
        }
        
        .sidebar-collapsed {
            width: 64px;
        }
        
        /* Active indicator */
        .nav-indicator {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #3B82F6;
            opacity: 0;
        }
        
        .nav-item.active .nav-indicator {
            opacity: 1;
        }
        
        .nav-item.active {
            background-color: #F0F7FF;
        }
        
        /* Content area */
        .main-content {
            margin-left: 240px;
            transition: margin-left 0.3s ease;
        }
        
        .main-content-collapsed {
            margin-left: 64px;
        }
        
        /* Profile dropdown */
        .profile-dropdown {
            display: none;
            position: absolute;
            right: 1rem;
            top: 4.5rem;
            width: 240px;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 50;
        }
        
        .profile-dropdown.show {
            display: block;
        }
        
        /* Responsive behavior */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
                height: 100%;
            }
            
            .sidebar.mobile-visible {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .backdrop {
                background-color: rgba(0, 0, 0, 0.5);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 40;
                display: none;
            }
            
            .backdrop.active {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-background">
    <!-- Top Navigation Bar -->
    <nav class="fixed top-0 left-0 right-0 h-16 bg-white shadow-md z-30 flex items-center justify-between px-4">
        <!-- Left side - Menu toggle and title -->
        <div class="flex items-center">
            <button id="toggle-sidebar" class="text-gray-500 hover:text-gray-700 p-2 mr-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="text-xl font-bold text-primary">EyeLearn</h1>
        </div>
        

            <!-- Profile dropdown -->
            <div class="profile-container relative">
                <button id="profile-toggle" class="flex items-center space-x-2 focus:outline-none">
                    <div class="bg-primary rounded-full w-8 h-8 flex items-center justify-center text-white font-medium text-sm">
                        US
                    </div>
                    <span class="hidden md:inline-block font-medium text-gray-700">User Student</span>
                </button>
                
                <!-- Dropdown menu -->
                <div id="profile-dropdown" class="profile-dropdown">
                    <div class="p-4 border-b">
                        <p class="font-medium text-gray-800">User Student</p>
                        <p class="text-sm text-gray-500">student@example.com</p>
                    </div>
                    <div class="p-2">
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded">Your Profile</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded">Settings</a>
                        <a href="../logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="flex min-h-screen pt-16">
        <!-- Mobile backdrop -->
        <div id="backdrop" class="backdrop"></div>
        
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar fixed left-0 top-16 h-full shadow-lg z-40 flex flex-col transition-all duration-300 ease-in-out">
            <!-- Navigation -->
            <nav class="mt-6 flex-1">
                <ul>
                    <!-- Dashboard -->
                    <li class="nav-item relative" id="dashboard-item">
                        <div class="nav-indicator"></div>
                        <a href="Adashboard.php" class="h-14 flex items-center px-5 text-gray-700 hover:bg-gray-50 transition duration-150" id="dashboard-link">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            <span class="font-medium ml-4 nav-text">Dashboard</span>
                        </a>
                    </li>
                    
                    <!-- Modules -->
                    <li class="nav-item relative" id="modules-item">
                        <div class="nav-indicator"></div>
                        <a href="Amodule.php" class="h-14 flex items-center px-5 text-gray-700 hover:bg-gray-50 transition duration-150" id="modules-link">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="font-medium ml-4 nav-text">Modules</span>
                        </a>
                    </li>
                    
                    <!-- Student Management -->
                    <li class="nav-item relative" id="assessments-item">
                        <div class="nav-indicator"></div>
                        <a href="Amanagement.php" class="h-14 flex items-center px-5 text-gray-700 hover:bg-gray-50 transition duration-150" id="assessments-link">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <span class="font-medium ml-4 nav-text">Student Management</span>
                        </a>
                    </li>
                    
                </ul>
            </nav>
        </div>
        
        <!-- Mobile Header -->
        <div class="md:hidden fixed top-0 left-0 right-0 bg-white shadow-md z-30 flex items-center justify-between p-4">
            <button id="mobile-menu-toggle" class="text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="text-xl font-bold text-primary">EyeLearn</h1>
            <div class="w-6 flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </div>
        </div>
               
                
        <!-- Main Content Area -->
        <main id="main-content" class="main-content flex-1 p-6 md:p-8 transition-all duration-300">
            <!-- Page-specific content will go here -->
          <!-- Dashboard Main Content -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Dashboard Overview</h2>
    <p class="text-gray-600 mb-6">Welcome to your E-Learning analytics dashboard. Review student performance, engagement metrics, and learning patterns.</p>
    
    <!-- Dashboard Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Students Card -->
        <div class="bg-blue-50 rounded-lg p-5 border-l-4 border-primary">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Total Students</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($totalStudents); ?></h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-center">
                <?php if ($studentGrowth >= 0): ?>
                <span class="text-green-500 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                    </svg>
                    <?php echo number_format(abs($studentGrowth), 1); ?>%
                </span>
                <?php else: ?>
                <span class="text-red-500 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                    <?php echo number_format(abs($studentGrowth), 1); ?>%
                </span>
                <?php endif; ?>
                <span class="text-gray-500 text-sm ml-2">vs last month</span>
            </div>
        </div>

        <!-- Course Completion Rate -->
        <div class="bg-green-50 rounded-lg p-5 border-l-4 border-secondary">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Completion Rate</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($completionRate, 1); ?>%</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-center">
                <span class="text-blue-500 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
                <span class="text-gray-500 text-sm ml-2">of enrolled students</span>
            </div>
        </div>

        <!-- Average Score -->
        <div class="bg-purple-50 rounded-lg p-5 border-l-4 border-purple-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Average Score</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($averageScore, 1); ?>%</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-center">
                <span class="text-blue-500 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
                <span class="text-gray-500 text-sm ml-2">across all modules</span>
            </div>
        </div>

        <!-- Active Modules -->
        <div class="bg-amber-50 rounded-lg p-5 border-l-4 border-amber-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Active Modules</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($totalModules); ?></h3>
                </div>
                <div class="bg-amber-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-center">
                <span class="text-blue-500 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
                <span class="text-gray-500 text-sm ml-2">published modules</span>
            </div>
        </div>
    </div>

    <!-- Gender Distribution & Gaze Tracking -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Gender Distribution Chart -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Student Gender Distribution</h3>
            <div class="flex items-center justify-center h-64">
                <canvas id="genderChart"></canvas>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                    <span class="text-sm text-gray-600" id="male-percentage">Male (50%)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-pink-500 rounded-full mr-2"></span>
                    <span class="text-sm text-gray-600" id="female-percentage">Female (50%)</span>
                </div>
            </div>
        </div>

        <!-- Gaze Tracking Analysis -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Gaze Tracking Analysis by Gender</h3>
            <div class="flex items-center justify-center h-64">
                <canvas id="gazeTrackingChart"></canvas>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-sm font-medium text-gray-700">Male Focus Time</p>
                    <p class="text-xl font-bold text-primary" id="male-focus-time">
                        <?php
                        // Get male average focus time
                        $maleFocusQuery = "SELECT AVG(ets.total_time_seconds) as avg_time 
                                          FROM eye_tracking_sessions ets
                                          JOIN users u ON ets.user_id = u.id
                                          WHERE u.gender = 'Male' AND ets.total_time_seconds > 0";
                        $result = $conn->query($maleFocusQuery);
                        $maleAvgTime = $result->fetch_assoc()['avg_time'];
                        $maleMinutes = $maleAvgTime ? round($maleAvgTime / 60, 1) : 18.2;
                        echo $maleMinutes . ' min avg';
                        ?>
                    </p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-sm font-medium text-gray-700">Female Focus Time</p>
                    <p class="text-xl font-bold text-pink-500" id="female-focus-time">
                        <?php
                        // Get female average focus time
                        $femaleFocusQuery = "SELECT AVG(ets.total_time_seconds) as avg_time 
                                            FROM eye_tracking_sessions ets
                                            JOIN users u ON ets.user_id = u.id
                                            WHERE u.gender = 'Female' AND ets.total_time_seconds > 0";
                        $result = $conn->query($femaleFocusQuery);
                        $femaleAvgTime = $result->fetch_assoc()['avg_time'];
                        $femaleMinutes = $femaleAvgTime ? round($femaleAvgTime / 60, 1) : 22.6;
                        echo $femaleMinutes . ' min avg';
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Performance & Progress -->
    <div class="grid grid-cols-1 gap-6 mb-8">
        <!-- Student Data Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Student Performance Data</h3>
                <div class="flex items-center">
                    <div class="relative mr-4">
                        <input type="text" placeholder="Search students..." class="border border-gray-300 rounded-lg py-2 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <svg class="w-4 h-4 text-gray-500 absolute right-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <select class="border border-gray-300 rounded-lg py-2 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <option>All Courses</option>
                        <?php
                        // Get available modules for filter
                        $moduleQuery = "SELECT id, title FROM modules WHERE status = 'published' ORDER BY title";
                        $moduleResult = $conn->query($moduleQuery);
                        while ($module = $moduleResult->fetch_assoc()) {
                            echo "<option value='{$module['id']}'>{$module['title']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Score</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modules</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Focus Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sessions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        // Get student performance data
                        $studentQuery = "SELECT 
                            u.id,
                            u.first_name,
                            u.last_name,
                            u.email,
                            u.gender,
                            COALESCE(AVG(up.completion_percentage), 0) as avg_completion,
                            COALESCE(AVG(CASE WHEN ets.total_time_seconds > 0 THEN ets.total_time_seconds END), 0) as avg_focus_time_seconds,
                            COUNT(DISTINCT up.module_id) as modules_enrolled,
                            COUNT(DISTINCT ets.id) as total_sessions
                            FROM users u
                            LEFT JOIN user_progress up ON u.id = up.user_id
                            LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
                            WHERE u.role = 'student'
                            GROUP BY u.id, u.first_name, u.last_name, u.email, u.gender
                            ORDER BY avg_completion DESC, avg_focus_time_seconds DESC
                            LIMIT 10";
                        
                        $studentResult = $conn->query($studentQuery);
                        
                        if ($studentResult && $studentResult->num_rows > 0) {
                            while ($student = $studentResult->fetch_assoc()) {
                                $initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
                                $avgCompletion = round($student['avg_completion'], 1);
                                $avgFocusTimeMinutes = round($student['avg_focus_time_seconds'] / 60, 1);
                                $gender = $student['gender'] ?: 'Not specified';
                                $totalSessions = $student['total_sessions'];
                                
                                echo "<tr>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>ST-{$student['id']}</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<div class='flex items-center'>";
                                echo "<div class='h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium'>{$initials}</div>";
                                echo "<div class='ml-3'>";
                                echo "<div class='text-sm font-medium text-gray-900'>{$student['first_name']} {$student['last_name']}</div>";
                                echo "<div class='text-sm text-gray-500'>{$student['email']}</div>";
                                echo "</div></div></td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$gender}</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<div class='text-sm text-gray-900'>{$avgCompletion}%</div>";
                                echo "<div class='w-full bg-gray-200 rounded-full h-1.5 mt-1'>";
                                echo "<div class='bg-primary h-1.5 rounded-full' style='width: {$avgCompletion}%'></div>";
                                echo "</div></td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<div class='text-sm text-gray-900'>{$student['modules_enrolled']} modules</div>";
                                echo "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$avgFocusTimeMinutes} min</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap'>";
                                echo "<div class='text-sm text-gray-900'>{$totalSessions} sessions</div>";
                                echo "<div class='text-xs text-gray-500'>Eye tracking data</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='px-6 py-4 text-center text-gray-500'>No student data available</td></tr>";
                        }
                        
                        // Get total count for pagination
                        $countQuery = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
                        $countResult = $conn->query($countQuery);
                        $totalCount = $countResult->fetch_assoc()['total'];
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-6">
                <div class="text-sm text-gray-500">
                    Showing <?php echo min(10, $totalCount); ?> of <?php echo $totalCount; ?> students
                </div>
                <div class="flex">
                    <button class="px-3 py-1 border border-gray-300 rounded-l-lg bg-white text-gray-500 hover:bg-gray-50">Previous</button>
                    <button class="px-3 py-1 border-t border-b border-gray-300 bg-primary text-white">1</button>
                    <?php if ($totalCount > 10): ?>
                    <button class="px-3 py-1 border-t border-b border-gray-300 bg-white text-gray-500 hover:bg-gray-50">2</button>
                    <?php endif; ?>
                    <button class="px-3 py-1 border border-gray-300 rounded-r-lg bg-white text-gray-500 hover:bg-gray-50">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Charts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
    // Fetch real dashboard data
    async function fetchDashboardData() {
        try {
            const response = await fetch('database/get_dashboard_data.php');
            const data = await response.json();
            
            if (data.error) {
                console.error('Error fetching dashboard data:', data.error);
                return null;
            }
            
            return data;
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            return null;
        }
    }

    // Initialize charts with real data
    async function initializeCharts() {
        const dashboardData = await fetchDashboardData();
        
        if (!dashboardData) {
            // Fallback to static data if API fails
            initializeStaticCharts();
            return;
        }

        // Gender Distribution Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderData = dashboardData.gender_distribution || [];
        
        let maleCount = 0;
        let femaleCount = 0;
        let malePercentage = 0;
        let femalePercentage = 0;
        
        genderData.forEach(item => {
            if (item.gender === 'Male') {
                maleCount = item.count;
                malePercentage = parseFloat(item.percentage);
            }
            if (item.gender === 'Female') {
                femaleCount = item.count;
                femalePercentage = parseFloat(item.percentage);
            }
        });
        
        // If no gender data, show equal distribution
        if (malePercentage === 0 && femalePercentage === 0) {
            malePercentage = 50;
            femalePercentage = 50;
        }
        
        // Update legend with real percentages
        document.getElementById('male-percentage').textContent = `Male (${malePercentage.toFixed(1)}%)`;
        document.getElementById('female-percentage').textContent = `Female (${femalePercentage.toFixed(1)}%)`;
        
        const genderChart = new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [maleCount || malePercentage, femaleCount || femalePercentage],
                    backgroundColor: [
                        '#3B82F6',
                        '#EC4899'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} students (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Gaze Tracking Chart
        const gazeCtx = document.getElementById('gazeTrackingChart').getContext('2d');
        const moduleAnalytics = dashboardData.module_analytics || [];
        
        // Process module analytics data for chart
        const modules = [...new Set(moduleAnalytics.map(item => item.module_name))];
        const maleData = [];
        const femaleData = [];
        
        modules.forEach(module => {
            const maleItem = moduleAnalytics.find(item => item.module_name === module && item.gender === 'Male');
            const femaleItem = moduleAnalytics.find(item => item.module_name === module && item.gender === 'Female');
            
            maleData.push(maleItem ? parseFloat(maleItem.avg_time_minutes || 0) : 0);
            femaleData.push(femaleItem ? parseFloat(femaleItem.avg_time_minutes || 0) : 0);
        });
        
        // If no module data, use sample topics with fallback data
        const chartLabels = modules.length > 0 ? modules : ['Module 1', 'Module 2', 'Module 3', 'Module 4'];
        const chartMaleData = maleData.length > 0 ? maleData : [20.4, 15.2, 18.5, 18.7];
        const chartFemaleData = femaleData.length > 0 ? femaleData : [22.3, 21.8, 20.1, 26.2];
        
        const gazeChart = new Chart(gazeCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Male Students',
                        data: chartMaleData,
                        backgroundColor: '#3B82F6',
                        borderWidth: 0
                    },
                    {
                        label: 'Female Students',
                        data: chartFemaleData,
                        backgroundColor: '#EC4899',
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        grid: {
                            borderDash: [2, 4]
                        },
                        title: {
                            display: true,
                            text: 'Average Focus Time (minutes)'
                        },
                        min: 0,
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y || 0;
                                return `${label}: ${value.toFixed(1)} minutes avg`;
                            }
                        }
                    }
                }
            }
        });

        // Update focus time summary with real data
        const focusTimeData = dashboardData.focus_time_by_gender || [];
        const maleAvgTime = focusTimeData.find(item => item.gender === 'Male')?.avg_focus_time_minutes || 18.2;
        const femaleAvgTime = focusTimeData.find(item => item.gender === 'Female')?.avg_focus_time_minutes || 22.6;
        
        // Update the focus time display in the DOM with specific IDs
        const maleFocusElement = document.getElementById('male-focus-time');
        const femaleFocusElement = document.getElementById('female-focus-time');
        
        if (maleFocusElement) {
            maleFocusElement.textContent = `${maleAvgTime.toFixed(1)} min avg`;
        }
        if (femaleFocusElement) {
            femaleFocusElement.textContent = `${femaleAvgTime.toFixed(1)} min avg`;
        }
    }

    // Fallback function for static charts if API fails
    function initializeStaticCharts() {
        // Gender Distribution Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [50, 50],
                    backgroundColor: [
                        '#3B82F6',
                        '#EC4899'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Gaze Tracking Chart
        const gazeCtx = document.getElementById('gazeTrackingChart').getContext('2d');
        const gazeChart = new Chart(gazeCtx, {
            type: 'bar',
            data: {
                labels: ['Topic 1', 'Topic 2', 'Topic 3', 'Topic 4'],
                datasets: [
                    {
                        label: 'Male Students',
                        data: [20.4, 15.2, 18.5, 18.7],
                        backgroundColor: '#3B82F6',
                        borderWidth: 0
                    },
                    {
                        label: 'Female Students',
                        data: [22.3, 21.8, 20.1, 26.2],
                        backgroundColor: '#EC4899',
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        grid: {
                            borderDash: [2, 4]
                        },
                        title: {
                            display: true,
                            text: 'Average Focus Time (minutes)'
                        },
                        min: 0
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Initialize charts when page loads
    document.addEventListener('DOMContentLoaded', function() {
        initializeCharts();
        
        // Refresh dashboard data every 30 seconds
        setInterval(refreshDashboardData, 30000);
    });

    // Function to refresh dashboard data without full page reload
    async function refreshDashboardData() {
        const dashboardData = await fetchDashboardData();
        
        if (!dashboardData) {
            console.log('Failed to fetch updated dashboard data');
            return;
        }

        // Update focus time summary
        const focusTimeData = dashboardData.focus_time_by_gender || [];
        const maleAvgTime = focusTimeData.find(item => item.gender === 'Male')?.avg_focus_time_minutes || 18.2;
        const femaleAvgTime = focusTimeData.find(item => item.gender === 'Female')?.avg_focus_time_minutes || 22.6;
        
        // Update focus time display
        const maleFocusElement = document.getElementById('male-focus-time');
        const femaleFocusElement = document.getElementById('female-focus-time');
        
        if (maleFocusElement) {
            maleFocusElement.textContent = `${maleAvgTime} min avg`;
        }
        if (femaleFocusElement) {
            femaleFocusElement.textContent = `${femaleAvgTime} min avg`;
        }

        // Update gender distribution legend
        const genderData = dashboardData.gender_distribution || [];
        let malePercentage = 50;
        let femalePercentage = 50;
        
        genderData.forEach(item => {
            if (item.gender === 'Male') {
                malePercentage = parseFloat(item.percentage);
            }
            if (item.gender === 'Female') {
                femalePercentage = parseFloat(item.percentage);
            }
        });
        
        // Update legend elements
        const malePercentageElement = document.getElementById('male-percentage');
        const femalePercentageElement = document.getElementById('female-percentage');
        
        if (malePercentageElement) {
            malePercentageElement.textContent = `Male (${malePercentage.toFixed(1)}%)`;
        }
        if (femalePercentageElement) {
            femalePercentageElement.textContent = `Female (${femalePercentage.toFixed(1)}%)`;
        }

        console.log('Dashboard data refreshed at:', new Date().toLocaleTimeString());
    }
</script>
        </main>
    </div>
    
    <script>
    // DOM Elements
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const toggleSidebarBtn = document.getElementById('toggle-sidebar');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const navTexts = document.querySelectorAll('.nav-text');
    const backdrop = document.getElementById('backdrop');
    
    // Navigation items
    const dashboardItem = document.getElementById('dashboard-item');
    const modulesItem = document.getElementById('modules-item');
    const assessmentsItem = document.getElementById('assessments-item');

    // Navigation links
    const dashboardLink = document.getElementById('dashboard-link');
    const modulesLink = document.getElementById('modules-link');
    const assessmentsLink = document.getElementById('assessments-link');

    
    // Profile dropdown elements
    const profileToggle = document.getElementById('profile-toggle');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    // Toggle profile dropdown on click
    profileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });
    
    // Close dropdown when clicking elsewhere on the page
    document.addEventListener('click', function(e) {
        if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });
    
    // Function to handle active page styling
    function setActivePage() {
        const currentPage = window.location.pathname.split('/').pop(); // Get the current page file name
        
        // Reset all links
        [dashboardItem, modulesItem, assessmentsItem].forEach(item => {
            item.classList.remove('active');
        });
        
        // Highlight the active link based on the current page
        if (currentPage === 'Adashboard.php' || currentPage === '' || currentPage === '/') {
            dashboardItem.classList.add('active');
        } else if (currentPage === 'Amodule.php') {
            modulesItem.classList.add('active');
        } else if (currentPage === 'Amanagement.php') {
            assessmentsItem.classList.add('active');
        } else {
            // Default to dashboard if no match
            dashboardItem.classList.add('active');
        }
    }
    
    // Toggle sidebar collapse
    toggleSidebarBtn.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-collapsed');
        mainContent.classList.toggle('main-content-collapsed');
        if (sidebar.classList.contains('sidebar-collapsed')) {
            navTexts.forEach(text => text.classList.add('hidden'));
        } else {
            setTimeout(() => {
                navTexts.forEach(text => text.classList.remove('hidden'));
            }, 150); // Small delay for better animation
        }
    });
    
    // Check screen width and apply responsive design
    function checkScreenSize() {
        if (window.innerWidth < 768) {
            sidebar.classList.remove('sidebar-collapsed'); // Always keep full width for mobile
            mainContent.classList.remove('main-content-collapsed');
            sidebar.classList.remove('mobile-visible');
            backdrop.classList.remove('active');
        }
    }
    
    // Run on load and on resize
    window.addEventListener('resize', checkScreenSize);
    checkScreenSize();
    
    // Call the function to set the active page on load
    setActivePage();
    
    // Mobile menu toggle
    mobileMenuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-visible');
        backdrop.classList.toggle('active');
    });
    
    // Close sidebar when clicking on backdrop
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('mobile-visible');
        backdrop.classList.remove('active');
    });
    
    // Make dashboard active by default
    if (!dashboardItem.classList.contains('active') && 
        !modulesItem.classList.contains('active') && 
        !assessmentsItem.classList.contains('active')) {
        dashboardItem.classList.add('active');
    }
</script>
</body>
</html>