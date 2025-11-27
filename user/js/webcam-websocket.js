/**
 * WebSocket-based Webcam Capture for Eye Tracking
 * Captures webcam frames in browser and sends to Railway server via WebSocket
 */

class WebcamWebSocket {
    constructor(config = {}) {
        // Configuration
        this.serverUrl = config.serverUrl || this.getServerUrl();
        this.frameRate = config.frameRate || 15; // FPS
        this.jpegQuality = config.jpegQuality || 0.7; // 0-1
        this.videoWidth = config.videoWidth || 640;
        this.videoHeight = config.videoHeight || 480;

        // State
        this.socket = null;
        this.videoElement = null;
        this.canvasElement = null;
        this.canvasContext = null;
        this.stream = null;
        this.captureInterval = null;
        this.isConnected = false;
        this.isCapturing = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;

        // Session info
        this.userId = null;
        this.moduleId = null;
        this.sectionId = null;

        // Callbacks
        this.onTrackingUpdate = config.onTrackingUpdate || (() => { });
        this.onConnectionChange = config.onConnectionChange || (() => { });
        this.onError = config.onError || ((error) => console.error('WebSocket Error:', error));

        console.log('🎥 WebcamWebSocket initialized:', this.serverUrl);
    }

    getServerUrl() {
        // Determine WebSocket URL based on environment
        const isProduction = window.location.hostname.includes('railway.app');

        if (isProduction) {
            // Railway production - Python WebSocket service
            // TODO: Replace with your actual Railway Python service URL
            return 'wss://eye-learn-python-production.up.railway.app';
        } else {
            // Local development
            return 'ws://127.0.0.1:5000';
        }
    }

    async initialize(userId, moduleId, sectionId = null) {
        this.userId = userId;
        this.moduleId = moduleId;
        this.sectionId = sectionId;

        console.log(`🚀 Initializing WebcamWebSocket: User ${userId}, Module ${moduleId}, Section ${sectionId}`);

        try {
            // Create video and canvas elements
            await this.setupVideoElements();

            // Request camera access
            await this.startWebcam();

            // Connect WebSocket
            await this.connectWebSocket();

            console.log('✅ WebcamWebSocket fully initialized');
            return true;
        } catch (error) {
            console.error('❌ Failed to initialize WebcamWebSocket:', error);
            this.onError(error);
            return false;
        }
    }

    setupVideoElements() {
        // Create hidden video element for webcam stream
        this.videoElement = document.createElement('video');
        this.videoElement.width = this.videoWidth;
        this.videoElement.height = this.videoHeight;
        this.videoElement.autoplay = true;
        this.videoElement.style.display = 'none';
        document.body.appendChild(this.videoElement);

        // Create hidden canvas for frame capture
        this.canvasElement = document.createElement('canvas');
        this.canvasElement.width = this.videoWidth;
        this.canvasElement.height = this.videoHeight;
        this.canvasElement.style.display = 'none';
        document.body.appendChild(this.canvasElement);

        this.canvasContext = this.canvasElement.getContext('2d');

        console.log('📹 Video elements created');
    }

    async startWebcam() {
        try {
            console.log('📷 Requesting camera access...');

            const constraints = {
                video: {
                    width: { ideal: this.videoWidth },
                    height: { ideal: this.videoHeight },
                    frameRate: { ideal: this.frameRate }
                },
                audio: false
            };

            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.videoElement.srcObject = this.stream;

            // Wait for video to be ready
            await new Promise((resolve) => {
                this.videoElement.onloadedmetadata = () => {
                    this.videoElement.play();
                    resolve();
                };
            });

            console.log('✅ Camera access granted');
            return true;
        } catch (error) {
            console.error('❌ Camera access failed:', error);

            if (error.name === 'NotAllowedError') {
                this.onError({
                    type: 'CAMERA_PERMISSION_DENIED',
                    message: 'Camera permission denied. Please allow camera access to use eye tracking.'
                });
            } else if (error.name === 'NotFoundError') {
                this.onError({
                    type: 'NO_CAMERA',
                    message: 'No camera found. Please connect a camera to use eye tracking.'
                });
            } else {
                this.onError({
                    type: 'CAMERA_ERROR',
                    message: `Camera error: ${error.message}`
                });
            }

            throw error;
        }
    }

    connectWebSocket() {
        return new Promise((resolve, reject) => {
            try {
                console.log(`🔌 Connecting to WebSocket: ${this.serverUrl}`);

                // Connect to Socket.IO server
                this.socket = io(this.serverUrl, {
                    transports: ['websocket', 'polling'],
                    reconnection: true,
                    reconnectionAttempts: this.maxReconnectAttempts,
                    reconnectionDelay: 1000
                });

                // Connection events
                this.socket.on('connect', () => {
                    console.log('✅ WebSocket connected');
                    this.isConnected = true;
                    this.reconnectAttempts = 0;
                    this.onConnectionChange(true);

                    // Send session info
                    this.socket.emit('start_tracking', {
                        user_id: this.userId,
                        module_id: this.moduleId,
                        section_id: this.sectionId
                    });

                    resolve();
                });

                this.socket.on('disconnect', (reason) => {
                    console.log('🔌 WebSocket disconnected:', reason);
                    this.isConnected = false;
                    this.onConnectionChange(false);
                    this.stopCapture();
                });

                this.socket.on('connect_error', (error) => {
                    console.error('❌ WebSocket connection error:', error);
                    this.reconnectAttempts++;

                    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
                        this.onError({
                            type: 'WEBSOCKET_ERROR',
                            message: 'Failed to connect to eye tracking server. Please refresh the page.'
                        });
                        reject(error);
                    }
                });

                // Tracking events
                this.socket.on('tracking_started', (data) => {
                    console.log('👁️ Tracking started by server:', data);
                    this.startCapture();
                });

                this.socket.on('tracking_update', (data) => {
                    // Receive focus status and metrics from server
                    this.onTrackingUpdate(data);
                });

                this.socket.on('error', (error) => {
                    console.error('❌ Server error:', error);
                    this.onError({
                        type: 'SERVER_ERROR',
                        message: error.message || 'Server error occurred'
                    });
                });

            } catch (error) {
                console.error('❌ Failed to create WebSocket:', error);
                reject(error);
            }
        });
    }

    startCapture() {
        if (this.isCapturing) {
            console.log('⚠️ Already capturing frames');
            return;
        }

        console.log(`🎬 Starting frame capture at ${this.frameRate} FPS`);
        this.isCapturing = true;

        const intervalMs = Math.floor(1000 / this.frameRate);

        this.captureInterval = setInterval(() => {
            if (this.isConnected && this.videoElement.readyState === this.videoElement.HAVE_ENOUGH_DATA) {
                this.captureAndSendFrame();
            }
        }, intervalMs);
    }

    captureAndSendFrame() {
        try {
            // Draw current video frame to canvas
            this.canvasContext.drawImage(
                this.videoElement,
                0, 0,
                this.videoWidth,
                this.videoHeight
            );

            // Convert canvas to base64 JPEG
            const frameData = this.canvasElement.toDataURL('image/jpeg', this.jpegQuality);

            // Send frame to server
            this.socket.emit('video_frame', {
                frame: frameData,
                timestamp: Date.now()
            });

        } catch (error) {
            console.error('❌ Error capturing frame:', error);
        }
    }

    stopCapture() {
        if (this.captureInterval) {
            clearInterval(this.captureInterval);
            this.captureInterval = null;
            this.isCapturing = false;
            console.log('⏹️ Frame capture stopped');
        }
    }

    stopWebcam() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
            console.log('📷 Camera stopped');
        }
    }

    disconnect() {
        console.log('🔌 Disconnecting WebSocket...');

        // Stop capture
        this.stopCapture();

        // Stop webcam
        this.stopWebcam();

        // Close WebSocket
        if (this.socket) {
            this.socket.emit('stop_tracking');
            this.socket.disconnect();
            this.socket = null;
        }

        // Remove video elements
        if (this.videoElement) {
            this.videoElement.remove();
            this.videoElement = null;
        }

        if (this.canvasElement) {
            this.canvasElement.remove();
            this.canvasElement = null;
        }

        this.isConnected = false;
        console.log('✅ WebSocket disconnected and cleaned up');
    }
}

// Make available globally
window.WebcamWebSocket = WebcamWebSocket;
