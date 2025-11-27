<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Eye Tracking Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold mb-4">🔍 Eye Tracking File Test</h1>
        
        <div class="space-y-4">
            <div class="bg-gray-800 p-4 rounded">
                <h2 class="font-bold mb-2">Step 1: Check if TensorFlow.js loads</h2>
                <button onclick="testTF()" class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-700">
                    Test TensorFlow.js
                </button>
                <div id="tf-result" class="mt-2"></div>
            </div>

            <div class="bg-gray-800 p-4 rounded">
                <h2 class="font-bold mb-2">Step 2: Check if client-eye-tracking.js loads</h2>
                <button onclick="testClientTracking()" class="bg-green-600 px-4 py-2 rounded hover:bg-green-700">
                    Test Client Tracking
                </button>
                <div id="client-result" class="mt-2"></div>
            </div>

            <div class="bg-gray-800 p-4 rounded">
                <h2 class="font-bold mb-2">Console Output:</h2>
                <div id="console" class="bg-black p-2 rounded text-xs font-mono overflow-auto max-h-64"></div>
            </div>
        </div>
    </div>

    <!-- TensorFlow.js -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/facemesh"></script>

    <!-- Client-side eye tracking -->
    <script src="js/client-eye-tracking.js"></script>

    <script>
        function log(msg) {
            const console = document.getElementById('console');
            console.innerHTML += msg + '\n';
            console.scrollTop = console.scrollHeight;
        }

        function testTF() {
            const result = document.getElementById('tf-result');
            if (typeof tf !== 'undefined') {
                result.innerHTML = '<span class="text-green-400">✅ TensorFlow.js loaded! Version: ' + tf.version.tfjs + '</span>';
                log('✅ TensorFlow.js: ' + tf.version.tfjs);
            } else {
                result.innerHTML = '<span class="text-red-400">❌ TensorFlow.js NOT loaded</span>';
                log('❌ TensorFlow.js NOT loaded');
            }
        }

        function testClientTracking() {
            const result = document.getElementById('client-result');
            if (typeof ClientSideEyeTracking !== 'undefined') {
                result.innerHTML = '<span class="text-green-400">✅ ClientSideEyeTracking class found!</span>';
                log('✅ ClientSideEyeTracking class exists');
                
                // Try to instantiate
                try {
                    const tracker = new ClientSideEyeTracking(1, 1, 1);
                    result.innerHTML += '<br><span class="text-green-400">✅ Can instantiate tracker</span>';
                    log('✅ Tracker instantiated successfully');
                } catch (e) {
                    result.innerHTML += '<br><span class="text-yellow-400">⚠️ Error instantiating: ' + e.message + '</span>';
                    log('⚠️ Error: ' + e.message);
                }
            } else {
                result.innerHTML = '<span class="text-red-400">❌ ClientSideEyeTracking NOT found</span>';
                log('❌ client-eye-tracking.js not loaded or class not defined');
            }
        }

        // Auto-check on load
        window.addEventListener('load', () => {
            log('🚀 Page loaded');
            setTimeout(() => {
                testTF();
                testClientTracking();
            }, 1000);
        });

        // Log all errors
        window.addEventListener('error', (e) => {
            log('❌ ERROR: ' + e.message + ' at ' + e.filename);
        });
    </script>
</body>
</html>
