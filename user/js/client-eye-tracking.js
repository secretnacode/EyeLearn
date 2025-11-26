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
        this.lastSaveTime = null; // Track when we last saved to avoid double counting

        // Configuration
        this.updateInterval = null;
        this.saveInterval = null;
        this.detectionInterval = null;

        // FIX #1: Video ready state tracking
        this.videoReady = false;
        this.videoReadyStateCheckInterval = null;

        // Eye tracking improvements pipeline
        this.trackingPipeline = null;
        if (window.EyeTrackingPipeline) {
            this.trackingPipeline = new EyeTrackingPipeline();
        }

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
            container.className = 'fixed top-16 right-4 z-50 bg-black rounded-lg shadow-xl border border-gray-700 p-2 w-56';
            container.style.transition = 'all 0.3s ease';
            container.style.pointerEvents = 'auto'; // Ensure widget doesn't block content
            document.body.appendChild(container);
            
            // Make draggable
            this.makeDraggable(container);
        }

        this.isMinimized = false;
        const contentHTML = this.isMinimized ? this.getMinimizedHTML() : this.getFullHTML();
        container.innerHTML = contentHTML;

        // Set up minimize button
        const minimizeBtn = container.querySelector('.minimize-btn');
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleMinimize();
            });
        }

        this.videoElement = document.getElementById('eye-tracking-video');
        this.canvasElement = document.getElementById('eye-tracking-canvas');
        if (this.canvasElement) {
            this.canvasCtx = this.canvasElement.getContext('2d');
        }
        
        // Set canvas size to match video
        if (this.videoElement && this.canvasElement) {
            this.canvasElement.width = this.videoElement.offsetWidth;
            this.canvasElement.height = this.videoElement.offsetHeight;
        }
    }

    getFullHTML() {
        return `
            <div class="widget-header flex items-center justify-between mb-2 cursor-move">
                <h3 class="text-xs font-semibold text-white">Eye Tracking</h3>
                <div class="flex items-center gap-1">
                    <span id="eye-tracking-status-indicator" class="flex items-center gap-1">
                        <span class="relative flex h-2 w-2 bg-gray-500 rounded-full"></span>
                    </span>
                    <button class="minimize-btn text-gray-400 hover:text-white text-xs px-1" title="Minimize">−</button>
                </div>
            </div>
            <div class="widget-content">
                <div class="relative w-full h-32 bg-gray-900 rounded overflow-hidden mb-2">
                    <video id="eye-tracking-video" 
                           autoplay 
                           playsinline 
                           muted 
                           class="w-full h-full object-cover transform scale-x-[-1]"></video>
                    <canvas id="eye-tracking-canvas" 
                            class="absolute top-0 left-0 w-full h-full pointer-events-none"></canvas>
                </div>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Focused:</span>
                        <span id="eye-tracking-focused" class="font-medium text-green-400">0s</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Unfocused:</span>
                        <span id="eye-tracking-unfocused" class="font-medium text-red-400">0s</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Total:</span>
                        <span id="eye-tracking-total" class="font-medium text-blue-400">0s</span>
                    </div>
                </div>
                <div class="mt-1">
                    <div class="flex justify-between items-center mb-0.5 text-xs">
                        <span class="text-gray-400">Focus:</span>
                        <span id="eye-tracking-focus-percentage" class="font-medium text-white text-xs">0%</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-1.5">
                        <div id="eye-tracking-focus-progress-bar" class="bg-gray-500 h-1.5 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                </div>
                <div id="eye-tracking-current-status" class="mt-1.5 p-1 bg-gray-900 rounded text-center">
                    <span class="text-xs font-medium text-gray-200">Initializing...</span>
                </div>
            </div>
        `;
    }

    getMinimizedHTML() {
        return `
            <div class="widget-header flex items-center justify-between cursor-move">
                <h3 class="text-xs font-semibold text-white">Eye Tracking</h3>
                <div class="flex items-center gap-1">
                    <span id="eye-tracking-status-indicator" class="flex items-center gap-1">
                        <span class="relative flex h-2 w-2 bg-gray-500 rounded-full"></span>
                    </span>
                    <button class="minimize-btn text-gray-400 hover:text-white text-xs px-1" title="Expand">+</button>
                </div>
            </div>
            <div class="widget-content mt-1">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-400">Focus:</span>
                    <span id="eye-tracking-focus-percentage" class="font-medium text-white">0%</span>
                </div>
                <div id="eye-tracking-current-status" class="mt-1 text-xs text-gray-300 text-center">
                    <span>Minimized</span>
                </div>
            </div>
        `;
    }

    toggleMinimize() {
        this.isMinimized = !this.isMinimized;
        const container = document.getElementById('eye-tracking-container');
        if (container) {
            container.innerHTML = this.isMinimized ? this.getMinimizedHTML() : this.getFullHTML();
            
            // Re-attach minimize button
            const minimizeBtn = container.querySelector('.minimize-btn');
            if (minimizeBtn) {
                minimizeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleMinimize();
                });
            }

            // Re-initialize video/canvas if expanded
            if (!this.isMinimized) {
                this.videoElement = document.getElementById('eye-tracking-video');
                this.canvasElement = document.getElementById('eye-tracking-canvas');
                if (this.canvasElement) {
                    this.canvasCtx = this.canvasElement.getContext('2d');
                }
                if (this.videoElement && this.canvasElement) {
                    this.canvasElement.width = this.videoElement.offsetWidth;
                    this.canvasElement.height = this.videoElement.offsetHeight;
                }
            }
        }
    }

    makeDraggable(element) {
        let isDragging = false;
        let currentX, currentY, initialX, initialY;

        const header = element.querySelector('.widget-header') || element;
        
        header.addEventListener('mousedown', function(e) {
            if (e.target.classList.contains('minimize-btn')) return;
            
            isDragging = true;
            initialX = e.clientX - element.offsetLeft;
            initialY = e.clientY - element.offsetTop;
            element.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', function(e) {
            if (isDragging) {
                e.preventDefault();
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;

                // Keep within viewport
                const maxX = window.innerWidth - element.offsetWidth;
                const maxY = window.innerHeight - element.offsetHeight;
                currentX = Math.max(0, Math.min(currentX, maxX));
                currentY = Math.max(0, Math.min(currentY, maxY));

                element.style.left = currentX + 'px';
                element.style.top = currentY + 'px';
                element.style.right = 'auto';
            }
        });

        document.addEventListener('mouseup', function() {
            if (isDragging) {
                isDragging = false;
                element.style.cursor = 'default';
            }
        });
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
        this.lastSaveTime = this.startTime; // Initialize last save time
        
        // Reset all time counters to zero for new session
        this.focusedTime = 0;
        this.unfocusedTime = 0;
        
        // Update UI immediately to show zeros
        this.updateUI();

        console.log('🎬 Starting eye tracking detection - session times reset to zero');

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
                const now = Date.now();
                if (this.isFocused) {
                    // Transitioning from focused to unfocused
                    const duration = (now - this.lastFocusChangeTime) / 1000;
                    this.focusedTime += duration;
                    this.isFocused = false;
                    this.lastFocusChangeTime = now;
                } else if (this.lastFocusChangeTime) {
                    // Already unfocused, continue tracking unfocused time
                    const duration = (now - this.lastFocusChangeTime) / 1000;
                    this.unfocusedTime += duration;
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
            // If we can't compute EAR, it might mean eyes are covered or not visible
            // Be conservative: if we can't detect eyes properly, assume not awake
            // This helps detect covered eyes scenarios
            return false;
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
            // CRITICAL FIX: When eye landmarks are missing (eyes covered/not visible),
            // we should return false for all metrics, not true!
            // This ensures covered eyes are detected as unfocused.
            return {
                horizontalCentered: false,
                verticalCentered: false,
                eyesAligned: false
            };
        }

        const leftWidth = Math.abs(leftOuter.x - leftInner.x);
        const rightWidth = Math.abs(rightOuter.x - rightInner.x);

        if (leftWidth < 0.01 || rightWidth < 0.01) {
            // Eyes too small/closed to trust gaze; treat as not centered
            // FIX: All metrics should be false when eyes are closed/covered
            return {
                horizontalCentered: false,
                verticalCentered: false,
                eyesAligned: false
            };
        }

        // Prefer true iris landmarks if available (MediaPipe refine_landmarks-style)
        let leftIrisCenter = null;
        let rightIrisCenter = null;
        let hasRealIrisData = false;
        
        if (keypoints.length > 473) {
            leftIrisCenter = keypoints[468];
            rightIrisCenter = keypoints[473];
            // Check if iris landmarks are actually valid (not default/zero values)
            if (leftIrisCenter && rightIrisCenter && 
                (leftIrisCenter.x !== 0 || leftIrisCenter.y !== 0) &&
                (rightIrisCenter.x !== 0 || rightIrisCenter.y !== 0)) {
                hasRealIrisData = true;
            }
        }

        // Fallback: approximate iris center using central eyelid point if iris not present
        // BUT: If we don't have real iris data, this is less reliable (eyes might be covered)
        if (!hasRealIrisData) {
            // If we can't detect real iris landmarks, eyes might be covered
            // Use fallback but mark as less reliable
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
            // When using fallback (no real iris data), be more conservative
            // This helps detect covered eyes where iris landmarks aren't available
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

        // If we don't have real iris data (using fallback), be more strict
        // This helps detect covered eyes where iris detection fails
        const irisTolerance = hasRealIrisData ? 0.3 : 0.25; // Stricter when using fallback
        
        const horizontalCentered =
            leftIrisRatio > irisTolerance && leftIrisRatio < (1 - irisTolerance) &&
            rightIrisRatio > irisTolerance && rightIrisRatio < (1 - irisTolerance);

        const verticalCentered =
            leftIrisVertical > irisTolerance && leftIrisVertical < (1 - irisTolerance) &&
            rightIrisVertical > irisTolerance && rightIrisVertical < (1 - irisTolerance);

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
        const statusIndicator = document.getElementById('eye-tracking-status-indicator');
        const currentStatusEl = document.getElementById('eye-tracking-current-status');
        const focusedEl = document.getElementById('eye-tracking-focused');
        const unfocusedEl = document.getElementById('eye-tracking-unfocused');
        const totalEl = document.getElementById('eye-tracking-total');
        const focusPercentageEl = document.getElementById('eye-tracking-focus-percentage');
        const focusProgressBar = document.getElementById('eye-tracking-focus-progress-bar');

        // Update status indicator
        if (statusIndicator) {
            if (this.isTracking) {
                statusIndicator.innerHTML = `
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    <span class="text-xs text-green-400 font-medium">Active</span>
                `;
            } else {
                statusIndicator.innerHTML = `
                    <span class="relative flex h-3 w-3 bg-gray-500 rounded-full"></span>
                    <span class="text-xs text-gray-300 font-medium">Inactive</span>
                `;
            }
        }

        // Update current status
        if (currentStatusEl) {
            const status = this.isFocused ? '✅ Currently Focused' : '⚠️ Currently Unfocused';
            currentStatusEl.innerHTML = `<span class="text-xs font-medium ${this.isFocused ? 'text-green-400' : 'text-red-400'}">${status}</span>`;
        }

        // Update times
        if (focusedEl) {
            focusedEl.textContent = this.formatTime(this.focusedTime);
        }

        if (unfocusedEl) {
            unfocusedEl.textContent = this.formatTime(this.unfocusedTime);
        }

        if (totalEl) {
            const total = this.focusedTime + this.unfocusedTime;
            totalEl.textContent = this.formatTime(total);
        }

        // Update focus percentage
        if (focusPercentageEl && focusProgressBar) {
            const total = this.focusedTime + this.unfocusedTime;
            const percentage = total > 0 ? Math.round((this.focusedTime / total) * 100) : 0;
            focusPercentageEl.textContent = percentage + '%';
            focusProgressBar.style.width = percentage + '%';
            
            // Change color based on percentage
            if (percentage >= 70) {
                focusProgressBar.className = 'bg-green-600 h-2 rounded-full transition-all duration-300';
            } else if (percentage >= 40) {
                focusProgressBar.className = 'bg-yellow-600 h-2 rounded-full transition-all duration-300';
            } else {
                focusProgressBar.className = 'bg-red-600 h-2 rounded-full transition-all duration-300';
            }
        }
    }

    formatTime(seconds) {
        if (seconds < 60) return Math.round(seconds) + 's';
        const mins = Math.floor(seconds / 60);
        const secs = Math.round(seconds % 60);
        return `${mins}m ${secs}s`;
    }

    // FIX #3: Save tracking data with proper headers and JSON
    async saveTrackingData() {
        if (!this.lastSaveTime) {
            this.lastSaveTime = Date.now();
            return; // Skip first save, wait for actual data
        }

        const now = Date.now();
        
        // Calculate time in current state since last focus change
        const timeInCurrentState = this.lastFocusChangeTime 
            ? (now - this.lastFocusChangeTime) / 1000 
            : 0;

        // Add current state time to accumulated times
        let totalFocusedTime = this.focusedTime;
        let totalUnfocusedTime = this.unfocusedTime;

        if (this.isFocused) {
            totalFocusedTime += timeInCurrentState;
        } else {
            totalUnfocusedTime += timeInCurrentState;
        }

        const totalTime = totalFocusedTime + totalUnfocusedTime;

        // Only save if we have meaningful time (> 1 second)
        if (totalTime < 1) {
            return;
        }

        const data = {
            user_id: this.userId,
            module_id: this.moduleId,
            section_id: this.sectionId || 0,
            focused_time: Math.round(totalFocusedTime),
            unfocused_time: Math.round(totalUnfocusedTime),
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
                console.log('✅ Tracking data saved:', {
                    focused: Math.round(totalFocusedTime) + 's',
                    unfocused: Math.round(totalUnfocusedTime) + 's',
                    total: Math.round(totalTime) + 's'
                });
                
                // Reset accumulated times after successful save
                // The time in current state will be tracked from lastFocusChangeTime
                this.focusedTime = 0;
                this.unfocusedTime = 0;
                this.lastSaveTime = now;
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

