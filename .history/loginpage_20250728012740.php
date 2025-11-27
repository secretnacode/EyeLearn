<?php
require_once 'config.php';

$error = '';
$success = '';

// Check for camera agreement decline
if (isset($_GET['error']) && $_GET['error'] === 'camera_declined') {
    $error = 'Camera permission is required to access the learning modules. Please accept the camera agreement to continue.';
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Authenticate user
        $user = authenticateUser($email, $password, $pdo);
        
        if ($user) {
        // Create user session
            createUserSession($user);
            
            // Redirect based on role
         if ($user['role'] === 'admin') {
            header('Location: admin/Adashboard.php'); // Admin Dashboard
            exit;
        } else {
            // Check if user has accepted camera agreement
            if (isset($user['camera_agreement_accepted']) && $user['camera_agreement_accepted'] == 1) {
                header('Location: user/Sdashboard.php'); // Regular users - direct to dashboard
            } else {
                header('Location: user/camera_agreement.php'); // Redirect to camera agreement first
            }
            exit;
        }

        } else {
            $error = 'Invalid email or password';
        }
    }
}

// Check if there's a registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EyeLearn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#4F46E5',
                        'secondary': '#10B981',
                        'accent': '#F97316',
                        'background': '#F9FAFB'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-background min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="md:flex">
            <div class="w-full p-6">
                <div class="flex justify-center mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-eye text-primary text-3xl mr-2"></i>
                        <h1 class="text-3xl font-bold text-primary">EyeLearn</h1>
                    </div>
                </div>
                
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Sign In to Your Account</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="loginpage.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                            Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input class="appearance-none border rounded-lg w-full py-3 px-4 pl-10 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-primary" 
                                   id="email" type="email" name="email" placeholder="your@email.com" required>
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input class="appearance-none border rounded-lg w-full py-3 px-4 pl-10 pr-10 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-primary" 
                                   id="password" type="password" name="password" placeholder="••••••••" required>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <i class="fas fa-eye" id="passwordIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="text-sm text-primary hover:text-indigo-700">Forgot password?</a>
                    </div>
                    
                    <div class="mb-6">
                        <button class="w-full bg-primary hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition-colors duration-200" 
                                type="submit">
                            Sign In
                        </button>
                    </div>
                </form>
                
                <div class="text-center">
                    <p class="text-gray-600 text-sm">
                        Need an account? 
                        <a href="register.php" class="text-primary hover:text-indigo-700 font-medium">Sign up</a>
                    </p>
                </div>
                
                <div class="mt-8 text-center">
                    <p class="text-xs text-gray-500">
                        By using EyeLearn, you agree to our Terms of Service and Privacy Policy
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            togglePassword.addEventListener('click', function() {
                // Toggle the password field type
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle the eye icon
                if (type === 'password') {
                    passwordIcon.classList.remove('fa-eye-slash');
                    passwordIcon.classList.add('fa-eye');
                } else {
                    passwordIcon.classList.remove('fa-eye');
                    passwordIcon.classList.add('fa-eye-slash');
                }
            });
        });
    </script>
</body>
</html>