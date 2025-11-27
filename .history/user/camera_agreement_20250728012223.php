<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginpage.php');
    exit;
}

// Check if camera agreement is already accepted
if (isset($_SESSION['camera_agreement_accepted']) && $_SESSION['camera_agreement_accepted'] === true) {
    header('Location: Sdashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept_agreement'])) {
        $_SESSION['camera_agreement_accepted'] = true;
        
        // Save agreement to database
        $conn = new mysqli('localhost', 'root', '', 'elearn_db');
        if (!$conn->connect_error) {
            $user_id = $_SESSION['user_id'];
            $sql = "UPDATE users SET camera_agreement_accepted = 1, camera_agreement_date = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $conn->close();
        }
        
        header('Location: Sdashboard.php');
        exit;
    } elseif (isset($_POST['decline_agreement'])) {
        // User declined - logout and redirect to login
        session_destroy();
        header('Location: ../loginpage.php?error=camera_declined');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Agreement - EyeLearn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#3B82F6',
                        'secondary': '#10B981',
                        'background': '#F9FAFB'
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-background min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <div class="text-center">
                <div class="mx-auto h-20 w-20 gradient-bg rounded-full flex items-center justify-center mb-6">
                    <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Camera Permission Agreement</h2>
                <p class="text-lg text-gray-600">Enhanced Eye Tracking for Better Learning</p>
            </div>

            <div class="bg-white rounded-lg shadow-xl p-8">
                <div class="space-y-6">
                    <!-- Eye Tracking Information -->
                    <div class="border-l-4 border-primary pl-4">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">🎯 How Eye Tracking Works</h3>
                        <p class="text-gray-700">
                            Our AI-powered eye tracking system monitors your focus and attention during learning sessions to provide personalized insights and improve your learning experience.
                        </p>
                    </div>

                    <!-- Privacy Information -->
                    <div class="border-l-4 border-secondary pl-4">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">🔒 Your Privacy is Protected</h3>
                        <ul class="text-gray-700 space-y-2">
                            <li class="flex items-start">
                                <span class="text-secondary mr-2">✓</span>
                                <span>Camera data is processed locally on your device</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-secondary mr-2">✓</span>
                                <span>No video recordings are stored or transmitted</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-secondary mr-2">✓</span>
                                <span>Only focus metrics are saved for learning analytics</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-secondary mr-2">✓</span>
                                <span>You can disable tracking at any time</span>
                            </li>
                        </ul>
                    </div>

                    <!-- What We Track -->
                    <div class="border-l-4 border-yellow-400 pl-4">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">📊 What We Measure</h3>
                        <div class="grid md:grid-cols-2 gap-4 text-gray-700">
                            <div>
                                <h4 class="font-medium">Focus Metrics:</h4>
                                <ul class="text-sm space-y-1 mt-1">
                                    <li>• Time spent focused on content</li>
                                    <li>• Attention patterns</li>
                                    <li>• Learning engagement levels</li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="font-medium">Learning Analytics:</h4>
                                <ul class="text-sm space-y-1 mt-1">
                                    <li>• Module completion times</li>
                                    <li>• Comprehension indicators</li>
                                    <li>• Personalized recommendations</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Browser Permission Notice -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <svg class="h-5 w-5 text-blue-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium">Browser Permission Required</p>
                                <p class="mt-1">Your browser will ask for camera permission. Please click "Allow" to enable the eye tracking features. This permission is required to access learning modules.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Technical Requirements -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">🔧 Technical Requirements</h4>
                        <div class="grid md:grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <p><strong>Supported Browsers:</strong></p>
                                <p>Chrome, Firefox, Edge, Safari</p>
                            </div>
                            <div>
                                <p><strong>Camera Requirements:</strong></p>
                                <p>Any webcam (built-in or external)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Agreement Form -->
                    <form method="POST" class="space-y-6">
                        <div class="border-t pt-6">
                            <div class="flex items-start">
                                <input type="checkbox" id="understand_agreement" class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary" required>
                                <label for="understand_agreement" class="ml-3 text-sm text-gray-700">
                                    <span class="font-medium">I understand and agree</span> that:
                                    <ul class="mt-2 space-y-1 text-xs">
                                        <li>• EyeLearn will access my camera for eye tracking during learning sessions</li>
                                        <li>• Camera data is processed locally and not stored or transmitted</li>
                                        <li>• Only anonymized focus metrics are saved for learning analytics</li>
                                        <li>• I can disable this feature at any time in my profile settings</li>
                                    </ul>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <button type="submit" name="accept_agreement" 
                                    class="inline-flex justify-center items-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Accept & Continue
                            </button>
                            
                            <button type="submit" name="decline_agreement"
                                    class="inline-flex justify-center items-center px-8 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Decline & Logout
                            </button>
                        </div>
                    </form>

                    <div class="text-center text-xs text-gray-500 border-t pt-4">
                        <p>By continuing, you agree to our Privacy Policy and Terms of Service.</p>
                        <p class="mt-1">For technical support, contact: <a href="mailto:support@eyellearn.com" class="text-primary hover:underline">support@eyellearn.com</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Test camera availability when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                // Camera API is available
                console.log('Camera API is available');
            } else {
                // Show warning if camera API is not available
                const warning = document.createElement('div');
                warning.className = 'bg-red-50 border border-red-200 rounded-lg p-4 mb-6';
                warning.innerHTML = `
                    <div class="flex items-start">
                        <svg class="h-5 w-5 text-red-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <div class="text-sm text-red-800">
                            <p class="font-medium">Camera API Not Available</p>
                            <p class="mt-1">Your browser or device may not support camera access. Please try using a modern browser like Chrome, Firefox, or Edge.</p>
                        </div>
                    </div>
                `;
                document.querySelector('.bg-white.rounded-lg.shadow-xl').insertBefore(warning, document.querySelector('.space-y-6'));
            }
        });
    </script>
</body>
</html>
