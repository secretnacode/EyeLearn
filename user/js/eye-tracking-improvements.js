/**
 * Advanced Eye Tracking Improvements
 * Implements smoothing, deadzone, calibration, velocity tracking, and more
 */

// 1. Kalman Filter-inspired Exponential Smoothing
class CursorSmoother {
    constructor(alpha = 0.3) {
        this.alpha = alpha;
        this.prevX = 0;
        this.prevY = 0;
    }

    smooth(x, y) {
        // Exponential moving average
        this.prevX = this.alpha * x + (1 - this.alpha) * this.prevX;
        this.prevY = this.alpha * y + (1 - this.alpha) * this.prevY;
        return { x: this.prevX, y: this.prevY };
    }

    reset() {
        this.prevX = 0;
        this.prevY = 0;
    }

    setAlpha(alpha) {
        this.alpha = Math.max(0, Math.min(1, alpha));
    }
}

// 2. Deadzone Filter - Ignore tiny movements
class DeadzoneFilter {
    constructor(threshold = 2) {
        this.threshold = threshold;
        this.lastX = 0;
        this.lastY = 0;
    }

    filter(x, y) {
        const dx = Math.abs(x - this.lastX);
        const dy = Math.abs(y - this.lastY);

        if (dx < this.threshold && dy < this.threshold) {
            return { x: this.lastX, y: this.lastY, moved: false };
        }

        this.lastX = x;
        this.lastY = y;
        return { x, y, moved: true };
    }

    reset() {
        this.lastX = 0;
        this.lastY = 0;
    }
}

// 3. Velocity Tracker - Distinguish fixations from saccades
class VelocityTracker {
    constructor(window = 5) {
        this.window = window;
        this.history = [];
        this.lastTime = Date.now();
    }

    track(x, y) {
        const now = Date.now();
        const dt = (now - this.lastTime) / 1000; // seconds
        this.lastTime = now;

        this.history.push({ x, y, t: now });
        if (this.history.length > this.window) this.history.shift();

        if (this.history.length < 2) return { velocity: 0, isFixation: true };

        const first = this.history[0];
        const last = this.history[this.history.length - 1];

        const dx = last.x - first.x;
        const dy = last.y - first.y;
        const dt_total = (last.t - first.t) / 1000;

        const velocity = Math.sqrt(dx * dx + dy * dy) / (dt_total || 0.001);
        const isFixation = velocity < 50; // pixels/second threshold

        return { velocity, isFixation };
    }

    reset() {
        this.history = [];
        this.lastTime = Date.now();
    }
}

// 4. Dwell Detector - Click events based on fixation time
class DwellDetector {
    constructor(dwellTime = 500) {
        this.dwellTime = dwellTime;
        this.fixationStart = null;
        this.lastX = 0;
        this.lastY = 0;
        this.enabled = false; // Disabled by default
    }

    update(x, y, isFixation) {
        if (!this.enabled) return;

        const moved = Math.hypot(x - this.lastX, y - this.lastY) > 10;

        if (isFixation && !moved) {
            if (!this.fixationStart) {
                this.fixationStart = Date.now();
            } else if (Date.now() - this.fixationStart >= this.dwellTime) {
                this.triggerClick(x, y);
                this.fixationStart = null; // Reset
            }
        } else {
            this.fixationStart = null;
        }

        this.lastX = x;
        this.lastY = y;
    }

    triggerClick(x, y) {
        const element = document.elementFromPoint(x, y);
        if (element && element.click) {
            element.click();
            // Visual feedback
            element.classList.add('gaze-clicked');
            setTimeout(() => element.classList.remove('gaze-clicked'), 200);
        }
    }

    setEnabled(enabled) {
        this.enabled = enabled;
    }
}

// 5. Calibration Validator - Monitor real-time accuracy
class CalibrationValidator {
    constructor() {
        this.errors = [];
        this.maxErrors = 100; // Keep last 100 errors
    }

    validate(gazePredicted, gazeActual) {
        const error = Math.hypot(
            gazePredicted.x - gazeActual.x,
            gazePredicted.y - gazeActual.y
        );
        this.errors.push(error);
        if (this.errors.length > this.maxErrors) this.errors.shift();

        const avgError = this.errors.reduce((a, b) => a + b, 0) / this.errors.length;
        const maxError = Math.max(...this.errors);

        if (this.errors.length % 10 === 0) { // Log every 10 samples
            console.log(`Avg error: ${avgError.toFixed(1)}px | Max: ${maxError.toFixed(1)}px`);
        }

        if (avgError > 100) {
            console.warn('⚠️ Re-calibration recommended');
        }

        return { error, avgError, maxError };
    }

    reset() {
        this.errors = [];
    }
}

// 6. Multi-Point Calibration System
class CalibrationSystem {
    constructor() {
        this.calibrationPoints = [];
        this.calibrationSamples = [];
        this.isCalibrating = false;
        this.currentPointIndex = 0;
        this.calibrationMapping = null;
    }

    generateCalibrationPoints() {
        return [
            { x: 0, y: 0 },                                           // top-left
            { x: window.innerWidth / 2, y: 0 },                      // top-center
            { x: window.innerWidth, y: 0 },                          // top-right
            { x: 0, y: window.innerHeight / 2 },                     // middle-left
            { x: window.innerWidth / 2, y: window.innerHeight / 2 }, // center
            { x: window.innerWidth, y: window.innerHeight / 2 },     // middle-right
            { x: 0, y: window.innerHeight },                          // bottom-left
            { x: window.innerWidth / 2, y: window.innerHeight },     // bottom-center
            { x: window.innerWidth, y: window.innerHeight }           // bottom-right
        ];
    }

    startCalibration() {
        this.isCalibrating = true;
        this.currentPointIndex = 0;
        this.calibrationSamples = [];
        this.calibrationPoints = this.generateCalibrationPoints();
        this.showCalibrationPoint(0);
    }

    showCalibrationPoint(index) {
        if (index >= this.calibrationPoints.length) {
            this.finishCalibration();
            return;
        }

        const point = this.calibrationPoints[index];
        // Create visual indicator
        const indicator = document.createElement('div');
        indicator.id = 'calibration-point';
        indicator.style.cssText = `
            position: fixed;
            left: ${point.x}px;
            top: ${point.y}px;
            width: 20px;
            height: 20px;
            background: red;
            border-radius: 50%;
            z-index: 10000;
            transform: translate(-50%, -50%);
            pointer-events: none;
            animation: pulse 1s infinite;
        `;
        document.body.appendChild(indicator);

        // Remove after 3 seconds and collect sample
        setTimeout(() => {
            indicator.remove();
            this.currentPointIndex++;
            if (this.currentPointIndex < this.calibrationPoints.length) {
                setTimeout(() => this.showCalibrationPoint(this.currentPointIndex), 500);
            } else {
                this.finishCalibration();
            }
        }, 3000);
    }

    collectSample(rawGazeData) {
        if (!this.isCalibrating) return;

        const currentPoint = this.calibrationPoints[this.currentPointIndex];
        if (currentPoint) {
            this.calibrationSamples.push({
                raw: rawGazeData,
                screen: currentPoint
            });
        }
    }

    finishCalibration() {
        this.isCalibrating = false;
        // Build simple linear mapping (can be enhanced with polynomial regression)
        this.calibrationMapping = this.buildCalibrationModel(this.calibrationSamples);
        console.log('✅ Calibration complete', this.calibrationMapping);
        
        // Remove calibration point if still exists
        const indicator = document.getElementById('calibration-point');
        if (indicator) indicator.remove();
    }

    buildCalibrationModel(samples) {
        if (samples.length < 3) return null;

        // Simple linear mapping (can be enhanced)
        const avgRawX = samples.reduce((sum, s) => sum + s.raw.x, 0) / samples.length;
        const avgRawY = samples.reduce((sum, s) => sum + s.raw.y, 0) / samples.length;
        const avgScreenX = samples.reduce((sum, s) => sum + s.screen.x, 0) / samples.length;
        const avgScreenY = samples.reduce((sum, s) => sum + s.screen.y, 0) / samples.length;

        return {
            offsetX: avgScreenX - avgRawX,
            offsetY: avgScreenY - avgRawY,
            scaleX: window.innerWidth / (Math.max(...samples.map(s => s.raw.x)) - Math.min(...samples.map(s => s.raw.x))),
            scaleY: window.innerHeight / (Math.max(...samples.map(s => s.raw.y)) - Math.min(...samples.map(s => s.raw.y)))
        };
    }

    mapGazeToScreen(rawX, rawY) {
        if (!this.calibrationMapping) {
            return { x: rawX, y: rawY };
        }

        const mappedX = rawX * this.calibrationMapping.scaleX + this.calibrationMapping.offsetX;
        const mappedY = rawY * this.calibrationMapping.scaleY + this.calibrationMapping.offsetY;

        return {
            x: Math.max(0, Math.min(window.innerWidth, mappedX)),
            y: Math.max(0, Math.min(window.innerHeight, mappedY))
        };
    }
}

// 7. IOD Normalization
class IODNormalizer {
    constructor() {
        this.iod = null;
        this.userProfile = {
            iod: null,
            headPose: null
        };
    }

    calculateIOD(leftEye, rightEye) {
        if (!leftEye || !rightEye) return null;

        const iod = Math.hypot(
            leftEye.x - rightEye.x,
            leftEye.y - rightEye.y
        );

        this.iod = iod;
        this.userProfile.iod = iod;
        return iod;
    }

    normalize(gazeRaw, iod = this.iod) {
        if (!iod || iod === 0) return gazeRaw;

        return {
            x: gazeRaw.x / iod,
            y: gazeRaw.y / iod
        };
    }

    updateHeadPose(faceMesh) {
        // Simplified head pose estimation
        if (faceMesh && faceMesh.landmarks) {
            // Estimate head pose from facial landmarks
            this.userProfile.headPose = {
                pitch: 0, // Would need proper calculation
                yaw: 0,
                roll: 0
            };
        }
    }
}

// 8. Eye Tracking Pipeline - Combines all filters
class EyeTrackingPipeline {
    constructor() {
        this.smoother = new CursorSmoother(0.3);
        this.deadzone = new DeadzoneFilter(2);
        this.velocityTracker = new VelocityTracker(5);
        this.dwellDetector = new DwellDetector(500);
        this.calibrationValidator = new CalibrationValidator();
        this.calibrationSystem = new CalibrationSystem();
        this.iodNormalizer = new IODNormalizer();

        // Rendering state
        this.pendingX = 0;
        this.pendingY = 0;
        this.hasPending = false;
        this.lastSent = 0;
        this.UPDATE_INTERVAL = 16; // ~60 FPS

        // Confidence and lighting
        this.confidence = 1.0;
        this.lighting = 1.0;

        // Start render loop
        this.startRenderLoop();
    }

    processGazeData(rawX, rawY, confidence = 1.0, lighting = 1.0, faceMesh = null) {
        this.confidence = confidence;
        this.lighting = lighting;

        // Update IOD if face mesh available
        if (faceMesh && faceMesh.left_eye && faceMesh.right_eye) {
            this.iodNormalizer.calculateIOD(faceMesh.left_eye, faceMesh.right_eye);
            this.iodNormalizer.updateHeadPose(faceMesh);
        }

        // Apply IOD normalization
        let normalized = this.iodNormalizer.normalize({ x: rawX, y: rawY });

        // Apply calibration mapping
        const calibrated = this.calibrationSystem.mapGazeToScreen(normalized.x, normalized.y);

        // Apply deadzone filter
        const deadzoneResult = this.deadzone.filter(calibrated.x, calibrated.y);
        if (!deadzoneResult.moved) {
            return { x: deadzoneResult.x, y: deadzoneResult.y, isFixation: true, velocity: 0 };
        }

        // Track velocity
        const velocityData = this.velocityTracker.track(deadzoneResult.x, deadzoneResult.y);

        // Adjust smoothing based on fixation state and confidence
        const alpha = velocityData.isFixation 
            ? (confidence > 0.8 ? 0.15 : 0.1)  // Stronger smoothing during fixation
            : (confidence > 0.8 ? 0.5 : 0.3);  // Less smoothing during saccades

        this.smoother.setAlpha(alpha);

        // Apply smoothing
        const smoothed = this.smoother.smooth(deadzoneResult.x, deadzoneResult.y);

        // Update dwell detector
        this.dwellDetector.update(smoothed.x, smoothed.y, velocityData.isFixation);

        // Queue for rendering
        this.pendingX = smoothed.x;
        this.pendingY = smoothed.y;
        this.hasPending = true;

        return {
            x: smoothed.x,
            y: smoothed.y,
            isFixation: velocityData.isFixation,
            velocity: velocityData.velocity,
            confidence: confidence,
            lighting: lighting
        };
    }

    startRenderLoop() {
        const renderLoop = () => {
            if (this.hasPending) {
                // Dispatch custom event for rendering
                const event = new CustomEvent('gaze', {
                    detail: {
                        x: this.pendingX,
                        y: this.pendingY,
                        confidence: this.confidence
                    }
                });
                document.dispatchEvent(event);
                this.hasPending = false;
            }
            requestAnimationFrame(renderLoop);
        };
        renderLoop();
    }

    sendToWebSocket(ws, x, y, timestamp, confidence) {
        const now = Date.now();
        if (now - this.lastSent > this.UPDATE_INTERVAL && ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                x: x,
                y: y,
                timestamp: now,
                confidence: confidence
            }));
            this.lastSent = now;
        }
    }

    reset() {
        this.smoother.reset();
        this.deadzone.reset();
        this.velocityTracker.reset();
        this.calibrationValidator.reset();
    }
}

// Make available globally
window.CursorSmoother = CursorSmoother;
window.DeadzoneFilter = DeadzoneFilter;
window.VelocityTracker = VelocityTracker;
window.DwellDetector = DwellDetector;
window.CalibrationValidator = CalibrationValidator;
window.CalibrationSystem = CalibrationSystem;
window.IODNormalizer = IODNormalizer;
window.EyeTrackingPipeline = EyeTrackingPipeline;

