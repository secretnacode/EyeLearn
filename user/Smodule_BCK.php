<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../loginpage.php");
    exit();
}

// Add database connection
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
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

// Add this right after database connection
$table_check = "ALTER TABLE user_module_progress 
                ADD COLUMN IF NOT EXISTS last_accessed 
                TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
                ON UPDATE CURRENT_TIMESTAMP";
$conn->query($table_check);

// Update the modules query
$modules_query = "
    SELECT 
        m.*,
        ump.completed_sections,
        COALESCE(ump.last_accessed, m.created_at) as last_accessed,
        (SELECT COUNT(*) 
         FROM module_sections ms 
         JOIN module_parts mp ON ms.module_part_id = mp.id 
         WHERE mp.module_id = m.id) as total_sections
    FROM modules m
    LEFT JOIN user_module_progress ump ON m.id = ump.module_id AND ump.user_id = ?
    WHERE m.status = 'published'
    ORDER BY COALESCE(ump.last_accessed, m.created_at) DESC, m.created_at DESC
";

$stmt = $conn->prepare($modules_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$in_progress_modules = [];
$completed_modules = [];
$available_modules = [];

while ($module = $result->fetch_assoc()) {
    // Calculate progress
    $completed_sections = json_decode($module['completed_sections'] ?? '[]', true);
    $total_sections = max(1, $module['total_sections']); // Prevent division by zero
    $progress = round((count($completed_sections) / $total_sections) * 100);
    
    // Add progress info to module
    $module['progress'] = $progress;
    $module['completed_count'] = count($completed_sections);
    
    // Sort into appropriate array
    if ($module['completed_sections'] !== null) {
        if ($progress == 100) {
            $completed_modules[] = $module;
        } else {
            $in_progress_modules[] = $module;
        }
    } else {
        $available_modules[] = $module;
    }
}

// Handle module start action
if (isset($_POST['start_module'])) {
    $module_id = intval($_POST['module_id']);
    
    // Insert initial progress record with current timestamp
    $stmt = $conn->prepare("
        INSERT INTO user_module_progress 
            (user_id, module_id, completed_sections, last_accessed) 
        VALUES 
            (?, ?, '[]', NOW())
        ON DUPLICATE KEY UPDATE 
            last_accessed = NOW()
    ");
    
    $stmt->bind_param("ii", $user_id, $module_id);
    $stmt->execute();
    
    // Redirect to module content
    header("Location: Smodulepart.php?module_id=" . $module_id);
    exit();
}

// Add this function after database connection
function timeAgo($timestamp) {
    if (!$timestamp) return 'Never';
    
    $diff = time() - strtotime($timestamp);
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = round($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
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
            <!-- Enhanced CV Eye Tracking System -->
        <script src="js/cv-eye-tracking.js?service_reconnect_<?php echo time(); ?>"></script>
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
            pointer-events: auto;
        }        .profile-dropdown.show {
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
                    <span class="hidden md:inline-block font-medium text-gray-700"><?php echo htmlspecialchars($user_display_name); ?></span>
                </button>
                
                <!-- Dropdown menu -->
                <div id="profile-dropdown" class="profile-dropdown">
                <div class="p-4 border-b">
    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user_display_name); ?></p>
    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user_email); ?></p>
</div>
                    <div class="p-2">
                        <a href="#" onclick="openProfileModal()" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded">Your Profile</a>
                        <a href="../logout.php" onclick="handleLogout(event)" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded">Logout</a>
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
    <!-- Live Feed Box (Hidden by default, shown when tracking) -->
    <div id="live-feed-container" style="position: fixed; top: 80px; right: 20px; z-index: 1000; background: white; border: 2px solid #007bff; border-radius: 8px; padding: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); display: none;">
        <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #007bff;">Live Camera Feed</h4>
        <img id="tracking-video" alt="Camera feed will appear here" style="width: 320px; height: 240px; background-color: #000; border-radius: 4px;">
        <button onclick="stopModuleTracking()" style="width: 100%; margin-top: 10px; padding: 5px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Stop Tracking</button>
    </div>
    
    <div class="container mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-primary">Learning Modules</h1>
            <div class="hidden md:block">
                <div class="relative">
                    <input type="text" placeholder="Search modules..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50">
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- In Progress Section -->
        <div class="mb-10">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                In Progress
            </h2>
            
            <!-- Module Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (!empty($in_progress_modules)): ?>
                    <?php foreach ($in_progress_modules as $module): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($module['title']); ?></h3>
                            </div>
                            <div class="mb-6">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Progress</span>
                                    <span class="font-medium"><?php echo $module['progress']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: <?php echo $module['progress']; ?>%"></div>
                                </div>
                            </div>
                            <div class="flex items-center text-sm text-gray-500 mb-6">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Last accessed: <?php echo timeAgo($module['last_accessed']); ?></span>
                            </div>
                            <form action="Smodulepart.php" method="get" class="w-full">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <button class="w-full bg-secondary text-white py-2.5 rounded-lg hover:bg-green-600 transition-colors flex items-center justify-center font-medium">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                    </svg>
                                    Continue Learning
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-6 bg-gray-50 rounded-lg text-center">
                        <p class="text-gray-500">No modules in progress. Start learning by selecting a module below!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed Modules Section -->
        <div class="mb-10">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Completed Modules
            </h2>
            
            <!-- Completed Module Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (!empty($completed_modules)): ?>
                    <?php foreach ($completed_modules as $module): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($module['title']); ?></h3>
                            </div>
                            <div class="mb-6">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Completed</span>
                                    <span class="font-medium text-green-600">100%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full w-full"></div>
                                </div>
                            </div>
                            <div class="flex items-center text-sm text-gray-500 mb-6">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Last accessed: <?php echo timeAgo($module['last_accessed']); ?></span>
                            </div>
                            <form action="Smodulepart.php" method="get" class="w-full">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <button class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center font-medium">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Review Module
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-6 bg-gray-50 rounded-lg text-center">
                        <p class="text-gray-500">No completed modules yet. Keep learning!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Modules -->
        <div class="mb-8">
    <h2 class="text-xl font-semibold mb-6 text-gray-800 flex items-center">
        <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Available Modules
    </h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($available_modules as $module): ?>
            <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col">
                <?php if (!empty($module['image_path'])) : ?>
                    <div class="relative h-48 overflow-hidden">
                        <img src="<?php echo htmlspecialchars($module['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($module['title']); ?>" 
                             class="w-full h-full object-cover transition-transform duration-300 hover:scale-105">
                    </div>
                <?php endif; ?>
                
                <div class="p-6 flex flex-col flex-grow">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="text-lg font-semibold text-gray-800 line-clamp-2">
                            <?php echo htmlspecialchars($module['title']); ?>
                        </h3>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-6 line-clamp-3 flex-grow">
                        <?php echo htmlspecialchars($module['description']); ?>
                    </p>
                    
                    <form method="post" class="w-full">
                        <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                        <input type="hidden" name="start_module" value="1">
                        <button type="submit" class="w-full bg-secondary text-white py-2.5 px-4 rounded-lg hover:bg-green-600 transition-colors flex items-center justify-center font-medium group">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                            </svg>
                            Start Learning
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
                </div>
            </div>
        </div>
    </div>
</main>
    </div>
    <!-- Profile Modal -->
    <div id="profileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="relative mx-auto p-8 border w-[480px] shadow-lg rounded-lg bg-white">
            <!-- Profile Header -->
            <div class="text-center mb-6">
                <div class="mx-auto w-24 h-24 bg-primary rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4">
                    <?php echo $initials; ?>
                </div>
                <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($user_display_name); ?></h3>
                <p class="text-gray-500"><?php echo htmlspecialchars($user_email); ?></p>
            </div>

            <!-- Divider -->
            <div class="border-b mb-6"></div>

            <!-- Profile Form -->
            <form id="profileForm" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="firstName">
                            First Name
                        </label>
                        <input type="text" id="firstName" name="first_name" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>"
                               class="shadow-sm border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="lastName">
                            Last Name
                        </label>
                        <input type="text" id="lastName" name="last_name" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>"
                               class="shadow-sm border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="gender">
                        Gender
                    </label>
                    <select id="gender" name="gender" 
                            class="shadow-sm border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>

                <div class="bg-gray-50 -mx-8 -mb-8 px-8 py-4 rounded-b-lg mt-6">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeProfileModal()"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-primary text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300 font-medium">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content -->
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
        e.preventDefault();
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });
    
    // Add handler for logout link
    document.querySelector('#profile-dropdown a[href*="logout"]').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        window.location.href = this.href;
    });
    
    // Close dropdown when clicking elsewhere on the page
    document.addEventListener('click', function(e) {
        if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });
    
    // Function to handle active page styling
    function setActivePage() {
        const currentPage = window.location.pathname.split('/').pop();
        
        // Reset all links
        [dashboardItem, modulesItem, assessmentsItem].forEach(item => {
            item.classList.remove('active');
        });
        
        // Highlight active link based on current page
        if (currentPage === 'Sdashboard.php' || currentPage === '' || currentPage === '/') {
            dashboardItem.classList.add('active');
        } else if (currentPage === 'Smodule.php') {
            modulesItem.classList.add('active');
        } else if (currentPage === 'Sassessment.php') {
            assessmentsItem.classList.add('active');
        } else {
            dashboardItem.classList.add('active');
        }
    }

    // Set active page on load
    setActivePage();
    
    // Profile modal functions
        function openProfileModal() {
            document.getElementById('profileModal').classList.remove('hidden');
            document.getElementById('profile-dropdown').classList.remove('show');
        }

        function closeProfileModal() {
            document.getElementById('profileModal').classList.add('hidden');
        }

        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('profile_update.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI with new values
                    const fullName = `${formData.get('first_name')} ${formData.get('last_name')}`;
                    document.querySelector('#profile-toggle .font-medium').textContent = fullName;
                    document.querySelector('#profile-toggle .bg-primary').textContent = result.data.initials;
                    document.querySelector('#profile-dropdown .font-medium').textContent = fullName;
                    
                    // Update modal header
                    const modalHeader = document.querySelector('#profileModal .text-xl');
                    if (modalHeader) {
                        modalHeader.textContent = fullName;
                    }
                    
                    closeProfileModal();
                    
                    // Show success notification
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg';
                    notification.textContent = 'Profile updated successfully!';
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 3000);
                    
                } else {
                    throw new Error(result.message || 'Failed to update profile');
                }
            } catch (error) {
                console.error('Error:', error);
                alert(error.message || 'An error occurred while updating profile');
            }
        });

        // Update the profile link click handler
        document.querySelector('#profile-dropdown a[href="#"]').addEventListener('click', function(e) {
            e.preventDefault();
            openProfileModal();
        });

        // Close modal when clicking outside
        document.getElementById('profileModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProfileModal();
            }
        });

        // Add this function near your other JavaScript code
function handleLogout(e) {
    e.preventDefault();
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '../logout.php';
    }
}
    </script>
</body>
</html>