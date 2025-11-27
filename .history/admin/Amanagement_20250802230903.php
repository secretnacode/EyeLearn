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
        
        /* Modal overlay styling */
        #studentModal {
            backdrop-filter: blur(2px);
            z-index: 9999 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
        }
        
        /* Disable pointer events on elements behind modal */
        body.modal-open {
            overflow: hidden;
        }
        
        body.modal-open #sidebar {
            pointer-events: none !important;
            user-select: none !important;
            filter: brightness(0.7) blur(2px) !important;
        }
        
        body.modal-open .main-content {
            pointer-events: none !important;
            user-select: none !important;
            filter: brightness(0.7) blur(2px) !important;
        }
        
        body.modal-open #sidebar .nav-item {
            pointer-events: none !important;
        }
        
        body.modal-open #sidebar .nav-item:hover {
            background-color: transparent !important;
        }
        
        /* Completely disable all hover effects when modal is open */
        body.modal-open * {
            pointer-events: none !important;
        }
        
        body.modal-open #studentModal,
        body.modal-open #studentModal * {
            pointer-events: auto !important;
        }
        
        /* Override any existing hover states */
        body.modal-open .nav-item,
        body.modal-open .nav-item:hover,
        body.modal-open .nav-item:focus,
        body.modal-open .nav-item:active {
            background-color: transparent !important;
            color: inherit !important;
            transform: none !important;
            box-shadow: none !important;
        }
        
        body.modal-open .nav-item .nav-indicator {
            opacity: 0 !important;
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
                        <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded">Logout</a>
                    </div>
                </div>
            </div>

            <!-- Student Details Modal -->
            <div id="studentModal" class="fixed inset-0 hidden z-[9999]" style="background-color: rgba(0, 0, 0, 0.75);">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl" onclick="event.stopPropagation()">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-2xl font-bold text-gray-800" id="modalStudentName">Student Details</h2>
                                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                            </div>
                            
                            <!-- Student Info -->
                            <div class="mb-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="font-semibold text-lg mb-2">Student Information</h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">Student ID</p>
                                            <p class="font-medium" id="modalStudentId">-</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">Email</p>
                                            <p class="font-medium" id="modalStudentEmail">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Today's Performance -->
                            <div class="mb-6">
                                <h3 class="font-semibold text-lg mb-4">Today's Performance</h3>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="bg-green-50 p-4 rounded-lg text-center">
                                        <p class="text-sm font-medium text-green-700">Focused Time</p>
                                        <p class="text-2xl font-bold text-green-600" id="modalTodayFocused">0 min</p>
                                    </div>
                                    <div class="bg-red-50 p-4 rounded-lg text-center">
                                        <p class="text-sm font-medium text-red-700">Unfocused Time</p>
                                        <p class="text-2xl font-bold text-red-600" id="modalTodayUnfocused">0 min</p>
                                    </div>
                                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                                        <p class="text-sm font-medium text-blue-700">Focus Percentage</p>
                                        <p class="text-2xl font-bold text-blue-600" id="modalTodayPercent">0%</p>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="bg-gray-200 rounded-full h-3">
                                        <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" id="modalTodayBar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Weekly Performance -->
                            <div class="mb-6">
                                <h3 class="font-semibold text-lg mb-4">Weekly Performance</h3>
                                <div class="grid grid-cols-4 gap-4">
                                    <div class="bg-indigo-50 p-4 rounded-lg text-center">
                                        <p class="text-sm font-medium text-indigo-700">Total Focus</p>
                                        <p class="text-xl font-bold text-indigo-600" id="modalWeeklyFocused">0 min</p>
                                    </div>
                                    <div class="bg-orange-50 p-4 rounded-lg text-center">
                                        <p class="text-sm font-medium text-orange-700">Total Sessions</p>
                                        <p class="text-xl font-bold text-orange-600" id="modalWeeklySessions">0</p>
                                    </div>
                                    <div class="bg-emerald-50 p-4 rounded-lg text-center">
                                        <p class="text-sm font-medium text-emerald-700">Focus %</p>
                                        <p class="text-xl font-bold text-emerald-600" id="modalWeeklyPercent">0%</p>
                                    </div>
                                    <div class="bg-purple-50 p-4 rounded-lg text-center">
                                        <p class="text-sm font-medium text-purple-700">Active Days</p>
                                        <p class="text-xl font-bold text-purple-600" id="modalActiveDays">0</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Insights -->
                            <div class="mb-6">
                                <h3 class="font-semibold text-lg mb-4">Insights</h3>
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <ul class="space-y-2" id="modalInsights">
                                        <li>Loading insights...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
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
                </div>
                
                <!-- Search and Filter -->
                <div class="mb-6 flex space-x-4">
                    <input type="text" placeholder="Search students..." class="flex-1 p-2 border rounded">
                    <select class="p-2 border rounded">
                        <option>All Module</option>
                        <option>Computer Science</option>
                        <option>Data Science</option>
                    </select>
                </div>

                <!-- Student Table -->
                <div class="bg-white shadow-md rounded">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-3 text-left">Name</th>
                                <th class="p-3 text-left">Email</th>
                                <th class="p-3 text-left">Progress</th>
                                <th class="p-3 text-left">Today's Focus</th>
                                <th class="p-3 text-left">Total Sessions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="p-4 text-center text-gray-500">Loading students...</td></tr>
                        </tbody>
                    </table>
                </div>
                        </tbody>
                    </table>
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

    // Student Management Functionality
    let allStudents = [];

    async function loadStudents() {
        try {
            const searchInput = document.querySelector('input[placeholder="Search students..."]');
            const search = searchInput ? searchInput.value.trim() : '';
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            
            const response = await fetch(`database/students_minimal.php?${params.toString()}`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch students');
            }
            
            const data = await response.json();
            
            if (data.success) {
                allStudents = data.students;
                displayStudents(allStudents);
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        } catch (error) {
            console.error('Error loading students:', error);
            alert('Error loading students: ' + error.message);
        }
    }

    function displayStudents(students) {
        const tbody = document.querySelector('table tbody');
        if (!tbody) return;

        if (students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-gray-500">No students found</td></tr>';
            return;
        }

        tbody.innerHTML = students.map(student => `
            <tr class="border-b hover:bg-gray-50">
                <td class="p-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-medium">
                            ${getInitials(student.name)}
                        </div>
                        <div>
                            <p class="font-medium">${student.name}</p>
                            <p class="text-sm text-gray-500">${student.student_id}</p>
                        </div>
                    </div>
                </td>
                <td class="p-3">${student.email}</td>
                <td class="p-3">
                    <div class="flex items-center space-x-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: ${student.progress}%"></div>
                        </div>
                        <span class="text-sm font-medium">${student.progress}%</span>
                    </div>
                </td>
                <td class="p-3">
                    <div class="text-center">
                        <div class="mb-1">
                            <span class="text-lg font-semibold text-green-600">${student.today_focus_time}m</span>
                            <span class="text-sm text-gray-400">/</span>
                            <span class="text-lg font-semibold text-red-500">${student.today_unfocus_time}m</span>
                        </div>
                        <div class="w-full bg-red-200 rounded-full h-2 mb-1">
                            <div class="bg-green-500 h-2 rounded-full" style="width: ${student.today_focus_percentage}%"></div>
                        </div>
                        <p class="text-sm text-gray-600">${student.today_focus_percentage}% focused today</p>
                    </div>
                </td>
                <td class="p-3">
                    <div class="text-center">
                        <div class="mb-1">
                            <span class="text-2xl font-bold text-primary">${student.total_sessions}</span>
                        </div>
                        <p class="text-sm text-gray-600">Eye tracking sessions</p>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }

    function viewStudent(studentId) {
        openModal(studentId);
    }

    async function openModal(studentId) {
        const modal = document.getElementById('studentModal');
        const sidebar = document.getElementById('sidebar');
        
        // Add classes for complete isolation
        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
        sidebar.style.pointerEvents = 'none';
        sidebar.style.userSelect = 'none';
        
        // Disable all navigation items
        const navItems = sidebar.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.style.pointerEvents = 'none';
            item.style.userSelect = 'none';
        });
        
        // Show loading state
        document.getElementById('modalStudentName').textContent = 'Loading...';
        
        try {
            const response = await fetch(`database/student_details_minimal.php?student_id=${studentId}`);
            const data = await response.json();
            
            if (data.success) {
                updateModalData(data);
            } else {
                throw new Error(data.error || 'Failed to load student details');
            }
        } catch (error) {
            console.error('Error loading student details:', error);
            alert('Error loading student details: ' + error.message);
            closeModal();
        }
    }

    function updateModalData(data) {
        const { student, today, weekly, insights } = data;
        
        // Update student info
        document.getElementById('modalStudentName').textContent = student.name;
        document.getElementById('modalStudentId').textContent = student.student_id;
        document.getElementById('modalStudentEmail').textContent = student.email;
        
        // Update today's data
        document.getElementById('modalTodayFocused').textContent = today.focused_time + ' min';
        document.getElementById('modalTodayUnfocused').textContent = today.unfocused_time + ' min';
        document.getElementById('modalTodayPercent').textContent = today.focus_percentage + '%';
        document.getElementById('modalTodayBar').style.width = today.focus_percentage + '%';
        
        // Update weekly data
        document.getElementById('modalWeeklyFocused').textContent = weekly.focused_time + ' min';
        document.getElementById('modalWeeklySessions').textContent = weekly.total_sessions;
        document.getElementById('modalWeeklyPercent').textContent = weekly.focus_percentage + '%';
        document.getElementById('modalActiveDays').textContent = weekly.active_days;
        
        // Update insights
        const insightsList = document.getElementById('modalInsights');
        insightsList.innerHTML = insights.map(insight => `<li>${insight}</li>`).join('');
    }

    function closeModal() {
        const modal = document.getElementById('studentModal');
        const sidebar = document.getElementById('sidebar');
        
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
        
        // Re-enable sidebar interactions
        sidebar.style.pointerEvents = '';
        sidebar.style.userSelect = '';
        
        // Re-enable all navigation items
        const navItems = sidebar.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.style.pointerEvents = '';
            item.style.userSelect = '';
        });
    }

    // Close modal when clicking outside
    document.getElementById('studentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('studentModal').classList.contains('hidden')) {
            closeModal();
        }
    });

    // Set up search functionality
    const searchInput = document.querySelector('input[placeholder="Search students..."]');
    if (searchInput) {
        searchInput.addEventListener('input', loadStudents);
    }

    // Load students on page load
    loadStudents();
</script>
</body>
</html>