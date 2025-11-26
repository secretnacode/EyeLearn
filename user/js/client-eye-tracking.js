/**
 * Client-Side Eye Tracking System using TensorFlow.js FaceMesh
 * Enables camera access on deployed websites (HTTPS)
 * Processes face tracking entirely in the browser for privacy
 */

class ClientSideEyeTracking {
    constructor(moduleId, sectionId, userId) {
        this.moduleId = moduleId;
        this.sectionId = sectionId;
        this.userId = userId;

        // Video and canvas elements
        this.videoElement = null;
        this.canvasElement = null;
        this.canvasCtx = null;

        // TensorFlow.js FaceMesh model
        this.model = null;
        this.isModelLoaded = false;

        // Tracking state
        this.isTracking = false;
        this.isFocused = false;
        this.startTime = null;
        this.focusedTime = 0;
        this.unfocusedTime = 0;
        this.lastFocusChangeTime = null;

        // Configuration
        this.updateInterval = null;
        this.saveInterval = null;
        this.detectionInterval = null;

        console.log('📹 Client-Side Eye Tracking initialized');
    }

    async init() {
        try {
            console.log('🚀 Initializing TensorFlow.js FaceMesh...');

            // Create UI elements
            this.createTrackingInterface();

            // Load TensorFlow.js FaceMesh model
            await this.loadModel();

            // Request camera access
            await this.initCamera();

            // Start tracking
            this.startTracking();

            console.log('✅ Client-side eye tracking ready!');

        } catch (error) {
            console.error('❌ Error initializing eye tracking:', error);
            this.showError(error.message);
        }
    }

    async loadModel() {
        try {
            // Load FaceMesh model
            this.model = await facemesh.load({
                maxFaces: 1,
                detectionConfidence: 0.5,
                iouThreshold: 0.3,
                scoreThreshold: 0.75
            });

            this.isModelLoaded = true;
            console.log('✅ TensorFlow.js FaceMesh model loaded');

        } catch (error) {
            console.error('❌ Failed to load FaceMesh model:', error);
            throw new Error('Failed to load face tracking model');
        }
    }

    async initCamera() {
        try {
            // Request camera permission
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            });

            this.videoElement.srcObject = stream;

            // Wait for video to be ready
            await new Promise((resolve) => {
                this.videoElement.onloadedmetadata = () => {
                    this.videoElement.play();
                    resolve();
                };
            });

            console.log('✅ Camera access granted');

        } catch (error) {
            console.error('❌ Camera access denied:', error);
            throw new Error('Camera access required for eye tracking. Please grant permission.');
        }
    }

    createTrackingInterface() {
        // Create container
        const container = document.createElement('div');
        container.id = 'client-eye-tracking-container';
        container.className = 'fixed top-4 right-4 z-50 bg-gray-900 rounded-lg shadow-2xl overflow-hidden';
        container.style.width = '280px';

        container.innerHTML = `
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-3 py-2 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                    <span class="text-white text-xs font-semibold">Eye Tracking Active</span>
                </div>
                <button onclick="window.clientEyeTracker.toggleMinimize()" class="text-white hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>
            
            <div id="tracking-content" class="p-3">
                <!-- Live Camera Feed (visible) -->
                <div class="relative mb-2">
                    <video id="tracking-video" autoplay playsinline muted class="w-full rounded border-2 border-gray-700"></video>
                    <!-- Face mesh overlay canvas -->
                    <canvas id="tracking-canvas" width="254" height="190" class="absolute top-0 left-0 w-full h-full rounded pointer-events-none"></canvas>
                </div>
                
                <!-- Stats -->
                <div class="space-y-1 text-xs text-gray-300">
                    <div class="flex justify-between">
                        <span>Status:</span>
                        <span id="focus-status" class="font-semibold text-green-400">Focused</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Session Time:</span>
                        <span id="session-time" class="font-semibold">0s</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Focused Time:</span>
                        <span id="focused-time" class="font-semibold text-green-400">0s</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Unfocused Time:</span>
                        <span id="unfocused-time" class="font-semibold text-red-400">0s</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Focus Rate:</span>
                        <span id="focus-rate" class="font-semibold text-blue-400">100%</span>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(container);

        // Get references
        this.videoElement = document.getElementById('tracking-video');
        this.canvasElement = document.getElementById('tracking-canvas');
        this.canvasCtx = this.canvasElement.getContext('2d');
    }

    startTracking() {
        if (this.isTracking) return;

        this.isTracking = true;
        this.startTime = Date.now();
        this.lastFocusChangeTime = Date.now();
        this.isFocused = true; // Start as focused

        // Start face detection loop
        this.detectFace();

        // Update UI every second
        this.updateInterval = setInterval(() => this.updateUI(), 1000);

        // Save data every 30 seconds
        this.saveInterval = setInterval(() => this.saveTrackingData(), 30000);

        console.log('🎯 Eye tracking started');
    }

    async detectFace() {
        if (!this.isTracking || !this.isModelLoaded) return;

        try {
            // Get predictions from FaceMesh
            const predictions = await this.model.estimateFaces({
                input: this.videoElement,
                returnTensors: false,
                flipHorizontal: false,
                predictIrises: true
            });

            // Draw on canvas
            this.drawFrame(predictions);

            // Update focus state
            if (predictions.length > 0) {
                const isFocused = this.calculateFocusState(predictions[0]);
                this.updateFocusState(isFocused);
            } else {
                // No face detected = unfocused
                this.updateFocusState(false);
            }

        } catch (error) {
            console.warn('Detection error:', error);
        }

        // Continue detection loop
        requestAnimationFrame(() => this.detectFace());
    }

    drawFrame(predictions) {
        const ctx = this.canvasCtx;
        const width = this.canvasElement.width;
        const height = this.canvasElement.height;

        // Clear canvas (transparent for overlay)
        ctx.clearRect(0, 0, width, height);

        // Draw face mesh overlay if detected
        if (predictions.length > 0) {
            const keypoints = predictions[0].scaledMesh;

            // Calculate scaling factors for overlay
            const scaleX = width / this.videoElement.videoWidth;
            const scaleY = height / this.videoElement.videoHeight;

            // Draw key landmarks with proper scaling
            ctx.fillStyle = this.isFocused ? 'rgba(0, 255, 0, 0.5)' : 'rgba(255, 0, 0, 0.5)';
            keypoints.forEach(([x, y]) => {
                ctx.beginPath();
                ctx.arc(x * scaleX, y * scaleY, 1, 0, 2 * Math.PI);
                ctx.fill();
            });

            // Draw iris centers (bright cyan for visibility)
            const leftIris = keypoints[468];
            const rightIris = keypoints[473];

            if (leftIris && rightIris) {
                ctx.fillStyle = '#00ffff';

                ctx.beginPath();
                ctx.arc(leftIris[0] * scaleX, leftIris[1] * scaleY, 5, 0, 2 * Math.PI);
                ctx.fill();

                ctx.beginPath();
                ctx.arc(rightIris[0] * scaleX, rightIris[1] * scaleY, 5, 0, 2 * Math.PI);
                ctx.fill();
            }
        }

        // Draw status overlay text
        ctx.font = 'bold 16px Arial';
        ctx.fillStyle = this.isFocused ? '#00ff00' : '#ff0000';
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 3;
        ctx.strokeText(this.isFocused ? 'FOCUSED' : 'UNFOCUSED', 10, 25);
        ctx.fillText(this.isFocused ? 'FOCUSED' : 'UNFOCUSED', 10, 25);
    }

    calculateFocusState(prediction) {
        // Get iris landmarks
        const keypoints = prediction.scaledMesh;
        const leftIris = keypoints[468]; // Left iris center
        const rightIris = keypoints[473]; // Right iris center

        if (!leftIris || !rightIris) return false;

        // Calculate average position
        const avgX = (leftIris[0] + rightIris[0]) / 2;
        const avgY = (leftIris[1] + rightIris[1]) / 2;

        // Normalize to video dimensions
        const normalizedX = avgX / this.videoElement.videoWidth;
        const normalizedY = avgY / this.videoElement.videoHeight;

        // Check if looking at center (±20% tolerance)
        const isXCentered = Math.abs(normalizedX - 0.5) < 0.2;
        const isYCentered = Math.abs(normalizedY - 0.5) < 0.2;

        return isXCentered && isYCentered;
    }

    updateFocusState(isFocused) {
        if (isFocused === this.isFocused) return;

        const now = Date.now();
        const duration = (now - this.lastFocusChangeTime) / 1000;

        if (this.isFocused) {
            this.focusedTime += duration;
        } else {
            this.unfocusedTime += duration;
        }

        this.isFocused = isFocused;
        this.lastFocusChangeTime = now;

        console.log(`👁️ Focus state changed: ${isFocused ? 'Focused' : 'Unfocused'}`);
    }

    updateUI() {
        const now = Date.now();
        const sessionTime = Math.floor((now - this.startTime) / 1000);
        const currentDuration = (now - this.lastFocusChangeTime) / 1000;

        let currentFocusedTime = this.focusedTime;
        let currentUnfocusedTime = this.unfocusedTime;

        if (this.isFocused) {
            currentFocusedTime += currentDuration;
        } else {
            currentUnfocusedTime += currentDuration;
        }

        const totalActiveTime = currentFocusedTime + currentUnfocusedTime;
        const focusRate = totalActiveTime > 0
            ? Math.round((currentFocusedTime / totalActiveTime) * 100)
            : 100;

        // Update DOM
        document.getElementById('session-time').textContent = `${sessionTime}s`;
        document.getElementById('focused-time').textContent = `${Math.floor(currentFocusedTime)}s`;
        document.getElementById('unfocused-time').textContent = `${Math.floor(currentUnfocusedTime)}s`;
        document.getElementById('focus-rate').textContent = `${focusRate}%`;

        const statusElement = document.getElementById('focus-status');
        statusElement.textContent = this.isFocused ? 'Focused' : 'Unfocused';
        statusElement.className = this.isFocused ? 'font-semibold text-green-400' : 'font-semibold text-red-400';
    }

    async saveTrackingData() {
        try {
            const now = Date.now();
            const currentDuration = (now - this.lastFocusChangeTime) / 1000;

            let currentFocusedTime = this.focusedTime;
            let currentUnfocusedTime = this.unfocusedTime;

            if (this.isFocused) {
                currentFocusedTime += currentDuration;
            } else {
                currentUnfocusedTime += currentDuration;
            }

            const data = {
                user_id: this.userId,
                module_id: this.moduleId,
                section_id: this.sectionId,
                focused_time: Math.floor(currentFocusedTime),
                unfocused_time: Math.floor(currentUnfocusedTime),
                total_time: Math.floor((now - this.startTime) / 1000),
                timestamp: new Date().toISOString()
            };

            const response = await fetch('/api/save_tracking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                console.log('💾 Tracking data saved');
            } else {
                console.warn('⚠️ Failed to save tracking data');
            }

        } catch (error) {
            console.warn('⚠️ Error saving tracking data:', error);
        }
    }

    toggleMinimize() {
        const content = document.getElementById('tracking-content');
        if (content.style.display === 'none') {
            content.style.display = 'block';
        } else {
            content.style.display = 'none';
        }
    }

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'fixed top-4 right-4 bg-red-600 text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-md';
        errorDiv.innerHTML = `
            <div class="font-semibold mb-2">❌ Eye Tracking Error</div>
            <div class="text-sm">${message}</div>
            <button onclick="this.parentElement.remove()" class="mt-2 text-sm underline">Dismiss</button>
        `;
        document.body.appendChild(errorDiv);
    }

    async stop() {
        this.isTracking = false;

        // Clear intervals
        if (this.updateInterval) clearInterval(this.updateInterval);
        if (this.saveInterval) clearInterval(this.saveInterval);

        // Save final data
        await this.saveTrackingData();

        // Stop camera
        if (this.videoElement && this.videoElement.srcObject) {
            const tracks = this.videoElement.srcObject.getTracks();
            tracks.forEach(track => track.stop());
        }

        console.log('🛑 Eye tracking stopped');
    }
}

// Make globally accessible
window.ClientSideEyeTracking = ClientSideEyeTracking;
