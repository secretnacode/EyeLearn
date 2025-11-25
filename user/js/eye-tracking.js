/**
 * Eye Tracking System for Module Learning
 * Tracks user attention and focus time while viewing module content
 */

class EyeTrackingSystem {
    constructor(moduleId, sectionId = null) {
        this.moduleId = moduleId;
        this.sectionId = sectionId;
        this.isTracking = false;
        this.startTime = null;
        this.accumulatedTime = 0;
        this.lastSaveTime = 0;
        this.saveInterval = 30; // Save every 30 seconds
        this.isVisible = true;
        this.hasFocus = true;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadPreviousData();
        this.startTracking();
        this.displayTimer();
    }

    setupEventListeners() {
        // Page visibility change (tab switching, window minimizing)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseTracking('visibility');
            } else {
                this.resumeTracking('visibility');
            }
        });

        // Window focus/blur events
        window.addEventListener('focus', () => {
            this.hasFocus = true;
            this.resumeTracking('focus');
        });

        window.addEventListener('blur', () => {
            this.hasFocus = false;
            this.pauseTracking('focus');
        });

        // Mouse enter/leave main content area
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            mainContent.addEventListener('mouseenter', () => {
                if (this.hasFocus && !document.hidden) {
                    this.resumeTracking('mouse');
                }
            });

            mainContent.addEventListener('mouseleave', () => {
                this.pauseTracking('mouse');
            });
        }

        // Scroll events to detect engagement
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            if (!this.isTracking && this.hasFocus && !document.hidden) {
                this.resumeTracking('scroll');
            }
            
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                // User stopped scrolling, continue tracking if still focused
                if (this.hasFocus && !document.hidden) {
                    this.resumeTracking('scroll_end');
                }
            }, 1000);
        });

        // Keyboard activity
        document.addEventListener('keydown', () => {
            if (this.hasFocus && !document.hidden) {
                this.resumeTracking('keyboard');
            }
        });

        // Mouse movement within content area
        let mouseMoveTimeout;
        const contentArea = document.querySelector('.module-content, .quiz-container');
        if (contentArea) {
            contentArea.addEventListener('mousemove', () => {
                if (this.hasFocus && !document.hidden) {
                    this.resumeTracking('mouse_move');
                }
                
                clearTimeout(mouseMoveTimeout);
                mouseMoveTimeout = setTimeout(() => {
                    // Mouse stopped moving, pause after 30 seconds of inactivity
                    setTimeout(() => {
                        if (this.isTracking) {
                            this.pauseTracking('inactivity');
                        }
                    }, 30000);
                }, 1000);
            });
        }

        // Save data before page unload
        window.addEventListener('beforeunload', () => {
            this.saveCurrentSession(true);
        });

        // Save data periodically
        setInterval(() => {
            if (this.isTracking) {
                this.saveCurrentSession();
            }
        }, this.saveInterval * 1000);
    }

    startTracking() {
        if (!this.isTracking && this.hasFocus && !document.hidden) {
            this.isTracking = true;
            this.startTime = Date.now();
            this.updateTrackingStatus('🟢 Tracking Active');
            console.log('Eye tracking started');
        }
    }

    pauseTracking(reason = 'unknown') {
        if (this.isTracking) {
            this.isTracking = false;
            const sessionTime = Math.floor((Date.now() - this.startTime) / 1000);
            this.accumulatedTime += sessionTime;
            this.updateTrackingStatus('⏸️ Tracking Paused');
            console.log(`Eye tracking paused (${reason}): +${sessionTime}s, total: ${this.accumulatedTime}s`);
        }
    }

    resumeTracking(reason = 'unknown') {
        if (!this.isTracking && this.hasFocus && !document.hidden) {
            this.isTracking = true;
            this.startTime = Date.now();
            this.updateTrackingStatus('🟢 Tracking Active');
            console.log(`Eye tracking resumed (${reason})`);
        }
    }

    async saveCurrentSession(isUnloading = false) {
        if (this.isTracking) {
            const sessionTime = Math.floor((Date.now() - this.startTime) / 1000);
            this.accumulatedTime += sessionTime;
            this.startTime = Date.now(); // Reset start time
        }

        if (this.accumulatedTime - this.lastSaveTime > 0) {
            const timeToSave = this.accumulatedTime - this.lastSaveTime;
            
            try {
                const response = await fetch('database/save_eye_tracking_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        module_id: this.moduleId,
                        section_id: this.sectionId,
                        time_spent: timeToSave,
                        session_type: 'viewing'
                    }),
                    keepalive: isUnloading
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        this.lastSaveTime = this.accumulatedTime;
                        this.updateTotalTime(result.total_time);
                        console.log(`Saved ${timeToSave}s, total: ${result.total_time}s`);
                    }
                }
            } catch (error) {
                console.error('Failed to save eye tracking data:', error);
            }
        }
    }

    async loadPreviousData() {
        try {
            const url = `database/get_eye_tracking_data.php?module_id=${this.moduleId}${this.sectionId ? `&section_id=${this.sectionId}` : ''}`;
            const response = await fetch(url);
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.updateTotalTime(result.total_time);
                    console.log(`Loaded previous data: ${result.total_time}s total`);
                }
            }
        } catch (error) {
            console.error('Failed to load previous eye tracking data:', error);
        }
    }

    displayTimer() {
        // Create timer display element
        const timerContainer = document.createElement('div');
        timerContainer.id = 'eye-tracking-timer';
        timerContainer.innerHTML = `
            <div class="fixed bottom-4 right-4 bg-white shadow-lg rounded-lg p-4 border border-gray-200 z-50">
                <div class="text-sm font-medium text-gray-700 mb-1">Study Time</div>
                <div id="current-session-time" class="text-lg font-bold text-blue-600">00:00:00</div>
                <div class="text-xs text-gray-500 mt-1">
                    Total: <span id="total-time">00:00:00</span>
                </div>
                <div id="tracking-status" class="text-xs mt-1">🟢 Tracking Active</div>
            </div>
        `;
        
        document.body.appendChild(timerContainer);

        // Update current session timer every second
        setInterval(() => {
            this.updateCurrentSessionTimer();
        }, 1000);
    }

    updateCurrentSessionTimer() {
        const currentSessionElement = document.getElementById('current-session-time');
        if (currentSessionElement && this.isTracking && this.startTime) {
            const currentSessionTime = Math.floor((Date.now() - this.startTime) / 1000);
            const totalCurrentTime = this.accumulatedTime + currentSessionTime;
            currentSessionElement.textContent = this.formatTime(totalCurrentTime - this.lastSaveTime);
        }
    }

    updateTotalTime(totalSeconds) {
        const totalTimeElement = document.getElementById('total-time');
        if (totalTimeElement) {
            totalTimeElement.textContent = this.formatTime(totalSeconds);
        }
    }

    updateTrackingStatus(status) {
        const statusElement = document.getElementById('tracking-status');
        if (statusElement) {
            statusElement.textContent = status;
        }
    }

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    // Public method to get current stats
    getStats() {
        const currentSessionTime = this.isTracking && this.startTime ? 
            Math.floor((Date.now() - this.startTime) / 1000) : 0;
        
        return {
            isTracking: this.isTracking,
            accumulatedTime: this.accumulatedTime,
            currentSessionTime: currentSessionTime,
            totalTime: this.accumulatedTime + currentSessionTime
        };
    }
}

// Initialize eye tracking when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Extract module and section IDs from URL or page data
    const urlParams = new URLSearchParams(window.location.search);
    const moduleId = urlParams.get('module_id');
    const sectionId = urlParams.get('section_id');
    
    if (moduleId) {
        window.eyeTracker = new EyeTrackingSystem(parseInt(moduleId), sectionId ? parseInt(sectionId) : null);
        console.log('Eye tracking system initialized for module:', moduleId, 'section:', sectionId);
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EyeTrackingSystem;
}
