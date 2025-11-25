/**
 * Enhanced Computer Vision Eye Tracking System v2.1
 * Features: 3-second countdown, real-time focus tracking, detailed metrics
 * CLEANED VERSION - Fixed duplicate methods and API response handling
 */

class CVEyeTrackingSystem {
    constructor(moduleId, sectionId = null) {
        this.moduleId = moduleId;
        this.sectionId = sectionId;
        this.isConnected = false;
        this.isTracking = false;
        this.dormantMode = false; // New dormant mode flag
        this.pythonServiceUrl = 'http://127.0.0.1:5000';
        this.checkInterval = null;
        this.statusUpdateInterval = null;
        this.videoUpdateInterval = null;
        this.fullscreenVideoInterval = null;
        this.totalTime = 0;
        this.lastStatusUpdate = 0;
        this.countdownActive = false;
        this.trackingState = 'idle';
        this.countdownShownForModule = false;
        this.cameraErrorShown = false; // Prevent multiple camera error dialogs
        
        // Enhanced timer system
        this.timers = {
            sessionStart: null,
            sessionTime: 0,
            focusedTime: 0,
            unfocusedTime: 0,
            currentFocusStart: null,
            currentUnfocusStart: null,
            isCurrentlyFocused: false
        };
        
        this.metrics = {
            focused_time: 0,
            unfocused_time: 0,
            total_time: 0,
            focus_percentage: 0
        };
        
        // Only initialize if not in dormant mode
        if (moduleId !== 'dormant_mode') {
            this.init();
        } else {
            this.dormantMode = true;
            console.log('🛌 Eye tracking initialized in dormant mode');
        }
    }

    async init() {
        console.log('🎯 Initializing Enhanced CV Eye Tracking System v2.1...');
        console.log('Features: 3-second countdown, real-time focus tracking, detailed metrics');
        
        // Check if Python service is running
        await this.checkServiceHealth();
        
        if (this.isConnected) {
            // Always show countdown for new module sessions
            console.log('🎬 Starting countdown for module:', this.moduleId);
            
            // Show countdown notification first
            this.showCountdownNotification();
            
            // Wait 3 seconds, then start tracking
            setTimeout(async () => {
                await this.startTracking();
                this.setupStatusUpdates();
                this.displayTrackingInterface();
                this.initializeTimers();
                console.log('✅ Eye tracking fully activated after countdown');
            }, 3000); // 3 second countdown
        } else {
            this.showServiceError();
        }
    }
    
    initializeTimers() {
        console.log('⏱️ Initializing timer system...');
        this.timers.sessionStart = Date.now();
        this.timers.sessionTime = 0;
        this.timers.focusedTime = 0;
        this.timers.unfocusedTime = 0;
        this.timers.isCurrentlyFocused = false;
        
        // Start the timer update loop
        this.startTimerUpdates();
    }
    
    startTimerUpdates() {
        // Update timers every 100ms for smooth display
        this.timerInterval = setInterval(() => {
            this.updateTimers();
        }, 100);
    }
    
    updateTimers() {
        if (!this.timers.sessionStart) return;
        
        const now = Date.now();
        this.timers.sessionTime = Math.floor((now - this.timers.sessionStart) / 1000);
        
        // Update focus/unfocus timers based on current state
        if (this.timers.isCurrentlyFocused && this.timers.currentFocusStart) {
            const additionalFocusTime = Math.floor((now - this.timers.currentFocusStart) / 1000);
            this.timers.focusedTime = this.timers.baseFocusedTime + additionalFocusTime;
        } else if (!this.timers.isCurrentlyFocused && this.timers.currentUnfocusStart) {
            const additionalUnfocusTime = Math.floor((now - this.timers.currentUnfocusStart) / 1000);
            this.timers.unfocusedTime = this.timers.baseUnfocusedTime + additionalUnfocusTime;
        }
        
        // Update the display
        this.updateTimerDisplay();
    }
    
    updateTimerDisplay() {
        // Update session time
        const sessionTimeElement = document.getElementById('session-time');
        if (sessionTimeElement) {
            sessionTimeElement.textContent = this.timers.sessionTime;
        }
        
        // Update focused time
        const focusTimeElement = document.getElementById('focus-time');
        if (focusTimeElement) {
            focusTimeElement.textContent = this.timers.focusedTime;
        }
        
        // Update unfocused time
        const unfocusTimeElement = document.getElementById('unfocus-time');
        if (unfocusTimeElement) {
            unfocusTimeElement.textContent = this.timers.unfocusedTime;
        }
        
        // Update focus percentage
        const focusPercentageElement = document.getElementById('focus-percentage');
        if (focusPercentageElement) {
            const totalActiveTime = this.timers.focusedTime + this.timers.unfocusedTime;
            const percentage = totalActiveTime > 0 ? Math.round((this.timers.focusedTime / totalActiveTime) * 100) : 0;
            focusPercentageElement.textContent = percentage;
        }
        
        // Update focus status indicator
        const focusStatus = document.getElementById('focus-status');
        const trackingIndicator = document.getElementById('tracking-indicator');
        
        if (focusStatus && trackingIndicator) {
            if (this.timers.isCurrentlyFocused) {
                focusStatus.textContent = 'Focused';
                focusStatus.className = 'text-green-400';
                trackingIndicator.className = 'w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5';
            } else {
                focusStatus.textContent = 'Unfocused';
                focusStatus.className = 'text-red-400';
                trackingIndicator.className = 'w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5';
            }
        }
    }
    
    handleFocusChange(isFocused) {
        const now = Date.now();
        
        if (isFocused && !this.timers.isCurrentlyFocused) {
            // User just became focused
            console.log('👁️ User became focused');
            
            // End unfocus period if active
            if (this.timers.currentUnfocusStart) {
                const unfocusDuration = Math.floor((now - this.timers.currentUnfocusStart) / 1000);
                this.timers.baseUnfocusedTime = (this.timers.baseUnfocusedTime || 0) + unfocusDuration;
                this.timers.currentUnfocusStart = null;
            }
            
            // Start focus period
            this.timers.currentFocusStart = now;
            this.timers.baseFocusedTime = this.timers.focusedTime;
            this.timers.isCurrentlyFocused = true;
            
        } else if (!isFocused && this.timers.isCurrentlyFocused) {
            // User just became unfocused
            console.log('👁️ User became unfocused');
            
            // End focus period if active
            if (this.timers.currentFocusStart) {
                const focusDuration = Math.floor((now - this.timers.currentFocusStart) / 1000);
                this.timers.baseFocusedTime = (this.timers.baseFocusedTime || 0) + focusDuration;
                this.timers.currentFocusStart = null;
            }
            
            // Start unfocus period
            this.timers.currentUnfocusStart = now;
            this.timers.baseUnfocusedTime = this.timers.unfocusedTime;
            this.timers.isCurrentlyFocused = false;
        }
    }

    async checkServiceHealth() {
        try {
            const response = await fetch(`${this.pythonServiceUrl}/api/health`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.isConnected = data.success;
                if (data.version) {
                    console.log(`✅ Connected to Enhanced Eye Tracking Service ${data.version}`);
                    console.log(`📋 Available features:`, data.features);
                }
                console.log('✅ Python eye tracking service is running');
            } else {
                console.log('❌ Python service responded with error');
                this.isConnected = false;
            }
        } catch (error) {
            console.log('❌ Cannot connect to Python eye tracking service:', error);
            this.isConnected = false;
        }
    }

    async startTracking() {
        if (!this.isConnected) {
            console.log('Cannot start tracking - service not connected');
            return false;
        }

        try {
            // Get user ID from session
            const userId = await this.getCurrentUserId();
            
            const response = await fetch(`${this.pythonServiceUrl}/api/start_tracking`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    module_id: this.moduleId,
                    section_id: this.sectionId
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.isTracking = true;
                    this.countdownActive = true;
                    console.log(`🎯 Enhanced eye tracking started with ${data.countdown_duration}s countdown`);
                    return true;
                } else {
                    console.error('Failed to start tracking:', data.error);
                    return false;
                }
            } else {
                console.error('HTTP error starting tracking:', response.status);
                return false;
            }
        } catch (error) {
            console.error('Error starting eye tracking:', error);
            return false;
        }
    }

    showCountdownNotification() {
        // Create compact centered countdown overlay
        const countdownOverlay = document.createElement('div');
        countdownOverlay.id = 'eye-tracking-countdown';
        countdownOverlay.className = 'fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50';
        countdownOverlay.innerHTML = `
            <div class="bg-gray-800 text-white rounded-lg shadow-2xl p-6 text-center" style="width: 220px; height: 220px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                <!-- Header -->
                <div class="flex items-center mb-3">
                    <div class="w-2 h-2 bg-pink-500 rounded-full mr-1.5"></div>
                    <span class="text-xs font-medium">CV Eye Tracking</span>
                </div>
                
                <!-- Rocket Icon Container -->
                <div class="mb-4">
                    <div id="countdown-number" class="text-4xl font-bold mb-1">3</div>
                    <div id="rocket-icon" class="text-3xl hidden">🚀</div>
                </div>
                
                <!-- Status Text -->
                <div class="text-xs text-gray-300" id="countdown-status">
                    Starting...
                </div>
            </div>
        `;
        document.body.appendChild(countdownOverlay);
        
        // Start countdown sequence: 3, 2, 1, rocket
        let secondsRemaining = 3;
        const countdownNumber = document.getElementById('countdown-number');
        const rocketIcon = document.getElementById('rocket-icon');
        const statusText = document.getElementById('countdown-status');
        
        // Update countdown immediately for initial display
        countdownNumber.textContent = secondsRemaining;
        statusText.textContent = `Starting in ${secondsRemaining}...`;
        
        const countdownInterval = setInterval(() => {
            secondsRemaining--;
            
            if (secondsRemaining > 0) {
                // Update the display for remaining seconds
                countdownNumber.textContent = secondsRemaining;
                statusText.textContent = `Starting in ${secondsRemaining}...`;
                console.log(`⏱️ Countdown: ${secondsRemaining} seconds remaining`);
            } else {
                // Show rocket and launch message
                console.log('🚀 Countdown complete - showing rocket');
                countdownNumber.classList.add('hidden');
                rocketIcon.classList.remove('hidden');
                rocketIcon.classList.add('animate-bounce');
                statusText.textContent = 'Eye Tracking Active! 🚀';
                
                clearInterval(countdownInterval);
                
                // Remove countdown overlay after rocket shows
                setTimeout(() => {
                    if (countdownOverlay && countdownOverlay.parentNode) {
                        countdownOverlay.remove();
                    }
                }, 1000);
            }
        }, 1000); // 1 second intervals
    }

    async stopTracking() {
        if (!this.isConnected || !this.isTracking) {
            return;
        }

        try {
            const response = await fetch(`${this.pythonServiceUrl}/api/stop_tracking`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.isTracking = false;
                    this.countdownActive = false;
                    console.log('⏹️ Enhanced eye tracking stopped');
                    
                    // Display final metrics
                    if (data.final_metrics) {
                        console.log('📊 Final session metrics:', data.final_metrics);
                        this.showFinalMetrics(data.final_metrics);
                    }
                }
            }
        } catch (error) {
            console.error('Error stopping eye tracking:', error);
        }

        // Clear intervals
        if (this.statusUpdateInterval) {
            clearInterval(this.statusUpdateInterval);
        }
        
        // Stop video updates
        this.stopVideoUpdates();
    }

    showFinalMetrics(metrics) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-green-600 text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-sm';
        notification.innerHTML = `
            <div class="text-sm">
                <div class="font-semibold mb-2">📊 Session Complete!</div>
                <div class="space-y-1 text-xs">
                    <div>Focus Time: ${metrics.focused_time}s</div>
                    <div>Total Time: ${metrics.total_time}s</div>
                    <div>Focus Rate: ${metrics.focus_percentage}%</div>
                </div>
            </div>
        `;
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    async getCurrentUserId() {
        // Try to get user ID from the page or make an API call
        // This is a simplified version - you may need to adjust based on your session management
        try {
            const response = await fetch('database/get_current_user.php');
            if (response.ok) {
                const data = await response.json();
                return data.user_id;
            }
        } catch (error) {
            console.log('Could not get user ID, using fallback');
        }
        
        // Fallback: try to extract from global variables if available
        if (typeof window.currentUserId !== 'undefined') {
            return window.currentUserId;
        }
        
        // Default fallback (not ideal for production)
        return 1;
    }

    setupStatusUpdates() {
        // Check status every 2 seconds
        this.statusUpdateInterval = setInterval(async () => {
            await this.updateStatus();
        }, 2000);
    }

    async updateStatus() {
        if (!this.isConnected) {
            return;
        }

        try {
            const response = await fetch(`${this.pythonServiceUrl}/api/status`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateTrackingDisplay(data.status);
                    
                    // Handle focus changes
                    if (data.status && typeof data.status.is_focused !== 'undefined') {
                        this.handleFocusChange(data.status.is_focused);
                    }
                }
            } else {
                // Service not responding properly - reconnect
                this.handleServiceDisconnection();
            }
        } catch (error) {
            // Network error - service might be down
            this.handleServiceDisconnection();
        }
    }

    handleServiceDisconnection() {
        if (this.isConnected) {
            console.warn('🔌 Eye tracking service disconnected - attempting reconnection...');
            this.isConnected = false;
            
            // Stop video updates to prevent spam
            this.stopVideoUpdates();
            
            // Try to reconnect after a delay
            setTimeout(async () => {
                await this.checkServiceHealth();
                if (this.isConnected) {
                    console.log('🔌 Service reconnected - resuming video feed');
                    this.startVideoUpdates();
                } else {
                    // Show service error if still not connected
                    this.showServiceError();
                }
            }, 5000); // Wait 5 seconds before trying to reconnect
        }
    }

    displayTrackingInterface() {
        // Create the compact interface
        const trackingContainer = document.createElement('div');
        trackingContainer.id = 'cv-eye-tracking-interface';
        trackingContainer.innerHTML = `
            <div class="fixed top-20 right-4 bg-black text-white shadow-2xl rounded-lg border border-gray-600 z-50" style="width: 180px; font-family: system-ui;">
                <!-- Header with red dot and "Eye Tracking" -->
                <div class="px-2 py-1.5 border-b border-gray-600">
                    <div class="flex items-center">
                        <div id="tracking-indicator" class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></div>
                        <span class="text-xs font-medium">Eye Tracking</span>
                    </div>
                </div>
                
                <!-- Focus status line -->
                <div class="px-2 py-1 border-b border-gray-600">
                    <div class="flex items-center text-xs">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></div>
                        <span id="focus-status">Focused</span>
                    </div>
                </div>
                
                <!-- Metrics -->
                <div class="px-2 py-1.5 text-xs space-y-0.5 border-b border-gray-600">
                    <div>Focus: <span id="focus-time" class="text-green-400">0</span>s</div>
                    <div>Session: <span id="session-time" class="text-white">0</span>s</div>
                    <div>Focused: <span id="focus-percentage" class="text-white">0</span>%</div>
                    <div>Unfocused: <span id="unfocus-time" class="text-white">0</span>s</div>
                </div>
                
                <!-- Live Feed label -->
                <div class="px-2 py-1 text-xs text-gray-300 border-b border-gray-600">
                    Live Feed
                </div>
                
                <!-- Video feed container -->
                <div class="relative bg-black">
                    <img id="tracking-video" 
                         style="width: 100%; height: 100px; display: block; background: #000;"
                         class="rounded-b-lg"
                         alt="Live camera feed">
                </div>
            </div>
        `;
        
        document.body.appendChild(trackingContainer);
        
        // Verify the interface was created correctly
        setTimeout(() => {
            const videoElement = document.getElementById('tracking-video');
            console.log('🔍 Interface verification:', {
                container: !!document.getElementById('cv-eye-tracking-interface'),
                videoElement: !!videoElement,
                videoElementVisible: videoElement ? window.getComputedStyle(videoElement).display !== 'none' : false,
                videoElementType: videoElement ? videoElement.tagName : null
            });
        }, 100);
        
        // Start video updates immediately
        this.startVideoUpdates();
        
        console.log('📺 Eye tracking interface displayed - exact format from image');
    }

    startVideoUpdates() {
        console.log('🎬 Starting video updates...');
        
        if (this.videoUpdateInterval) {
            console.log('⚠️ Video already running, clearing previous interval');
            clearInterval(this.videoUpdateInterval);
        }
        
        // Optimize for continuous smooth operation
        this.videoUpdateInterval = setInterval(async () => {
            await this.updateVideoFrame();
        }, 100); // Update every 100ms for 10 FPS - back to test file frequency
        
        console.log('✅ Video update interval started (100ms = 10 FPS - optimized for continuous operation)');
        
        // Add a watchdog to ensure video keeps running
        this.startVideoWatchdog();
    }

    startVideoWatchdog() {
        // Check every 5 seconds if video is still updating
        this.videoWatchdog = setInterval(() => {
            const videoElement = document.getElementById('tracking-video');
            if (videoElement && this.isConnected) {
                // Ensure video element is still visible and functional
                if (!videoElement.parentNode || videoElement.offsetWidth === 0) {
                    console.warn('⚠️ Video element lost - reinitializing...');
                    this.stopVideoUpdates();
                    this.startVideoUpdates();
                }
            }
        }, 5000); // Check every 5 seconds
    }

    stopVideoUpdates() {
        if (this.videoUpdateInterval) {
            clearInterval(this.videoUpdateInterval);
            this.videoUpdateInterval = null;
        }
    }

    // OPTIMIZED updateVideoFrame method for continuous smooth operation
    async updateVideoFrame() {
        if (!this.isConnected) {
            return;
        }

        try {
            const response = await fetch(`${this.pythonServiceUrl}/api/frame?_=${Date.now()}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                cache: 'no-cache' // Prevent caching for live feed
            });

            if (response.ok) {
                const data = await response.json();
                
                // Simplified logic focused on continuous operation
                if (data.hasFrame && data.frameData) {
                    const videoElement = document.getElementById('tracking-video');
                    if (videoElement) {
                        // Remove any existing event handlers to prevent conflicts
                        videoElement.onload = null;
                        videoElement.onerror = null;
                        
                        // Direct assignment for immediate update
                        videoElement.src = data.frameData;
                        
                        // Optional: Set a simple onload handler for debugging
                        videoElement.onload = () => {
                            // Log success occasionally
                            if (Math.random() < 0.05) { // 5% chance
                                console.log(`✅ Frame loaded (${data.frameData.length} chars)`);
                            }
                        };
                        
                        // Minimal error handling
                        videoElement.onerror = () => {
                            if (Math.random() < 0.1) { // 10% chance
                                console.warn('⚠️ Frame load issue - retrying next frame');
                            }
                        };
                    }
                } else {
                    // Minimal logging to avoid spam
                    if (Math.random() < 0.05) { // 5% chance
                        console.warn('⚠️ No frame data available');
                    }
                }
            } else if (response.status >= 500) {
                // Only handle server errors for reconnection
                this.handleServiceDisconnection();
            }
        } catch (error) {
            // Handle network errors gracefully
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.handleServiceDisconnection();
            }
            // Suppress other errors to avoid spam
        }
    }

    updateTrackingDisplay(status) {
        const indicator = document.getElementById('tracking-indicator');
        const focusStatus = document.getElementById('focus-status');
        const focusTime = document.getElementById('focus-time');
        const sessionTime = document.getElementById('session-time');
        const focusPercentage = document.getElementById('focus-percentage');
        const unfocusTime = document.getElementById('unfocus-time');
        
        if (!indicator || !focusStatus || !focusTime || !sessionTime || !focusPercentage || !unfocusTime) {
            return;
        }

        // Update tracking state and metrics
        if (status.metrics) {
            this.metrics = status.metrics;
        }
        
        this.trackingState = status.tracking_state || 'idle';

        // Update indicator color based on tracking state
        if (status.countdown_active) {
            indicator.className = 'w-2 h-2 rounded-full bg-blue-500 mr-2 animate-pulse';
        } else if (status.is_tracking_enabled && status.tracking_state === 'tracking') {
            indicator.className = status.is_focused ? 
                'w-2 h-2 rounded-full bg-green-500 mr-2' : 
                'w-2 h-2 rounded-full bg-red-500 mr-2';
        } else {
            indicator.className = 'w-2 h-2 rounded-full bg-gray-500 mr-2';
        }

        // Update focus status (matches the image exactly)
        focusStatus.textContent = status.is_focused ? 'Focused' : 'Unfocused';
        
        // Update all metrics to match the image format
        if (status.metrics) {
            focusTime.textContent = Math.floor(status.metrics.focused_time || 0);
            sessionTime.textContent = Math.floor(status.metrics.total_time || 0);
            focusPercentage.textContent = Math.floor(status.metrics.focus_percentage || 0);
            unfocusTime.textContent = Math.floor(status.metrics.unfocused_time || 0);
        }
    }

    startFullscreenVideoUpdates() {
        console.log('🖥️ Starting fullscreen video updates...');
        
        if (this.fullscreenVideoInterval) {
            clearInterval(this.fullscreenVideoInterval);
        }
        
        this.fullscreenVideoInterval = setInterval(async () => {
            await this.updateFullscreenVideoFrame();
        }, 100); // Faster updates for fullscreen (100ms)
        
        console.log('✅ Fullscreen video update interval started (100ms)');
    }

    stopFullscreenVideoUpdates() {
        if (this.fullscreenVideoInterval) {
            clearInterval(this.fullscreenVideoInterval);
            this.fullscreenVideoInterval = null;
            console.log('⏹️ Fullscreen video updates stopped');
        }
    }

    async updateFullscreenVideoFrame() {
        try {
            const response = await fetch(`${this.pythonServiceUrl}/api/frame?_=${Date.now()}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                // Use the same simple approach as the test file
                if (data.hasFrame && data.frameData) {
                    const fullscreenVideoElement = document.getElementById('fullscreen-video-feed');
                    if (fullscreenVideoElement) {
                        fullscreenVideoElement.src = data.frameData;
                    }
                    
                    // Also update the small video if it's visible
                    const videoElement = document.getElementById('tracking-video');
                    if (videoElement) {
                        videoElement.src = data.frameData;
                    }
                }
            }
        } catch (error) {
            console.warn('Failed to fetch fullscreen video frame:', error);
        }
    }

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    showCameraError() {
        const errorContainer = document.createElement('div');
        errorContainer.innerHTML = `
            <div class="fixed top-4 right-4 bg-red-50 border border-red-200 rounded-lg p-4 max-w-md z-50">
                <div class="flex items-center mb-2">
                    <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                    <h3 class="text-sm font-semibold text-red-700">Camera Required</h3>
                </div>
                
                <div class="text-xs text-red-600 space-y-1">
                    <p>📷 Eye tracking requires camera access</p>
                    <p>Please check:</p>
                    <ol class="list-decimal list-inside ml-2 space-y-1">
                        <li>Camera is connected and working</li>
                        <li>No other apps are using the camera</li>
                        <li>Camera permissions are enabled</li>
                        <li>Try unplugging and reconnecting camera</li>
                    </ol>
                    <p class="text-blue-600 mt-2">🔧 Check Windows Device Manager for camera issues</p>
                </div>
                
                <button onclick="this.parentElement.remove()" class="mt-2 text-xs text-red-600 hover:text-red-800">
                    Dismiss
                </button>
            </div>
        `;
        
        document.body.appendChild(errorContainer);
        
        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            if (errorContainer.parentNode) {
                errorContainer.remove();
            }
        }, 10000);
    }

    showServiceError() {
        const errorContainer = document.createElement('div');
        errorContainer.innerHTML = `
            <div class="fixed top-4 right-4 bg-red-50 border border-red-200 rounded-lg p-4 max-w-sm z-50">
                <div class="flex items-center mb-2">
                    <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                    <h3 class="text-sm font-semibold text-red-700">Enhanced Eye Tracking Service</h3>
                </div>
                
                <div class="text-xs text-red-600 space-y-1">
                    <p>❌ Enhanced Python service not running</p>
                    <p>To enable enhanced CV eye tracking:</p>
                    <ol class="list-decimal list-inside ml-2 space-y-1">
                        <li>Install Python dependencies (opencv, numpy, flask)</li>
                        <li>Run enhanced eye_tracking_service.py from python_services/</li>
                        <li>Service should start on http://127.0.0.1:5000</li>
                        <li>Refresh this page</li>
                    </ol>
                    <p class="text-blue-600 mt-2">🎯 Features: 3s countdown, real-time focus tracking, detailed metrics</p>
                </div>
                
                <button onclick="this.parentElement.remove()" class="mt-2 text-xs text-red-600 hover:text-red-800">
                    Dismiss
                </button>
            </div>
        `;
        
        document.body.appendChild(errorContainer);
        
        // Auto-dismiss after 15 seconds (longer since it's enhanced info)
        setTimeout(() => {
            if (errorContainer.parentNode) {
                errorContainer.remove();
            }
        }, 15000);
    }

    // Public method to get current stats
    getStats() {
        return {
            isConnected: this.isConnected,
            isTracking: this.isTracking,
            totalTime: this.totalTime,
            moduleId: this.moduleId,
            sectionId: this.sectionId
        };
    }
}

// Initialize CV eye tracking when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Extract module and section IDs from URL or page data
    const urlParams = new URLSearchParams(window.location.search);
    const moduleId = urlParams.get('module_id');
    const sectionId = urlParams.get('section_id');
    
    if (moduleId) {
        // Initialize CV eye tracking
        window.cvEyeTracker = new CVEyeTrackingSystem(parseInt(moduleId), sectionId ? parseInt(sectionId) : null);
        console.log('🎯 CV Eye tracking system initialized for module:', moduleId, 'section:', sectionId);
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            if (window.cvEyeTracker) {
                window.cvEyeTracker.stopTracking();
            }
        });
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CVEyeTrackingSystem;
}
