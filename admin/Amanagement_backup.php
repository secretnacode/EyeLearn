<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1 || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginpage.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeLearn - AI-Enhanced E-Learning System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        A
                    </div>
                    <span class="hidden md:inline-block font-medium text-gray-700">Admin</span>
                </button>
                
                <!-- Dropdown menu -->
                <div id="profile-dropdown" class="profile-dropdown">
                    <div class="p-4 border-b">
                        <p class="font-medium text-gray-800">Admin</p>
                        <p class="text-sm text-gray-500">admin@admin.eyelearn</p>
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
   
         <!-- Main Content -->
         <main id="main-content" class="main-content flex-1 p-6 md:p-8 transition-all duration-300">
            <div class="container mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-primary">Student Management</h1>
                    <div class="flex items-center space-x-4">
                        <span id="student-count" class="text-sm text-gray-600">0 students</span>
                        <button id="refresh-btn" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="mb-6 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <input type="text" id="search-input" placeholder="Search students by name or email..." class="flex-1 p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    <select id="module-filter" class="p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">All Modules</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Data Science">Data Science</option>
                        <option value="Web Development">Web Development</option>
                    </select>
                    <button id="search-btn" class="bg-secondary text-white px-6 py-3 rounded-lg hover:bg-green-600 transition-colors">
                        Search
                    </button>
                </div>

                <!-- Loading State -->
                <div id="loading-state" class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                    <p class="text-gray-600">Loading students...</p>
                </div>

                <!-- Student Table -->
                <div id="students-table" class="bg-white shadow-md rounded-lg overflow-hidden" style="display: none;">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-4 text-left text-sm font-medium text-gray-700">Student</th>
                                    <th class="p-4 text-left text-sm font-medium text-gray-700">Email</th>
                                    <th class="p-4 text-left text-sm font-medium text-gray-700">Progress</th>
                                    <th class="p-4 text-left text-sm font-medium text-gray-700">Focus Time</th>
                                    <th class="p-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="p-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="students-tbody">
                                <!-- Students will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div id="empty-state" class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center" style="display: none;">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Students Found</h3>
                    <p class="text-gray-600">No students match your search criteria.</p>
                </div>
            </div>

            <!-- Student Detail Modal -->
            <div id="student-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" style="display: none;">
                <div class="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-y-auto">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <div class="flex items-center space-x-4">
                            <div id="modal-avatar" class="w-12 h-12 bg-primary rounded-full flex items-center justify-center text-white font-bold text-lg">
                                <!-- Initials will be inserted here -->
                            </div>
                            <div>
                                <h2 id="modal-student-name" class="text-2xl font-bold text-gray-900">Student Name</h2>
                                <p id="modal-student-id" class="text-sm text-gray-600">ST-0000</p>
                            </div>
                        </div>
                        <button id="close-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Content -->
                    <div class="p-6">
                        <!-- Student Info -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-600 mb-1">Email</h3>
                                <p id="modal-email" class="text-lg font-semibold text-gray-900">student@email.com</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-600 mb-1">Gender</h3>
                                <p id="modal-gender" class="text-lg font-semibold text-gray-900">-</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-600 mb-1">Registration Date</h3>
                                <p id="modal-registration" class="text-lg font-semibold text-gray-900">-</p>
                            </div>
                        </div>

                        <!-- Today's Statistics -->
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Today's Performance</h3>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                    <p class="text-sm font-medium text-green-700">Focused Time</p>
                                    <p id="modal-today-focused" class="text-2xl font-bold text-green-600">0 min</p>
                                </div>
                                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                    <p class="text-sm font-medium text-red-700">Unfocused Time</p>
                                    <p id="modal-today-unfocused" class="text-2xl font-bold text-red-600">0 min</p>
                                </div>
                                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                    <p class="text-sm font-medium text-blue-700">Focus %</p>
                                    <p id="modal-today-focus-percentage" class="text-2xl font-bold text-blue-600">0%</p>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                    <p class="text-sm font-medium text-purple-700">Sessions</p>
                                    <p id="modal-today-sessions" class="text-2xl font-bold text-purple-600">0</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <p class="text-sm font-medium text-gray-700">Avg Session</p>
                                    <p id="modal-today-avg-session" class="text-2xl font-bold text-gray-600">0 min</p>
                                </div>
                            </div>
                        </div>

                        <!-- Weekly Overview -->
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-900 mb-4">📈 Weekly Overview</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                                    <p class="text-sm font-medium text-indigo-700">Weekly Focus Time</p>
                                    <p id="modal-weekly-focused" class="text-xl font-bold text-indigo-600">0 min</p>
                                </div>
                                <div class="bg-pink-50 p-4 rounded-lg border border-pink-200">
                                    <p class="text-sm font-medium text-pink-700">Active Days</p>
                                    <p id="modal-weekly-days" class="text-xl font-bold text-pink-600">0</p>
                                </div>
                                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                    <p class="text-sm font-medium text-yellow-700">Total Sessions</p>
                                    <p id="modal-weekly-sessions" class="text-xl font-bold text-yellow-600">0</p>
                                </div>
                                <div class="bg-teal-50 p-4 rounded-lg border border-teal-200">
                                    <p class="text-sm font-medium text-teal-700">Best Study Time</p>
                                    <p id="modal-best-time" class="text-xl font-bold text-teal-600">No data</p>
                                </div>
                            </div>
                        </div>

                        <!-- Module Performance -->
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-900 mb-4">📚 Module Performance</h3>
                            <div id="modal-modules" class="space-y-3">
                                <!-- Module performance data will be loaded here -->
                            </div>
                        </div>

                        <!-- AI Insights -->
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-900 mb-4">🧠 AI Insights</h3>
                            <div id="modal-insights" class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <!-- Insights will be loaded here -->
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 mb-4">⏰ Recent Activity</h3>
                            <div class="bg-gray-50 rounded-lg overflow-hidden">
                                <div id="modal-recent-activity" class="divide-y divide-gray-200">
                                    <!-- Recent activity will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
    
    // Student Management Elements
    const searchInput = document.getElementById('search-input');
    const moduleFilter = document.getElementById('module-filter');
    const searchBtn = document.getElementById('search-btn');
    const refreshBtn = document.getElementById('refresh-btn');
    const studentsTable = document.getElementById('students-table');
    const studentsTbody = document.getElementById('students-tbody');
    const loadingState = document.getElementById('loading-state');
    const emptyState = document.getElementById('empty-state');
    const studentCount = document.getElementById('student-count');
    
    // Modal Elements
    const studentModal = document.getElementById('student-modal');
    const closeModal = document.getElementById('close-modal');
    
    let allStudents = [];
    let currentStudentId = null;
    
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

    // Student Management Functions
    async function loadStudents() {
        showLoading();
        
        try {
            const search = searchInput.value.trim();
            const module = moduleFilter.value;
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (module) params.append('module', module);
            
            const response = await fetch(`database/test_students_simple.php?${params.toString()}`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch students');
            }
            
            const data = await response.json();
            
            if (data.success) {
                allStudents = data.students;
                displayStudents(allStudents);
                updateStudentCount(data.total_count);
            } else {
                throw new Error(data.error || 'Unknown error occurred');
            }
        } catch (error) {
            console.error('Error loading students:', error);
            showError('Failed to load students. Please try again.');
        }
    }
    
    function showLoading() {
        loadingState.style.display = 'block';
        studentsTable.style.display = 'none';
        emptyState.style.display = 'none';
    }
    
    function displayStudents(students) {
        loadingState.style.display = 'none';
        
        if (students.length === 0) {
            emptyState.style.display = 'block';
            studentsTable.style.display = 'none';
            return;
        }
        
        emptyState.style.display = 'none';
        studentsTable.style.display = 'block';
        
        studentsTbody.innerHTML = students.map(student => `
            <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                <td class="p-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-medium">
                            ${getInitials(student.name)}
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">${student.name}</p>
                            <p class="text-sm text-gray-600">${student.student_id}</p>
                        </div>
                    </div>
                </td>
                <td class="p-4">
                    <p class="text-gray-900">${student.email}</p>
                    <p class="text-sm text-gray-600">${student.gender || 'Not specified'}</p>
                </td>
                <td class="p-4">
                    <div class="flex items-center space-x-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width: ${student.progress}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-700">${student.progress}%</span>
                    </div>
                </td>
                <td class="p-4">
                    <div class="text-center">
                        <p class="text-lg font-semibold text-primary">${student.total_focus_time} min</p>
                        <p class="text-sm text-gray-600">${student.focus_percentage}% focused</p>
                    </div>
                </td>
                <td class="p-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(student.activity_status)}">
                        ${student.activity_status}
                    </span>
                </td>
                <td class="p-4">
                    <div class="flex space-x-2">
                        <button onclick="viewStudent(${student.id})" class="bg-primary text-white px-3 py-1.5 rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium">
                            View Details
                        </button>
                        <button onclick="deleteStudent(${student.id}, '${student.name}')" class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition-colors text-sm font-medium">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }
    
    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }
    
    function getStatusColor(status) {
        switch (status) {
            case 'Active': return 'bg-green-100 text-green-800';
            case 'Recent': return 'bg-yellow-100 text-yellow-800';
            case 'Inactive': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }
    
    function updateStudentCount(count) {
        studentCount.textContent = `${count} student${count !== 1 ? 's' : ''}`;
    }
    
    function showError(message) {
        loadingState.style.display = 'none';
        studentsTable.style.display = 'none';
        emptyState.style.display = 'block';
        emptyState.innerHTML = `
            <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Students</h3>
            <p class="text-gray-600">${message}</p>
        `;
    }
    
    async function viewStudent(studentId) {
        currentStudentId = studentId;
        
        try {
            // Find student in our already loaded data
            const student = allStudents.find(s => s.id == studentId);
            
            if (student) {
                displayStudentModal(student);
            } else {
                throw new Error('Student not found');
            }
        } catch (error) {
            console.error('Error loading student details:', error);
            alert('Failed to load student details. Please try again.');
        }
    }
    
    function displayStudentModal(student) {
        // Update modal header
        document.getElementById('modal-avatar').textContent = getInitials(student.name);
        document.getElementById('modal-student-name').textContent = student.name;
        document.getElementById('modal-student-id').textContent = student.id;
        
        // Update student info
        document.getElementById('modal-email').textContent = student.email;
        document.getElementById('modal-gender').textContent = student.gender || 'Not specified';
        document.getElementById('modal-registration').textContent = new Date(student.registration_date).toLocaleDateString();
        
        // Update today's stats (simplified - use defaults for now)
        document.getElementById('modal-today-focused').textContent = '0 min';
        document.getElementById('modal-today-unfocused').textContent = '0 min';
        document.getElementById('modal-today-focus-percentage').textContent = '0%';
        document.getElementById('modal-today-sessions').textContent = '0';
        document.getElementById('modal-today-avg-session').textContent = '0 min';
        
        // Update weekly stats (simplified)
        document.getElementById('modal-weekly-focused').textContent = '0 min';
        document.getElementById('modal-weekly-unfocused').textContent = '0 min';
        document.getElementById('modal-weekly-focus-percentage').textContent = '0%';
        document.getElementById('modal-weekly-sessions').textContent = '0';
        document.getElementById('modal-weekly-avg-session').textContent = '0 min';
        
        // Update modules progress (simplified)
        const modulesContainer = document.getElementById('modal-modules-list');
        modulesContainer.innerHTML = '<p class="text-gray-500">No module data available</p>';
        
        // Update AI insights (simplified)
        document.getElementById('modal-ai-insights').innerHTML = `
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <p class="text-blue-800">🤖 <strong>AI Analysis:</strong> Student data is available. Detailed analytics will be implemented soon.</p>
            </div>
        `;
        
        // Show modal
        studentModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
        document.getElementById('modal-weekly-focused').textContent = formatTime(weekly.focused_time);
        document.getElementById('modal-weekly-days').textContent = weekly.active_days;
        document.getElementById('modal-weekly-sessions').textContent = weekly.total_sessions;
        document.getElementById('modal-best-time').textContent = data.best_study_time;
        
        // Update modules
        const modulesContainer = document.getElementById('modal-modules');
        if (data.module_performance.length > 0) {
            modulesContainer.innerHTML = data.module_performance.map(module => `
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-medium text-gray-900">${module.module_name}</h4>
                        <span class="text-sm font-medium text-green-600">${module.completion_percentage}% complete</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600">Avg Focus Time</p>
                            <p class="font-semibold">${module.avg_focused_minutes} min</p>
                        </div>
                        <div>
                            <p class="text-gray-600">Study Sessions</p>
                            <p class="font-semibold">${module.study_sessions}</p>
                        </div>
                        <div>
                            <p class="text-gray-600">Focus Rate</p>
                            <p class="font-semibold">${module.focus_percentage}%</p>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            modulesContainer.innerHTML = '<p class="text-gray-600 text-center py-4">No module data available</p>';
        }
        
        // Update insights
        const insightsContainer = document.getElementById('modal-insights');
        if (data.insights.length > 0) {
            insightsContainer.innerHTML = data.insights.map(insight => `
                <p class="flex items-start mb-2 last:mb-0">
                    <span class="text-blue-500 mr-2">💡</span>
                    <span class="text-gray-700">${insight}</span>
                </p>
            `).join('');
        } else {
            insightsContainer.innerHTML = '<p class="text-gray-600 text-center">No insights available yet</p>';
        }
        
        // Update recent activity
        const activityContainer = document.getElementById('modal-recent-activity');
        if (data.recent_activity.length > 0) {
            activityContainer.innerHTML = data.recent_activity.map(activity => `
                <div class="p-3 flex justify-between items-center">
                    <div>
                        <p class="font-medium text-gray-900">${activity.module_name}</p>
                        <p class="text-sm text-gray-600">${new Date(activity.date).toLocaleString()}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium">Focus: ${activity.focus_percentage}%</p>
                        <p class="text-xs text-gray-600">${formatTime(activity.total_time)}</p>
                    </div>
                </div>
            `).join('');
        } else {
            activityContainer.innerHTML = '<p class="text-gray-600 text-center p-4">No recent activity</p>';
        }
        
        // Show modal
        studentModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function formatTime(seconds) {
        if (seconds < 60) {
            return `${seconds}s`;
        } else if (seconds < 3600) {
            return `${Math.round(seconds / 60)} min`;
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.round((seconds % 3600) / 60);
            return `${hours}h ${minutes}m`;
        }
    }
    
    async function deleteStudent(studentId, studentName) {
        if (!confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone and will remove all their data.`)) {
            return;
        }
        
        try {
            const response = await fetch('database/get_students.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ student_id: studentId })
            });
            
            if (!response.ok) {
                throw new Error('Failed to delete student');
            }
            
            const data = await response.json();
            
            if (data.success) {
                alert('Student deleted successfully');
                loadStudents(); // Reload the list
            } else {
                throw new Error(data.error || 'Unknown error occurred');
            }
        } catch (error) {
            console.error('Error deleting student:', error);
            alert('Failed to delete student. Please try again.');
        }
    }
    
    function closeStudentModal() {
        studentModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        currentStudentId = null;
    }
    
    // Event Listeners
    searchBtn.addEventListener('click', loadStudents);
    refreshBtn.addEventListener('click', loadStudents);
    closeModal.addEventListener('click', closeStudentModal);
    
    // Close modal when clicking outside
    studentModal.addEventListener('click', (e) => {
        if (e.target === studentModal) {
            closeStudentModal();
        }
    });
    
    // Search on Enter key
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            loadStudents();
        }
    });
    
    // Auto-refresh every 30 seconds
    setInterval(loadStudents, 30000);
    
    // Initial load
    document.addEventListener('DOMContentLoaded', loadStudents);
</script>
</body>
</html>