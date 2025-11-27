<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client-Side Eye Tracking Test</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- TensorFlow.js -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
    
    <!-- TensorFlow.js FaceMesh -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/facemesh"></script>
    
    <!-- Our client-side tracking -->
    <script src="js/client-eye-tracking.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-4xl w-full bg-white rounded-lg shadow-xl p-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">
            📹 Client-Side Eye Tracking Test
        </h1>
        
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <p class="text-sm text-blue-700">
                <strong>✨ New Feature:</strong> Eye tracking now runs entirely in your browser!
                Your camera video never leaves your device - all processing happens locally.
            </p>
        </div>
        
        <div class="space-y-4 mb-6">
            <h2 class="text-xl font-semibold text-gray-700">How it works:</h2>
            <ol class="list-decimal list-inside space-y-2 text-gray-600">
                <li>Click "Start Eye Tracking" below</li>
                <li>Grant camera permission when prompted</li>
                <li>Look at the screen to be marked as "Focused"</li>
                <li>Look away to be marked as "Unfocused"</li>
                <li>Watch your stats update in real-time!</li>
            </ol>
        </div>
        
        <button 
            id="startBtn"
            onclick="startTracking()"
            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all shadow-lg">
            🚀 Start Eye Tracking
        </button>
        
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <h3 class="font-semibold text-gray-700 mb-2">Test Content:</h3>
            <p class="text-gray-600 leading-relaxed">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor 
                incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud 
                exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
            </p>
        </div>
        
        <div class="mt-4 text-sm text-gray-500 text-center">
            <p>🔒 Privacy: Your camera feed is processed locally. No video is sent to the server.</p>
        </div>
    </div>
    
    <script>
        let tracker = null;
        
        async function startTracking() {
            const btn = document.getElementById('startBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Loading...';
            
            try {
                // Initialize tracker
                tracker = new ClientSideEyeTracking(
                    22, // module_id (test)
                    1,  // section_id (test)
                    1   // user_id (test)
                );
                
                // Make globally accessible
                window.clientEyeTracker = tracker;
                
                await tracker.init();
                
                btn.textContent = '✅ Tracking Active';
                btn.className = 'w-full bg-green-600 text-white font-semibold py-3 px-6 rounded-lg cursor-not-allowed';
                
            } catch (error) {
                btn.disabled = false;
                btn.textContent = '🚀 Start Eye Tracking';
                alert('Error: ' + error.message);
            }
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (tracker) {
                tracker.stop();
            }
        });
    </script>
</body>
</html>
