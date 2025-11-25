<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login_form.php");
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

// Get selected module ID from URL and validate it
$selected_module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : null;

// Validate module_id
if (!$selected_module_id || $selected_module_id <= 0) {
    $_SESSION['error'] = "Invalid module selected.";
    header("Location: Smodule.php");
    exit;
}

// Get all modules with their parts and sections in one efficient query
$modules_query = "
    SELECT 
        m.id AS module_id, m.title AS module_title,
        mp.id AS part_id, mp.title AS part_title,
        ms.id AS section_id, ms.subtitle AS section_title, ms.content AS section_content,
        ms.has_quiz
    FROM modules m
    LEFT JOIN module_parts mp ON m.id = mp.module_id
    LEFT JOIN module_sections ms ON mp.id = ms.module_part_id
    WHERE m.id = ?
    ORDER BY m.id, mp.id, ms.id
";

$stmt = $conn->prepare($modules_query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind the parameter
$stmt->bind_param("i", $selected_module_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$modules_result = $stmt->get_result();
if (!$modules_result) {
    die("Get result failed: " . $stmt->error);
}

// Organize data into hierarchical structure
$modules = [];
$current_module = null;
$current_part = null;

// For sequential navigation
$all_sections = [];
$current_section_index = -1;

while ($row = $modules_result->fetch_assoc()) {
    // New module
    if ($current_module !== $row['module_id']) {
        $current_module = $row['module_id'];
        $modules[$current_module] = [
            'id' => $row['module_id'],
            'title' => $row['module_title'],
            'parts' => []
        ];
        $current_part = null;
    }
    
    // New part within current module
    if ($row['part_id'] && $current_part !== $row['part_id']) {
        $current_part = $row['part_id'];
        $modules[$current_module]['parts'][$current_part] = [
            'id' => $row['part_id'],
            'title' => $row['part_title'],
            'sections' => []
        ];
    }
    
    // Add section if exists
    if ($row['section_id']) {
        $section = [
            'id' => $row['section_id'],
            'title' => $row['section_title'],
            'content' => $row['section_content'],
            'has_quiz' => $row['has_quiz'],
            'part_id' => $current_part
        ];
        
        $modules[$current_module]['parts'][$current_part]['sections'][$row['section_id']] = $section;
        
        // Add to flat list of all sections for navigation
        $all_sections[] = $section;
    }
}

// Get selected section ID from URL
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;

// Get selected section content
$selected_section = null;
$prev_section = null;
$next_section = null;

// Find current section in flat list
for ($i = 0; $i < count($all_sections); $i++) {
    if ($all_sections[$i]['id'] == $selected_section_id) {
        $selected_section = $all_sections[$i];
        $current_section_index = $i;
        break;
    }
}

// Determine previous and next sections
if ($current_section_index > 0) {
    $prev_section = $all_sections[$current_section_index - 1];
}
if ($current_section_index < count($all_sections) - 1) {
    $next_section = $all_sections[$current_section_index + 1];
}

// If no section selected but we have modules, select first available section
if (!$selected_section && !empty($all_sections)) {
    $selected_section = $all_sections[0];
    $selected_section_id = $selected_section['id'];
    $current_section_index = 0;
    
    // Set next section if there are more sections
    if (count($all_sections) > 1) {
        $next_section = $all_sections[1];
    }
}

// Get quiz questions if viewing a section with quiz
$quiz_questions = [];
if ($selected_section && $selected_section['has_quiz']) {
    $quiz_stmt = $conn->prepare("
        SELECT id, question_text, option1, option2, option3, option4, correct_answer 
        FROM section_quiz_questions 
        WHERE section_id = ?
    ");
    $quiz_stmt->bind_param("i", $selected_section['id']);
    $quiz_stmt->execute();
    $quiz_result = $quiz_stmt->get_result();
    
    if ($quiz_result && $quiz_result->num_rows > 0) {
        while ($question = $quiz_result->fetch_assoc()) {
            $quiz_questions[] = $question;
        }
    }
    $quiz_stmt->close();
}

// Get selected quiz ID from URL if it exists
$selected_quiz_id = isset($_GET['final_quiz']) ? intval($_GET['final_quiz']) : null;

// Get final quiz questions if final quiz is selected
$final_quiz_questions = [];
if ($selected_quiz_id) {
    $final_quiz_query = "
        SELECT id, question_text, option1, option2, option3, option4, correct_answer 
        FROM final_quiz_questions 
        WHERE quiz_id = ?
        ORDER BY id
    ";
    $quiz_stmt = $conn->prepare($final_quiz_query);
    $quiz_stmt->bind_param("i", $selected_quiz_id);
    $quiz_stmt->execute();
    $quiz_result = $quiz_stmt->get_result();
    
    if ($quiz_result && $quiz_result->num_rows > 0) {
        while ($question = $quiz_result->fetch_assoc()) {
            $final_quiz_questions[] = $question;
        }
    }
    $quiz_stmt->close();
}

// Get final quiz for this module
$final_quiz_query = "SELECT id, title FROM final_quizzes WHERE module_id = ?";
$quiz_stmt = $conn->prepare($final_quiz_query);
$quiz_stmt->bind_param("i", $selected_module_id);
$quiz_stmt->execute();
$final_quiz = $quiz_stmt->get_result()->fetch_assoc();
$quiz_stmt->close();

// Calculate completion percentage (placeholder)
$completion_percentage = 60;

// Close database connection
$is_module_completed = false;

// Check if this is the last section and all previous sections are completed
if ($selected_section && $current_section_index === count($all_sections) - 1) {
    // For now just set it to true since we don't have section completion tracking yet
    // TODO: Add proper section completion tracking
    $is_module_completed = true;
}

// Get user's progress for this module
try {
    $progress_query = "SELECT completed_sections FROM user_module_progress 
                      WHERE user_id = ? AND module_id = ?";
    $stmt = $conn->prepare($progress_query);
    $stmt->bind_param("ii", $user_id, $selected_module_id);
    $stmt->execute();
    $progress_result = $stmt->get_result();

    $completed_sections = [];
    if ($progress_result->num_rows > 0) {
        $progress_data = $progress_result->fetch_assoc();
        $completed_sections = json_decode($progress_data['completed_sections'], true) ?? [];
    } else {
        // Create progress record if doesn't exist
        $stmt = $conn->prepare("INSERT INTO user_module_progress (user_id, module_id, completed_sections) VALUES (?, ?, '[]')");
        $stmt->bind_param("ii", $user_id, $selected_module_id);
        $stmt->execute();
    }
} catch (mysqli_sql_exception $e) {
    // If table doesn't exist, set default values
    $completed_sections = [];
}

// Calculate completion percentage with error handling
$total_sections = count($all_sections);
$completed_count = count($completed_sections);
$completion_percentage = $total_sections > 0 ? round(($completed_count / $total_sections) * 100) : 0;

// Check if module is completed
$is_module_completed = ($completed_count === $total_sections);

// Add AJAX endpoint for updating progress
if (isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    $section_id = intval($_POST['section_id']);
    if (!in_array($section_id, $completed_sections)) {
        $completed_sections[] = $section_id;
        $completed_json = json_encode($completed_sections);
        
        $update_stmt = $conn->prepare("UPDATE user_module_progress 
                                     SET completed_sections = ? 
                                     WHERE user_id = ? AND module_id = ?");
        $update_stmt->bind_param("sii", $completed_json, $user_id, $selected_module_id);
        $update_stmt->execute();
        
        echo json_encode(['success' => true, 'completion' => $completion_percentage]);
        exit;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EyeLearn - Module View</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/src/output.css">
    <script src="js/cv-eye-tracking.js"></script>
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
         /* Ensure TinyMCE content displays properly */
         .module-content img {
            max-width: 100%;
            height: auto;
        }
        .module-content ul, .module-content ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .module-content h1, .module-content h2, .module-content h3, .module-content h4 {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .module-content h1 { font-size: 1.8rem; }
        .module-content h2 { font-size: 1.5rem; }
        .module-content h3 { font-size: 1.3rem; }
        .module-content h4 { font-size: 1.1rem; }
        .module-content p {
            margin-bottom: 1rem;
        }
        .module-content a {
            color: #3B82F6;
            text-decoration: underline;
        }
        /* Sidebar styling */
        .sidebar {
            width: 280px;
            background-color: white;
            transition: all 0.3s ease;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
        }
        
        .sidebar-collapsed {
            width: 0;
            overflow: hidden;
        }
        
        /* Module sidebar specific */
        .module-item {
            border-bottom: 1px solid #e5e7eb;
        }

        .module-header {
            padding: 1rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .module-header:hover {
            background-color: #f8fafc;
        }

        .part-item {
            border-left: 3px solid #e5e7eb;
        }

        .part-header {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .part-header:hover {
            background-color: #f8fafc;
        }

        .section-item {
            padding: 0.75rem 1rem;
            padding-left: 2.5rem;
            transition: all 0.2s ease;
        }

        .section-item:hover {
            background-color: #F9FAFB;
        }

        .section-item.active, a[href*="final_quiz"].active {
            background-color: #F0F7FF;
            font-weight: 500;
            border-left: 3px solid #3B82F6;
        }

        .section-item.completed .check-icon {
            color: #10B981;
        }

        .chevron {
            transition: transform 0.2s ease;
        }

        .chevron.rotated {
            transform: rotate(90deg);
        }
        
        /* Content area */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }
        
        .main-content-collapsed {
            margin-left: 0;
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
        
        /* Quiz styling */
        .quiz-container {
            background-color: #f0f9ff;
            border-left: 4px solid #3B82F6;
            margin-top: 2rem;
        }
        
        .quiz-option {
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }
        
        .quiz-option:hover {
            border-color: #3B82F6;
            background-color: #f0f7ff;
        }
        
        .quiz-option.selected {
            border-color: #3B82F6;
            background-color: #e0f2fe;
        }
        
        /* Navigation buttons */
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
        }
        
        .nav-button {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .nav-button.prev {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .nav-button.next {
            background-color: #3B82F6;
            color: white;
        }
        
        .nav-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .nav-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Responsive behavior */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 50;
                height: 100%;
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-visible {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
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
            
            .nav-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-button {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* IBM Skill Build like progress */
        .progress-container {
            position: relative;
            height: 4px;
            background-color: #e5e7eb;
            margin-top: 8px;
        }
        
        .progress-bar {
            position: absolute;
            height: 100%;
            background-color: #0f62fe;
            transition: width 0.3s ease;
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
        
        <!-- Right side - Notifications and Profile -->
        <div class="flex items-center space-x-4">
            <!-- Back to Dashboard -->
            <a href="Sdashboard.php" class="text-gray-700 hover:text-primary flex items-center mr-2">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span class="hidden md:inline">Exit Course</span>
            </a>
            
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
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded">Settings</a>
                        <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="flex min-h-screen pt-16">
        <!-- Mobile backdrop -->
        <div id="backdrop" class="backdrop"></div>
        
        <!-- Module Sidebar -->
        <div id="sidebar" class="sidebar fixed left-0 top-16 h-full shadow-lg z-40 flex flex-col transition-all duration-300 ease-in-out">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800"><?php echo !empty($modules) ? htmlspecialchars(reset($modules)['title']) : 'Learning Content'; ?></h2>
                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs font-medium text-gray-500">Progress</span>
                    <span class="text-xs font-medium text-gray-700"><?php echo $completion_percentage; ?>%</span>
                </div>
                <div class="progress-container mt-1">
                    <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%"></div>
                </div>
            </div>

            <div class="overflow-y-auto flex-1">
                <?php foreach ($modules as $module): ?>
                <div class="module-item">
                    <?php foreach ($module['parts'] as $part): ?>
                    <div class="part-item">
                        <div class="part-header flex items-center justify-between" data-part-id="<?php echo $part['id']; ?>">
                            <h4 class="font-medium ml-2"><?php echo htmlspecialchars($part['title']); ?></h4>
                            <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                        
                        <div class="part-sections" style="display: none;">
                            <?php foreach ($part['sections'] as $section): ?>
                            <a href="?module_id=<?php echo $selected_module_id; ?>&section_id=<?php echo $section['id']; ?>" 
                               class="section-item flex items-center <?php echo ($selected_section_id == $section['id']) ? 'active' : ''; ?>">
                                <span class="check-icon mr-2 <?php echo in_array($section['id'], $completed_sections) ? 'text-green-500' : 'text-gray-300'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </span>
                                <span><?php echo htmlspecialchars($section['title']); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Final Quiz Section -->
                    <?php if ($final_quiz): ?>
                    <a href="?module_id=<?php echo $selected_module_id; ?>&final_quiz=<?php echo $final_quiz['id']; ?>" 
                       class="flex items-center px-6 py-3 hover:bg-gray-50 transition-colors border-t border-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium text-gray-800">Final Quiz: <?php echo htmlspecialchars($final_quiz['title']); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <main id="main-content" class="main-content flex-1 p-6 md:p-8 transition-all duration-300 mt-16">
        <?php if ($selected_quiz_id): ?>
            <div class="max-w-4xl mx-auto">
                <div class="quiz-container p-6 rounded-lg">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800">Final Quiz: <?php echo htmlspecialchars($final_quiz['title']); ?></h2>
                    
                    <form id="final-quiz-form" class="space-y-6">
                        <?php foreach ($final_quiz_questions as $index => $question): ?>
                        <div class="quiz-question" data-question-id="<?php echo $question['id']; ?>">
                            <h3 class="font-medium text-lg mb-3"><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></h3>
                            
                            <div class="space-y-2">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="quiz-option flex items-center p-3 rounded-md cursor-pointer" data-option="<?php echo $i; ?>">
                                    <input type="radio" id="fq<?php echo $question['id']; ?>_opt<?php echo $i; ?>" 
                                           name="question_<?php echo $question['id']; ?>" 
                                           value="<?php echo $i; ?>" 
                                           class="mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <label for="fq<?php echo $question['id']; ?>_opt<?php echo $i; ?>" class="flex-1 cursor-pointer">
                                        <?php echo htmlspecialchars($question['option' . $i]); ?>
                                    </label>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-8">
                            <button type="button" id="submit-final-quiz" class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Submit Final Quiz
                            </button>
                        </div>
                    </form>
                    
                    <!-- Quiz results will be displayed here -->
                    <div id="final-quiz-results" class="hidden mt-8 p-4 rounded-md border"></div>
                </div>
            </div>
        <?php elseif ($selected_section): ?>
            <div class="max-w-4xl mx-auto">
                <!-- Section content -->
                <div class="module-content prose max-w-none">
                    <?php echo $selected_section['content']; ?>
                </div>

                <!-- Quiz section if available -->
                <?php if ($selected_section['has_quiz'] && !empty($quiz_questions)): ?>
                <div id="quiz" class="quiz-container mt-8 p-6 rounded-lg">
                    <h2 class="text-xl font-bold mb-6 text-gray-800">Quiz: Test Your Knowledge</h2>
                    
                    <form id="quiz-form" class="space-y-6">
                        <?php foreach ($quiz_questions as $index => $question): ?>
                        <div class="quiz-question" data-question-id="<?php echo $question['id']; ?>">
                            <h3 class="font-medium text-lg mb-3"><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></h3>
                            
                            <div class="space-y-2">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="quiz-option flex items-center p-3 rounded-md cursor-pointer" data-option="<?php echo $i; ?>">
                                    <input type="radio" id="q<?php echo $question['id']; ?>_opt<?php echo $i; ?>" 
                                           name="question_<?php echo $question['id']; ?>" 
                                           value="<?php echo $i; ?>" 
                                           class="mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <label for="q<?php echo $question['id']; ?>_opt<?php echo $i; ?>" class="flex-1 cursor-pointer">
                                        <?php echo htmlspecialchars($question['option' . $i]); ?>
                                    </label>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-8">
                            <button type="button" id="submit-quiz" class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Submit Answers
                            </button>
                        </div>
                    </form>
                    
                    <!-- Quiz results will be displayed here -->
                    <div id="quiz-results" class="hidden mt-8 p-4 rounded-md border"></div>
                </div>
                <?php endif; ?>
                
                <!-- Navigation buttons -->
                <div class="nav-buttons">
                    <?php if ($prev_section): ?>
                    <a href="?module_id=<?php echo $selected_module_id; ?>&section_id=<?php echo $prev_section['id']; ?>" class="nav-button prev">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Previous: <?php echo htmlspecialchars($prev_section['title']); ?>
                    </a>
                    <?php else: ?>
                    <div></div> <!-- Empty div to maintain flex spacing -->
                    <?php endif; ?>
                    
                    <?php if ($next_section): ?>
                    <a href="?module_id=<?php echo $selected_module_id; ?>&section_id=<?php echo $next_section['id']; ?>" 
                       class="nav-button next" id="next-section-btn">
                        Next: <?php echo htmlspecialchars($next_section['title']); ?>
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                    <?php elseif ($is_module_completed && $final_quiz): ?>
                    <a href="?module_id=<?php echo $selected_module_id; ?>&final_quiz=<?php echo $final_quiz['id']; ?>" 
                       class="nav-button next">
                        Take Final Quiz
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 12h14"></path>
                        </svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full text-center p-8">
                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <h2 class="text-xl font-bold text-gray-800 mb-2">No Section Selected</h2>
                <p class="text-gray-600 mb-4">Please select a section from the sidebar to view content.</p>
            </div>
        <?php endif; ?>
        </main>
    </div>
    
    <script>
    // Initialize DOM elements
const sidebarEl = document.getElementById('sidebar');
const mainContentEl = document.getElementById('main-content');
const backdropEl = document.getElementById('backdrop');
const toggleSidebarBtn = document.getElementById('toggle-sidebar');
const partHeaders = document.querySelectorAll('.part-header');

// State management
let isSidebarVisible = true;
const isMobile = () => window.innerWidth < 768;

// Sidebar toggle function
function toggleSidebar() {
    if (isMobile()) {
        sidebarEl.classList.toggle('mobile-visible');
        backdropEl.classList.toggle('active');
    } else {
        isSidebarVisible = !isSidebarVisible;
        sidebarEl.classList.toggle('sidebar-collapsed', !isSidebarVisible);
        mainContentEl.classList.toggle('main-content-collapsed', !isSidebarVisible);
        localStorage.setItem('sidebarVisible', isSidebarVisible);
    }
}

// Part sections toggle function
function togglePartSection(header, force = null) {
    const partItem = header.closest('.part-item');
    const sectionsContainer = partItem.querySelector('.part-sections');
    const chevron = header.querySelector('.chevron');
    
    // Close other sections
    document.querySelectorAll('.part-sections').forEach(section => {
        if (section !== sectionsContainer) {
            section.style.display = 'none';
            section.closest('.part-item').querySelector('.chevron').classList.remove('rotated');
        }
    });

    // Toggle current section
    const isVisible = force !== null ? force : sectionsContainer.style.display !== 'block';
    sectionsContainer.style.display = isVisible ? 'block' : 'none';
    chevron.classList.toggle('rotated', isVisible);
}

// Auto-expand current section's part or final quiz
<?php if ($selected_section_id): ?>
const currentPartId = <?php echo json_encode($selected_section['part_id']); ?>;
const currentPartHeader = document.querySelector(`.part-header[data-part-id="${currentPartId}"]`);
if (currentPartHeader) {
    togglePartSection(currentPartHeader, true);
}
<?php endif; ?>


// Handle final quiz active state
<?php if ($selected_quiz_id): ?>
const finalQuizLink = document.querySelector('a[href*="final_quiz"]');
if (finalQuizLink) {
    // Remove active class from all sections
    document.querySelectorAll('.section-item').forEach(section => {
        section.classList.remove('active');
    });
    // Add active class to final quiz link
    finalQuizLink.classList.add('active');
}
<?php endif; ?>

// Event Listeners
toggleSidebarBtn.addEventListener('click', toggleSidebar);
backdropEl.addEventListener('click', () => {
    sidebarEl.classList.remove('mobile-visible');
    backdropEl.classList.remove('active');
});

// Handle part header clicks
partHeaders.forEach(header => {
    header.addEventListener('click', (e) => {
        e.preventDefault();
        togglePartSection(header);
    });
});

// Handle window resize
window.addEventListener('resize', () => {
    if (!isMobile()) {
        sidebarEl.classList.remove('mobile-visible');
        backdropEl.classList.remove('active');
        
        // Restore desktop state
        const savedState = localStorage.getItem('sidebarVisible');
        if (savedState !== null) {
            isSidebarVisible = savedState === 'true';
            sidebarEl.classList.toggle('sidebar-collapsed', !isSidebarVisible);
            mainContentEl.classList.toggle('main-content-collapsed', !isSidebarVisible);
        }
    } else {
        sidebarEl.classList.remove('sidebar-collapsed');
        mainContentEl.classList.remove('main-content-collapsed');
    }
});

// Quiz functionality
if (document.getElementById('submit-quiz')) {
    const quizOptions = document.querySelectorAll('#quiz-form .quiz-option');
    const submitQuizBtn = document.getElementById('submit-quiz');
    const quizResults = document.getElementById('quiz-results');
    
    // Make entire option clickable
    quizOptions.forEach(option => {
        option.addEventListener('click', () => {
            const radioInput = option.querySelector('input[type="radio"]');
            radioInput.checked = true;
            
            // Remove selected class from all options in the same question
            const questionDiv = option.closest('.quiz-question');
            const options = questionDiv.querySelectorAll('.quiz-option');
            options.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to this option
            option.classList.add('selected');
        });
    });
    
    // Submit quiz button functionality
    submitQuizBtn.addEventListener('click', () => {
        const questions = document.querySelectorAll('#quiz-form .quiz-question');
        let correctAnswers = 0;
        let totalQuestions = questions.length;
        
        questions.forEach(question => {
            const questionId = question.dataset.questionId;
            const selectedOption = document.querySelector(`input[name="question_${questionId}"]:checked`);
            
            if (selectedOption) {
                const correctOption = <?php 
                    // Output JavaScript object with correct answers
                    $correctAnswers = [];
                    if (!empty($quiz_questions)) {
                        foreach ($quiz_questions as $q) {
                            $correctAnswers[$q['id']] = intval($q['correct_answer']);
                        }
                    }
                    echo json_encode($correctAnswers);
                ?>;
                
                const selectedValue = parseInt(selectedOption.value);
                if (selectedValue === correctOption[questionId]) {
                    correctAnswers++;
                    
                    // Highlight correct answer
                    const optionDiv = selectedOption.closest('.quiz-option');
                    optionDiv.classList.add('bg-green-100', 'border-green-500');
                } else {
                    // Highlight incorrect answer
                    const optionDiv = selectedOption.closest('.quiz-option');
                    optionDiv.classList.add('bg-red-100', 'border-red-500');
                    
                    // Highlight the correct answer
                    const correctOptionDiv = question.querySelector(`.quiz-option[data-option="${correctOption[questionId]}"]`);
                    if (correctOptionDiv) {
                        correctOptionDiv.classList.add('bg-green-100', 'border-green-500');
                    }
                }
            }
        });
        
        // Display results
        const percentageScore = Math.round((correctAnswers / totalQuestions) * 100);
        quizResults.innerHTML = `
            <div class="text-lg font-medium mb-2">Quiz Results</div>
            <p>You answered ${correctAnswers} out of ${totalQuestions} questions correctly.</p>
            <p class="font-bold mt-2">Score: ${percentageScore}%</p>
        `;
        
        // Add appropriate class based on score
        if (percentageScore >= 80) {
            quizResults.classList.add('bg-green-50', 'text-green-800', 'border-green-500');
        } else if (percentageScore >= 60) {
            quizResults.classList.add('bg-yellow-50', 'text-yellow-800', 'border-yellow-500');
        } else {
            quizResults.classList.add('bg-red-50', 'text-red-800', 'border-red-500');
        }
        
        quizResults.classList.remove('hidden');
        
        // Disable form after submission
        submitQuizBtn.disabled = true;
        submitQuizBtn.classList.add('opacity-50', 'cursor-not-allowed');
        
        // Disable all radio buttons
        const allRadios = document.querySelectorAll('#quiz-form input[type="radio"]');
        allRadios.forEach(radio => {
            radio.disabled = true;
        });
        
        // Remove clickable style from options
        quizOptions.forEach(option => {
            option.classList.remove('cursor-pointer');
            option.classList.add('cursor-default');
        });
    });
}

// Final Quiz functionality
if (document.getElementById('submit-final-quiz')) {
    const quizOptions = document.querySelectorAll('#final-quiz-form .quiz-option');
    const submitQuizBtn = document.getElementById('submit-final-quiz');
    const quizResults = document.getElementById('final-quiz-results');
    
    // Make entire option clickable
    quizOptions.forEach(option => {
        option.addEventListener('click', () => {
            const radioInput = option.querySelector('input[type="radio"]');
            radioInput.checked = true;
            
            // Remove selected class from all options in the same question
            const questionDiv = option.closest('.quiz-question');
            const options = questionDiv.querySelectorAll('.quiz-option');
            options.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to this option
            option.classList.add('selected');
        });
    });
    
    // Submit quiz button functionality
    submitQuizBtn.addEventListener('click', () => {
        const questions = document.querySelectorAll('#final-quiz-form .quiz-question');
        let correctAnswers = 0;
        let totalQuestions = questions.length;
        
        questions.forEach(question => {
            const questionId = question.dataset.questionId;
            const selectedOption = document.querySelector(`input[name="question_${questionId}"]:checked`);
            
            if (selectedOption) {
                const correctOption = <?php 
                    // Output JavaScript object with correct answers
                    $correctAnswers = [];
                    if (!empty($final_quiz_questions)) {
                        foreach ($final_quiz_questions as $q) {
                            $correctAnswers[$q['id']] = intval($q['correct_answer']);
                        }
                    }
                    echo json_encode($correctAnswers);
                ?>;
                
                const selectedValue = parseInt(selectedOption.value);
                if (selectedValue === correctOption[questionId]) {
                    correctAnswers++;
                    
                    // Highlight correct answer
                    const optionDiv = selectedOption.closest('.quiz-option');
                    optionDiv.classList.add('bg-green-100', 'border-green-500');
                } else {
                    // Highlight incorrect answer
                    const optionDiv = selectedOption.closest('.quiz-option');
                    optionDiv.classList.add('bg-red-100', 'border-red-500');
                    
                    // Highlight the correct answer
                    const correctOptionDiv = question.querySelector(`.quiz-option[data-option="${correctOption[questionId]}"]`);
                    if (correctOptionDiv) {
                        correctOptionDiv.classList.add('bg-green-100', 'border-green-500');
                    }
                }
            }
        });
        
        // Display results
        const percentageScore = Math.round((correctAnswers / totalQuestions) * 100);
        quizResults.innerHTML = `
            <div class="text-lg font-medium mb-2">Final Quiz Results</div>
            <p>You answered ${correctAnswers} out of ${totalQuestions} questions correctly.</p>
            <p class="font-bold mt-2">Score: ${percentageScore}%</p>
        `;
        
        // Add appropriate class based on score
        if (percentageScore >= 80) {
            quizResults.classList.add('bg-green-50', 'text-green-800', 'border-green-500');
        } else if (percentageScore >= 60) {
            quizResults.classList.add('bg-yellow-50', 'text-yellow-800', 'border-yellow-500');
        } else {
            quizResults.classList.add('bg-red-50', 'text-red-800', 'border-red-500');
        }
        
        quizResults.classList.remove('hidden');
        
        // Disable form after submission
        submitQuizBtn.disabled = true;
        submitQuizBtn.classList.add('opacity-50', 'cursor-not-allowed');
        
        // Disable all radio buttons
        const allRadios = document.querySelectorAll('#final-quiz-form input[type="radio"]');
        allRadios.forEach(radio => {
            radio.disabled = true;
        });
        
        // Remove clickable style from options
        quizOptions.forEach(option => {
            option.classList.remove('cursor-pointer');
            option.classList.add('cursor-default');
        });
    });
}

// Progress tracking functionality
document.getElementById('next-section-btn')?.addEventListener('click', function(e) {
    e.preventDefault();
    const currentSectionId = <?php echo $selected_section_id ?? 'null'; ?>;
    
    if (currentSectionId) {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_progress&section_id=${currentSectionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update progress bar
                document.querySelector('.progress-bar').style.width = `${data.completion}%`;
                document.querySelector('.text-xs.text-gray-700').textContent = `${data.completion}%`;
                
                // Update check mark
                const currentSection = document.querySelector(`.section-item[href*="section_id=${currentSectionId}"] .check-icon`);
                if (currentSection) {
                    currentSection.classList.remove('text-gray-300');
                    currentSection.classList.add('text-green-500');
                }
                
                // Navigate to next section
                window.location.href = this.href;
            }
        });
    }
});

// Final quiz access control
<?php if (!$is_module_completed): ?>
document.querySelector('a[href*="final_quiz"]')?.addEventListener('click', function(e) {
    e.preventDefault();
    alert('Please complete all sections before taking the final quiz.');
});
<?php endif; ?>
    </script>
</body> 
</html>