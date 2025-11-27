"""
Real-time Eye Tracking Service for E-Learning Platform
Uses computer vision to track gaze direction and focus
"""

import cv2
import json
import time
import threading
import requests
import base64
from datetime import datetime
from flask import Flask, jsonify, request, Response
from flask_cors import CORS
import logging

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class EyeTrackingService:
    def __init__(self):
        self.gaze = None
        self.webcam = None
        self.is_tracking = False
        self.is_focused = False
        self.session_start_time = None
        self.accumulated_time = 0
        self.current_module_id = None
        self.current_section_id = None
        self.current_user_id = None
        self.last_save_time = time.time()
        self.save_interval = 30  # Save every 30 seconds
        
        # Gaze detection parameters
        self.center_threshold = 0.1  # How close to center is considered "focused"
        self.focus_history = []  # Track recent focus states
        self.focus_history_size = 10  # Number of frames to average
        self.latest_frame = None  # Store latest annotated frame
        self.frame_lock = threading.Lock()  # Thread safety for frame access
        
        self.init_gaze_tracking()
        
    def init_gaze_tracking(self):
        """Initialize the gaze tracking library"""
        try:
            from gaze_tracking import GazeTracking
            self.gaze = GazeTracking()
            logger.info("GazeTracking initialized successfully")
            return True
        except ImportError:
            try:
                from gaze_tracking_fallback import GazeTracking
                self.gaze = GazeTracking()
                logger.info("Using fallback gaze tracking (simplified)")
                return True
            except Exception as e:
                logger.error(f"Error initializing fallback gaze tracking: {e}")
                return False
        except Exception as e:
            logger.error(f"Error initializing gaze tracking: {e}")
            return False
    
    def start_webcam(self):
        """Start the webcam for eye tracking"""
        try:
            self.webcam = cv2.VideoCapture(0)
            if not self.webcam.isOpened():
                logger.error("Could not open webcam")
                return False
            logger.info("Webcam started successfully")
            return True
        except Exception as e:
            logger.error(f"Error starting webcam: {e}")
            return False
    
    def stop_webcam(self):
        """Stop the webcam"""
        if self.webcam:
            self.webcam.release()
            self.webcam = None
            logger.info("Webcam stopped")
    
    def is_looking_at_screen(self, frame):
        """
        Determine if user is looking at the screen based on gaze direction
        Returns True if focused, False otherwise
        """
        if not self.gaze:
            return False
            
        self.gaze.refresh(frame)
        
        # Get annotated frame and store it
        annotated_frame = self.gaze.annotated_frame()
        with self.frame_lock:
            self.latest_frame = annotated_frame.copy()
        
        # Check if eyes are detected
        if self.gaze.pupils_located:
            # Get horizontal and vertical ratios
            h_ratio = self.gaze.horizontal_ratio()
            v_ratio = self.gaze.vertical_ratio()
            
            # Check if user is blinking (consider as unfocused)
            if self.gaze.is_blinking():
                return False
            
            # Define "looking at screen" as looking roughly at center
            # Adjust these thresholds based on your screen setup
            is_center_h = abs(h_ratio - 0.5) < self.center_threshold
            is_center_v = abs(v_ratio - 0.5) < self.center_threshold
            
            # Consider looking at screen if gaze is in the center area
            return is_center_h and is_center_v
        
        return False
    
    def update_focus_state(self, is_focused):
        """Update focus state with smoothing"""
        self.focus_history.append(is_focused)
        if len(self.focus_history) > self.focus_history_size:
            self.focus_history.pop(0)
        
        # Use majority voting for smoothing
        focus_count = sum(self.focus_history)
        smoothed_focus = focus_count > len(self.focus_history) // 2
        
        # Update tracking state if focus changed
        if smoothed_focus != self.is_focused:
            self.is_focused = smoothed_focus
            
            if self.is_focused:
                self.start_session()
                logger.info("User focused - tracking started")
            else:
                self.pause_session()
                logger.info("User unfocused - tracking paused")
    
    def start_session(self):
        """Start a new tracking session"""
        if not self.is_tracking:
            self.is_tracking = True
            self.session_start_time = time.time()
    
    def pause_session(self):
        """Pause the current tracking session"""
        if self.is_tracking and self.session_start_time:
            session_duration = time.time() - self.session_start_time
            self.accumulated_time += session_duration
            self.is_tracking = False
            self.session_start_time = None
    
    def get_current_session_time(self):
        """Get total time for current session"""
        total_time = self.accumulated_time
        if self.is_tracking and self.session_start_time:
            total_time += time.time() - self.session_start_time
        return total_time
    
    def get_current_frame_base64(self):
        """Get current annotated frame as base64 string for web display"""
        with self.frame_lock:
            if self.latest_frame is not None:
                # Resize frame for web display (smaller for better performance)
                height, width = self.latest_frame.shape[:2]
                new_width = 320
                new_height = int(height * (new_width / width))
                resized_frame = cv2.resize(self.latest_frame, (new_width, new_height))
                
                # Encode frame as JPEG
                _, buffer = cv2.imencode('.jpg', resized_frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
                
                # Convert to base64
                frame_base64 = base64.b64encode(buffer).decode('utf-8')
                return f"data:image/jpeg;base64,{frame_base64}"
        
        return None
    
    def save_tracking_data(self):
        """Save tracking data to the database via PHP API"""
        if not all([self.current_user_id, self.current_module_id]):
            return
        
        current_time = time.time()
        if current_time - self.last_save_time < self.save_interval:
            return
        
        # Calculate time to save
        time_to_save = 0
        if self.is_tracking and self.session_start_time:
            time_to_save = current_time - max(self.session_start_time, self.last_save_time)
        
        if time_to_save > 0:
            try:
                # Send data to PHP backend
                data = {
                    'module_id': self.current_module_id,
                    'section_id': self.current_section_id,
                    'time_spent': int(time_to_save),
                    'session_type': 'cv_tracking'  # Computer vision tracking
                }
                
                # Use a simple HTTP request without session cookies for now
                response = requests.post(
                    'http://localhost/capstone/user/database/save_cv_eye_tracking.php',
                    json=data,
                    timeout=5
                )
                
                if response.status_code == 200:
                    self.last_save_time = current_time
                    logger.info(f"Saved {time_to_save:.1f}s of tracking data")
                else:
                    logger.error(f"HTTP error saving data: {response.status_code}")
                
            except Exception as e:
                logger.error(f"Error saving tracking data: {e}")
    
    def run_tracking_loop(self):
        """Main tracking loop"""
        if not self.start_webcam():
            return
        
        logger.info("Starting eye tracking loop...")
        
        try:
            while self.is_tracking_enabled:
                ret, frame = self.webcam.read()
                if not ret:
                    logger.error("Failed to read frame from webcam")
                    break
                
                # Check if user is looking at screen
                is_focused = self.is_looking_at_screen(frame)
                self.update_focus_state(is_focused)
                
                # Save data periodically
                if time.time() - self.last_save_time > self.save_interval:
                    self.save_tracking_data()
                
                # Small delay to prevent excessive CPU usage
                time.sleep(0.1)  # 10 FPS
                
        except Exception as e:
            logger.error(f"Error in tracking loop: {e}")
        finally:
            self.stop_webcam()
    
    def start_tracking(self, user_id, module_id, section_id=None):
        """Start tracking for a specific module"""
        self.current_user_id = user_id
        self.current_module_id = module_id
        self.current_section_id = section_id
        self.accumulated_time = 0
        self.is_tracking_enabled = True
        
        # Start tracking in a separate thread
        self.tracking_thread = threading.Thread(target=self.run_tracking_loop)
        self.tracking_thread.daemon = True
        self.tracking_thread.start()
        
        logger.info(f"Started tracking for user {user_id}, module {module_id}")
    
    def stop_tracking(self):
        """Stop tracking"""
        self.is_tracking_enabled = False
        self.pause_session()
        
        if hasattr(self, 'tracking_thread'):
            self.tracking_thread.join(timeout=5)
        
        logger.info("Tracking stopped")
    
    def get_status(self):
        """Get current tracking status"""
        return {
            'is_tracking_enabled': getattr(self, 'is_tracking_enabled', False),
            'is_focused': self.is_focused,
            'is_session_active': self.is_tracking,
            'total_time': self.get_current_session_time(),
            'current_module': self.current_module_id,
            'current_section': self.current_section_id
        }

# Flask API for communication with frontend
app = Flask(__name__)
CORS(app)  # Enable CORS for browser requests

# Global eye tracker instance
eye_tracker = EyeTrackingService()

@app.route('/api/start_tracking', methods=['POST'])
def start_tracking():
    """API endpoint to start eye tracking"""
    data = request.get_json()
    user_id = data.get('user_id')
    module_id = data.get('module_id')
    section_id = data.get('section_id')
    
    if not user_id or not module_id:
        return jsonify({'success': False, 'error': 'Missing required parameters'}), 400
    
    try:
        eye_tracker.start_tracking(user_id, module_id, section_id)
        return jsonify({'success': True, 'message': 'Eye tracking started'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/stop_tracking', methods=['POST'])
def stop_tracking():
    """API endpoint to stop eye tracking"""
    try:
        eye_tracker.stop_tracking()
        return jsonify({'success': True, 'message': 'Eye tracking stopped'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/status', methods=['GET'])
def get_status():
    """API endpoint to get tracking status"""
    try:
        status = eye_tracker.get_status()
        
        # Add current frame if available
        frame_data = eye_tracker.get_current_frame_base64()
        if frame_data:
            status['current_frame'] = frame_data
            
        return jsonify({'success': True, 'status': status})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/frame', methods=['GET'])
def get_current_frame():
    """API endpoint to get current annotated frame"""
    try:
        frame_data = eye_tracker.get_current_frame_base64()
        if frame_data:
            return jsonify({
                'success': True, 
                'frame': frame_data,
                'timestamp': datetime.now().isoformat()
            })
        else:
            return jsonify({'success': False, 'error': 'No frame available'}), 404
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'success': True, 
        'message': 'Eye tracking service is running',
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    logger.info("Starting Eye Tracking Service...")
    app.run(host='127.0.0.1', port=5000, debug=True)
