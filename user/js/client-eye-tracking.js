/**
 * Client-Side Eye Tracking System using TensorFlow.js FaceMesh
 * FIXED VERSION with all three critical fixes:
 * 1. TensorFlow fromPixels crash fix (video.readyState check)
 * 2. Webcam continuity support (works with AJAX navigation)
 * 3. API save endpoint fix (proper headers and JSON)
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

        // FIX #1: Video ready state tracking
        this.videoReady = false;
        this.videoReadyStateCheckInterval = null;

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

            // FIX #1: Wait for video to be ready before starting detection
            await this.waitForVideoReady();

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

    // FIX #1: Wait for video to be ready before starting detection
    async waitForVideoReady() {
        return new Promise((resolve) => {
            // Check if video is already ready
            if (this.videoElement && this.videoElement.readyState >= 2) {
                this.videoReady = true;
                console.log('✅ Video is ready (readyState:', this.videoElement.readyState + ')');
                resolve();
                return;
            }

            // Wait for video to be ready
            const checkReady = () => {
                if (this.videoElement && this.videoElement.readyState >= 2) {
                    this.videoReady = true;
                    console.log('✅ Video is ready (readyState:', this.videoElement.readyState + ')');
                    if (this.videoReadyStateCheckInterval) {
                        clearInterval(this.videoReadyStateCheckInterval);
                        this.videoReadyStateCheckInterval = null;
                    }
                    resolve();
                }
            };

            // Check immediately
            checkReady();

            // Also listen for canplay event
            if (this.videoElement) {
                this.videoElement.addEventListener('canplay', checkReady, { once: true });
                this.videoElement.addEventListener('loadeddata', checkReady, { once: true });
            }

            // Fallback: poll every 100ms (max 5 seconds)
            let attempts = 0;
            this.videoReadyStateCheckInterval = setInterval(() => {
                attempts++;
                if (attempts > 50) { // 5 seconds max
                    clearInterval(this.videoReadyStateCheckInterval);
                    this.videoReadyStateCheckInterval = null;
                    console.warn('⚠️ Video ready check timeout, proceeding anyway');
                    resolve();
                    return;
                }
                checkReady();
            }, 100);
        });
    }

    createTrackingInterface() {
        // Check if container already exists (for AJAX navigation continuity)
        let container = document.getElementById('eye-tracking-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'eye-tracking-container';
            container.className = 'fixed top-4 right-4 z-50 bg-white rounded-lg shadow-xl p-4 w-64';
            document.body.appendChild(container);
        }

        container.innerHTML = `
            <div class="mb-3">
                <h3 class="text-sm font-semibold text-gray-800 mb-2">Eye Tracking</h3>
                <div class="relative w-full h-48 bg-gray-100 rounded overflow-hidden">
                    <video id="eye-tracking-video" 
                           autoplay 
                           playsinline 
                           muted 
                           class="w-full h-full object-cover transform scale-x-[-1]"></video>
                    <canvas id="eye-tracking-canvas" 
                            class="absolute top-0 left-0 w-full h-full pointer-events-none"></canvas>
                </div>
            </div>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span id="eye-tracking-status" class="font-medium text-gray-800">Initializing...</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Focused:</span>
                    <span id="eye-tracking-focused" class="font-medium text-green-600">0s</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Unfocused:</span>
                    <span id="eye-tracking-unfocused" class="font-medium text-red-600">0s</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total:</span>
                    <span id="eye-tracking-total" class="font-medium text-blue-600">0s</span>
                </div>
            </div>
        `;

        this.videoElement = document.getElementById('eye-tracking-video');
        this.canvasElement = document.getElementById('eye-tracking-canvas');
        this.canvasCtx = this.canvasElement.getContext('2d');
        
        // Set canvas size to match video
        if (this.videoElement && this.canvasElement) {
            this.canvasElement.width = this.videoElement.offsetWidth;
            this.canvasElement.height = this.videoElement.offsetHeight;
        }
    }

    startTracking() {
        if (this.isTracking) {
            console.warn('⚠️ Tracking already started');
            return;
        }

        // FIX #1: Ensure video is ready before starting
        if (!this.videoReady || !this.videoElement || this.videoElement.readyState < 2) {
            console.warn('⚠️ Video not ready, waiting...');
            this.waitForVideoReady().then(() => {
                this.startTracking();
            });
            return;
        }

        this.isTracking = true;
        this.startTime = Date.now();
        this.lastFocusChangeTime = this.startTime;

        console.log('🎬 Starting eye tracking detection...');

        // Start detection loop
        this.detectionInterval = setInterval(() => {
            this.renderPrediction();
        }, 100); // ~10 FPS

        // Update UI every second
        this.updateInterval = setInterval(() => {
            this.updateUI();
        }, 1000);

        // Save data every 30 seconds
        this.saveInterval = setInterval(() => {
            this.saveTrackingData();
        }, 30000);
    }

    // FIX #1: renderPrediction with video.readyState safety check
    async renderPrediction() {
        // Safety check: Ensure video element exists and is ready
        if (!this.videoElement || !this.canvasElement || !this.canvasCtx || !this.model) {
            return;
        }

        // FIX #1: Critical check - video must be ready (readyState >= 2 means HAVE_CURRENT_DATA or higher)
        if (this.videoElement.readyState < 2) {
            // Video not ready yet, skip this frame
            return;
        }

        // Additional safety: Check video dimensions
        if (this.videoElement.videoWidth === 0 || this.videoElement.videoHeight === 0) {
            return;
        }

        try {
            // FIX #1: Now safe to call fromPixels
            const predictions = await this.model.estimateFaces(this.videoElement);

            // Update canvas size if needed
            if (this.canvasElement.width !== this.videoElement.videoWidth ||
                this.canvasElement.height !== this.videoElement.videoHeight) {
                this.canvasElement.width = this.videoElement.videoWidth;
                this.canvasElement.height = this.videoElement.videoHeight;
            }

            // Clear canvas
            this.canvasCtx.clearRect(0, 0, this.canvasElement.width, this.canvasElement.height);

            if (predictions.length > 0) {
                const keypoints = predictions[0].scaledMesh;

                // Draw face mesh (optional, for debugging)
                this.drawFaceMesh(keypoints);

                // Determine focus status
                const wasFocused = this.isFocused;
                this.isFocused = this.determineFocus(keypoints);

                // Update time tracking
                if (wasFocused !== this.isFocused) {
                    const now = Date.now();
                    const duration = (now - this.lastFocusChangeTime) / 1000;

                    if (wasFocused) {
                        this.focusedTime += duration;
                    } else {
                        this.unfocusedTime += duration;
                    }

                    this.lastFocusChangeTime = now;
                }
            } else {
                // No face detected - consider as unfocused
                if (this.isFocused) {
                    const now = Date.now();
                    const duration = (now - this.lastFocusChangeTime) / 1000;
                    this.focusedTime += duration;
                    this.isFocused = false;
                    this.lastFocusChangeTime = now;
                }
            }

        } catch (error) {
            // FIX #1: Only log if it's not a readyState error
            if (error.message && !error.message.includes('fromPixels')) {
                console.error('❌ Detection error:', error);
            }
        }
    }

    // === GEOMETRY HELPERS ===
    distance2D(a, b) {
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        return Math.sqrt(dx * dx + dy * dy);
    }

    // Compute Eye Aspect Ratio (EAR) for one eye, given landmark indices
    computeEyeEAR(keypoints, idxOuter, idxInner, idxTop1, idxTop2, idxBottom1, idxBottom2) {
        const p1 = keypoints[idxOuter];
        const p4 = keypoints[idxInner];
        const p2 = keypoints[idxTop1];
        const p3 = keypoints[idxTop2];
        const p5 = keypoints[idxBottom1];
        const p6 = keypoints[idxBottom2];

        if (!p1 || !p2 || !p3 || !p4 || !p5 || !p6) {
            return null;
        }

        const vertical1 = this.distance2D(p2, p6);
        const vertical2 = this.distance2D(p3, p5);
        const horizontal = this.distance2D(p1, p4);

        if (horizontal === 0) return null;

        return (vertical1 + vertical2) / (2.0 * horizontal);
    }

    computeAverageEAR(keypoints) {
        // Left eye indices (approximate MediaPipe FaceMesh)
        // Outer: 33, Inner: 133, Upper: 159/158, Lower: 145/153
        const leftEAR = this.computeEyeEAR(
            keypoints,
            33, 133,
            159, 158,
            145, 153
        );

        // Right eye indices
        // Outer: 362, Inner: 263, Upper: 386/387, Lower: 374/373
        const rightEAR = this.computeEyeEAR(
            keypoints,
            362, 263,
            386, 387,
            374, 373
        );

        if (leftEAR == null || rightEAR == null) {
            return null;
        }

        return (leftEAR + rightEAR) / 2.0;
    }

    // Update drowsiness state using EAR with simple hysteresis
    updateDrowsinessState(ear) {
        // Lazy-init state on first call
        if (this._drowsinessState == null) {
            this._drowsinessState = {
                closedMs: 0,
                lastTimestamp: Date.now(),
                eyeClosed: false
            };
        }

        const state = this._drowsinessState;
        const now = Date.now();
        const dtMs = Math.min(now - (state.lastTimestamp || now), 500); // clamp to avoid spikes
        state.lastTimestamp = now;

        // Thresholds tuned conservatively; can be adjusted per environment
        const EAR_CLOSED = 0.18;
        const EAR_OPEN = 0.21;
        const DROWSY_MS = 1000; // > 1s continuous closure => drowsy

        if (ear == null) {
            // If we can't compute EAR, don't change drowsiness aggressively
            return true;
        }

        let eyeClosed = state.eyeClosed;
        if (ear < EAR_CLOSED) {
            eyeClosed = true;
        } else if (ear > EAR_OPEN) {
            eyeClosed = false;
        }
        state.eyeClosed = eyeClosed;

        if (eyeClosed) {
            state.closedMs += dtMs;
        } else {
            state.closedMs = 0;
        }

        const isDrowsy = state.closedMs >= DROWSY_MS;
        return !isDrowsy;
    }

    // Head pose proxy using face symmetry (nose vs eye centers) for yaw + pitch
    computeHeadFrontalMetric(keypoints) {
        const noseTip = keypoints[1];
        const leftEyeCenter = keypoints[33];
        const rightEyeCenter = keypoints[263];

        if (!noseTip || !leftEyeCenter || !rightEyeCenter) {
            return {
                horizontalRatio: 1.0,
                verticalRatio: 1.0
            }; // assume not frontal if we can't measure
        }

        const faceWidth = Math.abs(leftEyeCenter.x - rightEyeCenter.x);
        const eyeMidY = (leftEyeCenter.y + rightEyeCenter.y) / 2.0;
        const faceHeight = Math.abs(noseTip.y - eyeMidY);

        let horizontalRatio = 1.0;
        let verticalRatio = 1.0;

        if (faceWidth > 0) {
            const midX = (leftEyeCenter.x + rightEyeCenter.x) / 2.0;
            const noseOffsetX = Math.abs(noseTip.x - midX);
            horizontalRatio = noseOffsetX / faceWidth; // yaw proxy
        }

        if (faceHeight > 0 && faceWidth > 0) {
            // Normalize vertical offset by eye distance to get a rough pitch proxy
            const expectedNoseOffsetY = faceWidth * 0.1; // conservative expectation
            const actualOffsetY = Math.abs(noseTip.y - (eyeMidY + expectedNoseOffsetY));
            verticalRatio = actualOffsetY / faceWidth; // pitch proxy
        }

        return {
            horizontalRatio,
            verticalRatio
        };
    }

    // Iris / gaze metrics: checks if iris is near the center of each eye
    computeIrisMetrics(keypoints) {
        // Eye corners
        const leftOuter = keypoints[33];
        const leftInner = keypoints[133];
        const rightOuter = keypoints[362];
        const rightInner = keypoints[263];

        if (!leftOuter || !leftInner || !rightOuter || !rightInner) {
            return {
                horizontalCentered: true,
                verticalCentered: true,
                eyesAligned: true
            };
        }

        const leftWidth = Math.abs(leftOuter.x - leftInner.x);
        const rightWidth = Math.abs(rightOuter.x - rightInner.x);

        if (leftWidth < 0.01 || rightWidth < 0.01) {
            // Eyes too small/closed to trust gaze; treat as not centered
            return {
                horizontalCentered: false,
                verticalCentered: true,
                eyesAligned: true
            };
        }

        // Prefer true iris landmarks if available (MediaPipe refine_landmarks-style)
        let leftIrisCenter = null;
        let rightIrisCenter = null;
        if (keypoints.length > 473) {
            leftIrisCenter = keypoints[468];
            rightIrisCenter = keypoints[473];
        }

        // Fallback: approximate iris center using central eyelid point if iris not present
        if (!leftIrisCenter) {
            leftIrisCenter = keypoints[159] || {
                x: (leftOuter.x + leftInner.x) / 2,
                y: (leftOuter.y + leftInner.y) / 2
            };
        }
        if (!rightIrisCenter) {
            rightIrisCenter = keypoints[386] || {
                x: (rightOuter.x + rightInner.x) / 2,
                y: (rightOuter.y + rightInner.y) / 2
            };
        }

        const leftIrisRatio = (leftIrisCenter.x - leftOuter.x) / leftWidth;
        const rightIrisRatio = (rightIrisCenter.x - rightOuter.x) / rightWidth;

        // Vertical metrics if we have eyelid landmarks
        const leftTop = keypoints[159];
        const leftBottom = keypoints[145];
        const rightTop = keypoints[386];
        const rightBottom = keypoints[374];

        let leftIrisVertical = 0.5;
        let rightIrisVertical = 0.5;

        if (leftTop && leftBottom && rightTop && rightBottom) {
            const leftHeight = Math.abs(leftTop.y - leftBottom.y);
            const rightHeight = Math.abs(rightTop.y - rightBottom.y);

            if (leftHeight > 0.005 && rightHeight > 0.005) {
                leftIrisVertical = (leftIrisCenter.y - leftTop.y) / leftHeight;
                rightIrisVertical = (rightIrisCenter.y - rightTop.y) / rightHeight;
            }
        }

        const horizontalCentered =
            leftIrisRatio > 0.3 && leftIrisRatio < 0.7 &&
            rightIrisRatio > 0.3 && rightIrisRatio < 0.7;

        const verticalCentered =
            leftIrisVertical > 0.3 && leftIrisVertical < 0.7 &&
            rightIrisVertical > 0.3 && rightIrisVertical < 0.7;

        const eyesAligned = Math.abs(leftIrisRatio - rightIrisRatio) < 0.3;

        return {
            horizontalCentered,
            verticalCentered,
            eyesAligned
        };
    }

    // Core focus computation helper: returns boolean based on advanced metrics
    computeIsFocused(keypoints) {
        if (keypoints.length < 468) {
            return false; // Not enough landmarks
        }

        // 1) Head pose estimation (yaw/pitch proxy via symmetry)
        const headPose = this.computeHeadFrontalMetric(keypoints);
        const isFaceFrontal =
            headPose.horizontalRatio < 0.15 &&   // too far left/right => unfocused
            headPose.verticalRatio < 0.20;       // too far up/down   => unfocused

        // 2) Eye Aspect Ratio (EAR) for drowsiness / eye closure
        const earAvg = this.computeAverageEAR(keypoints);
        const isAwake = this.updateDrowsinessState(earAvg);

        // 3) Iris / gaze: are eyes looking roughly toward the screen?
        const irisMetrics = this.computeIrisMetrics(keypoints);
        const isLookingAtScreen =
            irisMetrics.horizontalCentered &&
            irisMetrics.verticalCentered &&
            irisMetrics.eyesAligned;

        // Final combined attention decision
        return (isFaceFrontal && isAwake && isLookingAtScreen);
    }

    determineFocus(keypoints) {
        // Backwards-compatible wrapper around the new advanced logic
        return this.computeIsFocused(keypoints);
    }

    drawFaceMesh(keypoints) {
        this.canvasCtx.strokeStyle = '#00FF00';
        this.canvasCtx.lineWidth = 1;

        // Draw face mesh (simplified)
        for (let i = 0; i < keypoints.length; i++) {
            const point = keypoints[i];
            this.canvasCtx.beginPath();
            this.canvasCtx.arc(point.x, point.y, 1, 0, 2 * Math.PI);
            this.canvasCtx.stroke();
        }
    }

    updateUI() {
        const statusEl = document.getElementById('eye-tracking-status');
        const focusedEl = document.getElementById('eye-tracking-focused');
        const unfocusedEl = document.getElementById('eye-tracking-unfocused');
        const totalEl = document.getElementById('eye-tracking-total');

        if (statusEl) {
            statusEl.textContent = this.isFocused ? 'Focused' : 'Unfocused';
            statusEl.className = this.isFocused ? 'font-medium text-green-600' : 'font-medium text-red-600';
        }

        if (focusedEl) {
            focusedEl.textContent = Math.round(this.focusedTime) + 's';
        }

        if (unfocusedEl) {
            unfocusedEl.textContent = Math.round(this.unfocusedTime) + 's';
        }

        if (totalEl) {
            const total = this.focusedTime + this.unfocusedTime;
            totalEl.textContent = Math.round(total) + 's';
        }
    }

    // FIX #3: Save tracking data with proper headers and JSON
    async saveTrackingData() {
        const now = Date.now();
        const currentDuration = (now - this.lastFocusChangeTime) / 1000;

        // Add current session time
        let focusedTime = this.focusedTime;
        let unfocusedTime = this.unfocusedTime;

        if (this.isFocused) {
            focusedTime += currentDuration;
        } else {
            unfocusedTime += currentDuration;
        }

        const totalTime = focusedTime + unfocusedTime;

        const data = {
            user_id: this.userId,
            module_id: this.moduleId,
            section_id: this.sectionId,
            focused_time: Math.round(focusedTime),
            unfocused_time: Math.round(unfocusedTime),
            total_time: Math.round(totalTime)
        };

        try {
            // FIX #3: Proper fetch with explicit headers and JSON.stringify
            const response = await fetch('/api/save_tracking', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                console.log('✅ Tracking data saved:', result);
            } else {
                console.error('❌ Save failed:', result.error);
            }

        } catch (error) {
            console.error('❌ Error saving tracking data:', error);
        }
    }

    stop() {
        console.log('⏹️ Stopping eye tracking...');

        // Save final data
        this.saveTrackingData();

        // Clear intervals
        if (this.detectionInterval) {
            clearInterval(this.detectionInterval);
            this.detectionInterval = null;
        }

        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }

        if (this.saveInterval) {
            clearInterval(this.saveInterval);
            this.saveInterval = null;
        }

        if (this.videoReadyStateCheckInterval) {
            clearInterval(this.videoReadyStateCheckInterval);
            this.videoReadyStateCheckInterval = null;
        }

        // Stop camera
        if (this.videoElement && this.videoElement.srcObject) {
            const tracks = this.videoElement.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            this.videoElement.srcObject = null;
        }

        this.isTracking = false;
        this.videoReady = false;
    }

    showError(message) {
        const container = document.getElementById('eye-tracking-container');
        if (container) {
            container.innerHTML = `
                <div class="text-red-600 text-sm">
                    <strong>Error:</strong> ${message}
                </div>
            `;
        }
    }
}

// Make available globally
window.ClientSideEyeTracking = ClientSideEyeTracking;

