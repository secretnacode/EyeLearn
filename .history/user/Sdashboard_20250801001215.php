<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../loginpage.php");
    exit();
}

// Add database connection
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT first_name, last_name, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    $user_display_name = $user['first_name'] . ' ' . $user['last_name'];
    $user_email = $user['email'];
    $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
} else {
    // Fallback if user not found
    $user_display_name = "User Student";
    $user_email = "student@example.com";
    $initials = "US";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeLearn - AI-Enhanced E-Learning System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        <!-- Right side - Notifications and Profile -->
        <div class="flex items-center space-x-4">
            
            <!-- Profile dropdown -->
            <div class="profile-container relative">
                <button id="profile-toggle" class="flex items-center space-x-2 focus:outline-none">
                    <div class="bg-primary rounded-full w-8 h-8 flex items-center justify-center text-white font-medium text-sm">
                        <?php echo $initials; ?>
                    </div>
                    <span class="hidden md:inline-block font-medium text-gray-700"><?php echo $user_display_name; ?></span>
                </button>
                
                <!-- Dropdown menu -->
                <div id="profile-dropdown" class="profile-dropdown">
                    <div class="p-4 border-b">
                        <p class="font-medium text-gray-800"><?php echo $user_display_name; ?></p>
                        <p class="text-sm text-gray-500"><?php echo $user_email; ?></p>
                    </div>
                    <div class="p-2">
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded">Your Profile</a>
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
                        <a href="Sdashboard.php" class="h-14 flex items-center px-5 text-gray-700 hover:bg-gray-50 transition duration-150" id="dashboard-link">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            <span class="font-medium ml-4 nav-text">Dashboard</span>
                        </a>
                    </li>
                    
                    <!-- Modules -->
                    <li class="nav-item relative" id="modules-item">
                        <div class="nav-indicator"></div>
                        <a href="Smodule.php" class="h-14 flex items-center px-5 text-gray-700 hover:bg-gray-50 transition duration-150" id="modules-link">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="font-medium ml-4 nav-text">Modules</span>
                        </a>
                    </li>
                    
                    <!-- Assessments -->
                    <li class="nav-item relative" id="assessments-item">
                        <div class="nav-indicator"></div>
                        <a href="Sassessment.php" class="h-14 flex items-center px-5 text-gray-700 hover:bg-gray-50 transition duration-150" id="assessments-link">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <span class="font-medium ml-4 nav-text">Assessment History</span>
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
                    
        <main id="main-content" class="main-content flex-1 p-6 md:p-8 transition-all duration-300">
        <div class="p-6">
                <!-- Welcome Section -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($user_display_name); ?>!</h2>
                        <div class="flex items-center bg-blue-50 text-primary px-3 py-1 rounded-full">
                            <i class="fas fa-eye mr-2"></i>
                            <span class="text-sm font-medium">Eye tracking active</span>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">Your personalized learning journey continues. Based on your eye
                        tracking data, we've tailored your content to optimize your comprehension.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Weekly Focus Score</p>
                                    <p class="text-2xl font-bold text-primary">87%</p>
                                </div>
                                <i class="fas fa-eye text-primary text-xl"></i>
                            </div>
                            <div class="mt-2">
                                <div class="text-xs text-green-600">▲ 5% from last week</div>
                            </div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Comprehension Level</p>
                                    <p class="text-2xl font-bold text-secondary">Advanced</p>
                                </div>
                                <i class="fas fa-brain text-secondary text-xl"></i>
                            </div>
                            <div class="mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-secondary h-2 rounded-full" style="width: 85%"></div>
                                </div>
                            </div>
                        </div>
                       
                    </div>
                </div>


                <!-- Eye-Tracking Analytics -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6" id="analytics-section">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Your Learning Analytics</h3>
                        <div class="flex items-center space-x-2">
                            <div id="live-indicator" class="hidden">
                                <span class="flex items-center text-xs text-green-600">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-1 animate-pulse"></span>
                                    Live Data
                                </span>
                            </div>
                            <button onclick="refreshAnalytics()" class="text-primary hover:underline text-sm">🔄 Refresh Data</button>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="analytics-loading" class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-2"></div>
                        <p class="text-gray-500">Loading your analytics...</p>
                    </div>
                    
                    <!-- Analytics Content -->
                    <div id="analytics-content" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Focus Trends Chart -->
                            <div>
                                <div class="mb-4">
                                    <h4 class="font-medium text-gray-800 mb-2">Your Focus Trends (Last 7 Days)</h4>
                                    <div class="h-48 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <canvas id="focusChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                                <div class="bg-blue-50 p-4 rounded-lg" id="analytics-insight">
                                    <p class="text-gray-700 text-sm">
                                        <i class="fas fa-lightbulb text-primary mr-2"></i>
                                        <span id="insight-text">Loading personalized insights...</span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Real-time Metrics -->
                            <div>
                                <div class="mb-2">
                                    <h4 class="font-medium text-gray-800 mb-2">Study Performance Metrics</h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div class="text-sm text-gray-500">Total Study Time</div>
                                            <div class="text-lg font-bold text-gray-800" id="total-study-time">0 hrs</div>
                                            <div class="text-xs text-blue-600" id="study-time-change">Eye tracking enabled</div>
                                        </div>
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div class="text-sm text-gray-500">Focus Efficiency</div>
                                            <div class="text-lg font-bold text-gray-800" id="focus-efficiency">0%</div>
                                            <div class="text-xs text-green-600" id="efficiency-trend">Calculating...</div>
                                        </div>
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div class="text-sm text-gray-500">Avg Session</div>
                                            <div class="text-lg font-bold text-gray-800" id="avg-session">0 min</div>
                                            <div class="text-xs text-green-600" id="session-quality">Good sessions</div>
                                        </div>
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div class="text-sm text-gray-500">Modules Studied</div>
                                            <div class="text-lg font-bold text-gray-800" id="modules-studied">0</div>
                                            <div class="text-xs text-orange-600" id="module-progress">Keep learning!</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <h4 class="font-medium text-gray-800 mb-2">AI Learning Insights</h4>
                                    <ul class="space-y-2" id="learning-insights">
                                        <li class="flex items-start">
                                            <i class="fas fa-spinner fa-spin text-blue-500 mt-1 mr-2"></i>
                                            <span class="text-sm text-gray-700">Analyzing your learning patterns...</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Module Performance Section -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="font-medium text-gray-800 mb-3">Module Performance Overview</h4>
                            <div id="module-performance" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Module performance cards will be populated here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Error State -->
                    <div id="analytics-error" class="hidden text-center py-8">
                        <div class="text-red-500 mb-2">
                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                        </div>
                        <p class="text-gray-600 mb-2">Unable to load analytics data</p>
                        <button onclick="loadAnalytics()" class="text-primary hover:underline text-sm">Try Again</button>
                    </div>
                </div>

                <!-- Next Assessments -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Upcoming Assessments</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Assessment</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Due Date</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estimated Time</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">Introduction To Information Technology Computing </div>
                                        <div class="text-sm text-gray-500">Module 1</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">April 25, 2025</div>
                                        <div class="text-sm text-gray-500">11:59 PM</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">30 minutes</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Not Started
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button class="text-primary hover:text-indigo-700">Start Now</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">Components Of Computer System
                                        </div>
                                        <div class="text-sm text-gray-500">Module 2</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">April 28, 2025</div>
                                        <div class="text-sm text-gray-500">11:59 PM</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">2 hours</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            In Progress (30%)
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button class="text-primary hover:text-indigo-700">Continue</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
           </main>
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
        if (currentPage === 'Sdashboard.php' || currentPage === '' || currentPage === '/') {
            dashboardItem.classList.add('active');
        } else if (currentPage === 'Smodule.php') {
            modulesItem.classList.add('active');
        } else if (currentPage === 'Sassessment.php') {
            assessmentsItem.classList.add('active');
        } else {
            // Default to dashboard if no match
            dashboardItem.classList.add('active');
        }
    }

    // Call the function to set the active page on load
    setActivePage();

    // Analytics functionality
    let focusChart = null;
    let analyticsUpdateInterval = null;

    async function loadAnalytics() {
        const loadingElement = document.getElementById('analytics-loading');
        const contentElement = document.getElementById('analytics-content');
        const errorElement = document.getElementById('analytics-error');

        // Show loading state only on first load
        if (!contentElement.classList.contains('hidden')) {
            // Subsequent loads - show subtle loading indicator
            showSubtleLoading();
        } else {
            // First load - show full loading
            loadingElement.classList.remove('hidden');
            contentElement.classList.add('hidden');
            errorElement.classList.add('hidden');
        }

        try {
            const response = await fetch('database/get_analytics_data.php?t=' + Date.now());
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Failed to load analytics data');
            }

            // Update metrics display
            updateMetricsDisplay(result.data);
            
            // Create focus trends chart
            createFocusChart(result.data.focus_trends);
            
            // Update module performance
            updateModulePerformance(result.data.module_performance);
            
            // Update insights
            updateInsights(result.data);

            // Update real-time status
            updateRealtimeStatus(result.data.realtime_data);

            // Show content
            loadingElement.classList.add('hidden');
            contentElement.classList.remove('hidden');
            hideSubtleLoading();

        } catch (error) {
            console.error('Error loading analytics:', error);
            loadingElement.classList.add('hidden');
            if (contentElement.classList.contains('hidden')) {
                errorElement.classList.remove('hidden');
            }
            hideSubtleLoading();
        }
    }

    function showSubtleLoading() {
        // Add subtle loading indicator for real-time updates
        const refreshBtn = document.querySelector('button[onclick="refreshAnalytics()"]');
        if (refreshBtn) {
            refreshBtn.innerHTML = '🔄 <span class="animate-spin inline-block">⟳</span> Updating...';
        }
    }

    function hideSubtleLoading() {
        const refreshBtn = document.querySelector('button[onclick="refreshAnalytics()"]');
        if (refreshBtn) {
            refreshBtn.innerHTML = '🔄 Refresh Data';
        }
    }

    function updateRealtimeStatus(realtimeData) {
        if (!realtimeData) return;

        // Update last updated timestamp
        const lastUpdated = new Date(realtimeData.last_updated).toLocaleTimeString();
        
        // Add real-time status indicator
        let statusIndicator = document.getElementById('realtime-status');
        if (!statusIndicator) {
            statusIndicator = document.createElement('div');
            statusIndicator.id = 'realtime-status';
            statusIndicator.className = 'text-xs text-gray-500 mt-2';
            document.getElementById('analytics-content').appendChild(statusIndicator);
        }

        const isStudying = realtimeData.is_currently_studying;
        const activeModule = realtimeData.active_module;

        statusIndicator.innerHTML = `
            <div class="flex items-center justify-between">
                <span>
                    ${isStudying ? 
                        `<span class="text-green-600">● Currently studying${activeModule ? ': ' + activeModule : ''}</span>` : 
                        '<span class="text-gray-400">○ No active session</span>'
                    }
                </span>
                <span>Updated: ${lastUpdated}</span>
            </div>
        `;
    }

    function startRealtimeUpdates() {
        // Update analytics every 30 seconds for real-time data
        if (analyticsUpdateInterval) {
            clearInterval(analyticsUpdateInterval);
        }
        
        analyticsUpdateInterval = setInterval(() => {
            loadAnalytics();
        }, 30000); // 30 seconds
        
        console.log('📊 Real-time analytics updates started (30s interval)');
    }

    function stopRealtimeUpdates() {
        if (analyticsUpdateInterval) {
            clearInterval(analyticsUpdateInterval);
            analyticsUpdateInterval = null;
            console.log('📊 Real-time analytics updates stopped');
        }
    }

    function updateMetricsDisplay(data) {
        const stats = data.overall_stats;
        
        document.getElementById('total-study-time').textContent = `${stats.total_study_time_hours} hrs`;
        document.getElementById('focus-efficiency').textContent = `${stats.focus_efficiency_percent}%`;
        document.getElementById('avg-session').textContent = `${stats.avg_session_minutes} min`;
        document.getElementById('modules-studied').textContent = stats.modules_studied;

        // Update trend indicators
        if (stats.focus_efficiency_percent > 75) {
            document.getElementById('efficiency-trend').textContent = '✨ Excellent focus!';
            document.getElementById('efficiency-trend').className = 'text-xs text-green-600';
        } else if (stats.focus_efficiency_percent > 50) {
            document.getElementById('efficiency-trend').textContent = '📈 Good progress';
            document.getElementById('efficiency-trend').className = 'text-xs text-blue-600';
        } else {
            document.getElementById('efficiency-trend').textContent = '💡 Room for improvement';
            document.getElementById('efficiency-trend').className = 'text-xs text-orange-600';
        }

        if (stats.avg_session_minutes > 20) {
            document.getElementById('session-quality').textContent = 'Great sessions!';
        } else if (stats.avg_session_minutes > 10) {
            document.getElementById('session-quality').textContent = 'Good duration';
        } else {
            document.getElementById('session-quality').textContent = 'Try longer sessions';
        }

        if (stats.total_sessions > 0) {
            const studyTimeChange = document.getElementById('study-time-change');
            studyTimeChange.textContent = `${stats.total_sessions} sessions completed`;
        }
    }

    function createFocusChart(trendsData) {
        const ctx = document.getElementById('focusChart');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (focusChart) {
            focusChart.destroy();
        }

        // Prepare data for chart
        const labels = trendsData.map(item => {
            const date = new Date(item.study_date);
            return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        });
        
        const focusTimes = trendsData.map(item => Math.round(item.daily_focus_time / 60)); // Convert to minutes
        const sessionCounts = trendsData.map(item => item.daily_sessions);

        focusChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Focus Time (minutes)',
                    data: focusTimes,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Minutes'
                        }
                    }
                }
            }
        });
    }

    function updateModulePerformance(moduleData) {
        const container = document.getElementById('module-performance');
        if (!container) return;

        container.innerHTML = '';

        if (moduleData.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-4 text-gray-500">
                    <i class="fas fa-book-open text-2xl mb-2"></i>
                    <p>Start studying modules to see your performance data here!</p>
                </div>
            `;
            return;
        }

        // Show top 3 modules
        const topModules = moduleData.slice(0, 3);
        
        topModules.forEach((module, index) => {
            const hours = Math.round(module.total_time / 3600 * 10) / 10;
            const lastStudied = new Date(module.last_studied).toLocaleDateString();
            
            const moduleCard = document.createElement('div');
            moduleCard.className = 'bg-gray-50 p-4 rounded-lg';
            moduleCard.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-gray-800 truncate">${module.module_title}</h5>
                    <span class="text-xs px-2 py-1 rounded-full ${index === 0 ? 'bg-green-100 text-green-800' : index === 1 ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}">
                        #${index + 1}
                    </span>
                </div>
                <div class="space-y-1 text-sm text-gray-600">
                    <div>📚 ${hours}h studied</div>
                    <div>🎯 ${module.session_count} sessions</div>
                    <div>📅 Last: ${lastStudied}</div>
                </div>
            `;
            container.appendChild(moduleCard);
        });
    }

    function updateInsights(data) {
        const insights = data.insights;
        const overallStats = data.overall_stats;
        const insightsContainer = document.getElementById('learning-insights');
        
        if (!insightsContainer) return;

        let insightsList = [];

        // Focus efficiency insight
        if (overallStats.focus_efficiency_percent > 80) {
            insightsList.push({
                icon: 'fas fa-trophy text-green-500',
                text: 'Excellent focus levels! You maintain attention very well during study sessions.'
            });
        } else if (overallStats.focus_efficiency_percent > 60) {
            insightsList.push({
                icon: 'fas fa-target text-blue-500',
                text: 'Good focus patterns. Consider eliminating distractions to improve further.'
            });
        } else {
            insightsList.push({
                icon: 'fas fa-lightbulb text-orange-500',
                text: 'Try shorter study sessions with breaks to improve concentration.'
            });
        }

        // Session length insight
        if (overallStats.avg_session_minutes > 30) {
            insightsList.push({
                icon: 'fas fa-clock text-blue-500',
                text: 'You prefer longer study sessions. Make sure to take regular breaks.'
            });
        } else if (overallStats.avg_session_minutes < 10) {
            insightsList.push({
                icon: 'fas fa-fast-forward text-purple-500',
                text: 'Quick study sessions can be effective. Try gradually increasing duration.'
            });
        }

        // Study consistency
        if (data.focus_trends && data.focus_trends.length > 0) {
            const avgDailyTime = data.focus_trends.reduce((sum, day) => sum + day.daily_focus_time, 0) / data.focus_trends.length;
            if (avgDailyTime > 1800) { // 30 minutes
                insightsList.push({
                    icon: 'fas fa-calendar-check text-green-500',
                    text: 'Great daily study consistency! Keep up the regular learning habit.'
                });
            }
        }

        // Custom insight from backend
        if (insights.improvement_suggestion) {
            insightsList.push({
                icon: 'fas fa-brain text-purple-500',
                text: insights.improvement_suggestion
            });
        }

        // Update insights display
        insightsContainer.innerHTML = insightsList.map(insight => `
            <li class="flex items-start">
                <i class="${insight.icon} mt-1 mr-2"></i>
                <span class="text-sm text-gray-700">${insight.text}</span>
            </li>
        `).join('');

        // Update main insight text
        const insightTextElement = document.getElementById('insight-text');
        if (insightTextElement && insightsList.length > 0) {
            insightTextElement.textContent = insightsList[0].text;
        }
    }

    function refreshAnalytics() {
        loadAnalytics();
    }

    // Load analytics when page loads and start real-time updates
    document.addEventListener('DOMContentLoaded', function() {
        loadAnalytics();
        startRealtimeUpdates();
    });

    // Stop real-time updates when leaving the page
    window.addEventListener('beforeunload', function() {
        stopRealtimeUpdates();
    });

    // Also stop updates when page becomes hidden (mobile/tab switching)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopRealtimeUpdates();
        } else {
            loadAnalytics(); // Refresh data when coming back
            startRealtimeUpdates();
        }
    });
</script>
</body>
</html>