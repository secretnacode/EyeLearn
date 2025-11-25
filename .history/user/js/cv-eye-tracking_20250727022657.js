/**
 * Computer Vision Eye Tracking System for Module Learning
 * Interfaces with Python service for real gaze tracking using webcam
 */

class CVEyeTrackingSystem {
    constructor(moduleId, sectionId = null) {
        this.moduleId = moduleId;
        this.sectionId = sectionId;
        this.isConnected = false;
        this.isTracking = false;
        this.pythonServiceUrl = 'http://127.0.0.1:5000';
        this.checkInterval = null;
        this.statusUpdateInterval = null;
        this.videoUpdateInterval = null;
        this.fullscreenVideoInterval = null;
        this.totalTime = 0;
        this.lastStatusUpdate = 0;
        
        this.init();
    }

    async init() {
        console.log('Initializing Computer Vision Eye Tracking System...');
        
        // Check if Python service is running
        await this.checkServiceHealth();
        
        if (this.isConnected) {
            await this.startTracking();
            this.setupStatusUpdates();
            this.displayTrackingInterface();
            
            // Auto-start video like the test file
            setTimeout(() => {
                const videoToggleButton = document.getElementById('toggle-video');
                if (videoToggleButton) {
                    videoToggleButton.click(); // Auto-show video
                }
            }, 500);
            
            this.loadPreviousData();
        } else {
            this.showServiceError();
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
            // Get user ID from session (you may need to adjust this)
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
                    console.log('🎯 Eye tracking started successfully');
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
                    console.log('⏹️ Eye tracking stopped');
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
        this.stopFullscreenVideoUpdates();
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
                }
            }
        } catch (error) {
            console.error('Error getting status:', error);
        }
    }

    displayTrackingInterface() {
        // Create the tracking interface
        const trackingContainer = document.createElement('div');
        trackingContainer.id = 'cv-eye-tracking-interface';
        trackingContainer.innerHTML = `
            <div class="fixed top-4 right-4 bg-white shadow-lg rounded-lg p-4 border border-gray-200 z-50 max-w-md">
                <div class="flex items-center mb-2">
                    <div id="tracking-indicator" class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                    <h3 class="text-sm font-semibold text-gray-700">CV Eye Tracking</h3>
                    <button id="toggle-video" class="ml-auto text-xs bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 mr-1">
                        Show Video
                    </button>
                    <button id="fullscreen-video" class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600 hidden">
                        🔍
                    </button>
                </div>
                
                <!-- Video container -->
                <div id="video-container" class="mb-3 hidden">
                    <div class="bg-black rounded border overflow-hidden" style="width: 100%; min-height: 200px;">
                        <img id="tracking-video" src="" alt="Gaze Tracking Feed" style="width: 100%; height: 200px; object-fit: cover; display: block;">
                    </div>
                    <div class="text-xs text-gray-500 mt-1 text-center">Live gaze tracking with annotations</div>
                </div>
                
                <!-- Fullscreen video overlay -->
                <div id="fullscreen-overlay" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
                    <div class="relative">
                        <img id="fullscreen-video-feed" src="" alt="Fullscreen Gaze Tracking" style="max-width: 90vw; max-height: 90vh; object-fit: contain;">
                        <button id="close-fullscreen" class="absolute top-4 right-4 bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                            ✕ Close
                        </button>
                        <div class="absolute bottom-4 left-4 text-white text-lg font-semibold bg-black bg-opacity-50 px-4 py-2 rounded">
                            Live Computer Vision Eye Tracking
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span id="tracking-status" class="font-medium">Initializing...</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Focus:</span>
                        <span id="focus-status" class="font-medium">Unknown</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Session Time:</span>
                        <span id="session-time" class="font-medium text-blue-600">00:00:00</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Time:</span>
                        <span id="total-time" class="font-medium text-green-600">00:00:00</span>
                    </div>
                </div>
                
                <div class="mt-3 pt-2 border-t border-gray-200">
                    <button id="toggle-tracking" class="w-full text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                        Stop Tracking
                    </button>
                </div>
                
                <div class="mt-2 text-xs text-gray-500">
                    💡 Look at the screen to track focus time
                </div>
            </div>
        `;
        
        document.body.appendChild(trackingContainer);

        // Add event listeners
        const toggleButton = document.getElementById('toggle-tracking');
        toggleButton.addEventListener('click', () => {
            if (this.isTracking) {
                this.stopTracking();
                toggleButton.textContent = 'Start Tracking';
                toggleButton.className = 'w-full text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600';
            } else {
                this.startTracking();
                toggleButton.textContent = 'Stop Tracking';
                toggleButton.className = 'w-full text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600';
            }
        });
        
        // Video toggle functionality
        const videoToggleButton = document.getElementById('toggle-video');
        const videoContainer = document.getElementById('video-container');
        const fullscreenButton = document.getElementById('fullscreen-video');
        const fullscreenOverlay = document.getElementById('fullscreen-overlay');
        const closeFullscreenButton = document.getElementById('close-fullscreen');
        let videoEnabled = false;
        let fullscreenMode = false;
        
        console.log('🔧 Setting up video toggle button:', {
            button: !!videoToggleButton,
            container: !!videoContainer,
            fullscreenButton: !!fullscreenButton
        });
        
        videoToggleButton.addEventListener('click', () => {
            console.log('🎛️ Video toggle clicked, current state:', videoEnabled);
            
            if (videoEnabled) {
                videoContainer.classList.add('hidden');
                fullscreenButton.classList.add('hidden');
                videoToggleButton.textContent = 'Show Video';
                videoEnabled = false;
                this.stopVideoUpdates();
                console.log('📹 Video hidden');
            } else {
                videoContainer.classList.remove('hidden');
                fullscreenButton.classList.remove('hidden');
                videoToggleButton.textContent = 'Hide Video';
                videoEnabled = true;
                this.startVideoUpdates();
                console.log('📹 Video shown and updates started');
            }
        });
        
        // Fullscreen functionality
        fullscreenButton.addEventListener('click', () => {
            console.log('🔍 Fullscreen button clicked');
            fullscreenOverlay.classList.remove('hidden');
            fullscreenMode = true;
            this.startFullscreenVideoUpdates();
        });
        
        closeFullscreenButton.addEventListener('click', () => {
            console.log('✕ Closing fullscreen');
            fullscreenOverlay.classList.add('hidden');
            fullscreenMode = false;
            this.stopFullscreenVideoUpdates();
        });
        
        // Close fullscreen on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && fullscreenMode) {
                closeFullscreenButton.click();
            }
        });
    }

    updateTrackingDisplay(status) {
        const indicator = document.getElementById('tracking-indicator');
        const trackingStatus = document.getElementById('tracking-status');
        const focusStatus = document.getElementById('focus-status');
        const sessionTime = document.getElementById('session-time');
        
        if (!indicator || !trackingStatus || !focusStatus || !sessionTime) {
            return;
        }

        // Update indicator color
        if (status.is_tracking_enabled) {
            indicator.className = status.is_focused ? 
                'w-3 h-3 rounded-full bg-green-500 mr-2' : 
                'w-3 h-3 rounded-full bg-yellow-500 mr-2';
        } else {
            indicator.className = 'w-3 h-3 rounded-full bg-red-500 mr-2';
        }

        // Update status text
        trackingStatus.textContent = status.is_tracking_enabled ? 'Active' : 'Inactive';
        focusStatus.textContent = status.is_focused ? '👁️ Focused' : '👀 Looking Away';
        
        // Update session time
        sessionTime.textContent = this.formatTime(Math.floor(status.total_time || 0));
    }

    async loadPreviousData() {
        try {
            const url = `database/get_eye_tracking_data.php?module_id=${this.moduleId}${this.sectionId ? `&section_id=${this.sectionId}` : ''}`;
            const response = await fetch(url);
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.totalTime = result.total_time;
                    this.updateTotalTimeDisplay(result.total_time);
                    console.log(`📊 Loaded previous tracking data: ${result.total_time}s total`);
                }
            }
        } catch (error) {
            console.error('Failed to load previous eye tracking data:', error);
        }
    }

    updateTotalTimeDisplay(totalSeconds) {
        const totalTimeElement = document.getElementById('total-time');
        if (totalTimeElement) {
            totalTimeElement.textContent = this.formatTime(totalSeconds);
        }
    }

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    showServiceError() {
        const errorContainer = document.createElement('div');
        errorContainer.innerHTML = `
            <div class="fixed top-4 right-4 bg-red-50 border border-red-200 rounded-lg p-4 max-w-sm z-50">
                <div class="flex items-center mb-2">
                    <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                    <h3 class="text-sm font-semibold text-red-700">Eye Tracking Service</h3>
                </div>
                
                <div class="text-xs text-red-600 space-y-1">
                    <p>❌ Python service not running</p>
                    <p>To enable CV eye tracking:</p>
                    <ol class="list-decimal list-inside ml-2 space-y-1">
                        <li>Install Python dependencies</li>
                        <li>Run eye_tracking_service.py</li>
                        <li>Refresh this page</li>
                    </ol>
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

    // Video feed methods
    startVideoUpdates() {
        console.log('🎬 Starting video updates...');
        
        if (this.videoUpdateInterval) {
            console.log('⚠️ Video already running, clearing previous interval');
            clearInterval(this.videoUpdateInterval);
        }
        
        this.videoUpdateInterval = setInterval(async () => {
            await this.updateVideoFrame();
        }, 100); // Update every 100ms - SAME as working test
        
        console.log('✅ Video update interval started (100ms - same as test)');
    }

    stopVideoUpdates() {
        if (this.videoUpdateInterval) {
            clearInterval(this.videoUpdateInterval);
            this.videoUpdateInterval = null;
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
            const response = await fetch(`${this.pythonServiceUrl}/api/frame`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                
                // Use the same working format as test file
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

    async updateVideoFrame() {
        try {
            const response = await fetch(`${this.pythonServiceUrl}/api/frame`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                
                // Use the EXACT same logic as the working test file
                if (data.hasFrame && data.frameData) {
                    const videoElement = document.getElementById('tracking-video');
                    if (videoElement) {
                        videoElement.src = data.frameData;
                        
                        // Log success occasionally - same as test
                        if (Math.random() < 0.1) { // 10% chance
                            console.log(`✅ Video frame updated (${data.frameData.length} chars)`);
                        }
                    }
                } else {
                    if (Math.random() < 0.1) { // 10% chance to avoid spam
                        console.log(`⚠️ No frame data: hasFrame=${data.hasFrame}`);
                    }
                }
            } else {
                console.log(`❌ Frame request failed: ${response.status}`);
            }
        } catch (error) {
            console.log(`❌ Failed to fetch video frame: ${error.message}`);
        }
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
