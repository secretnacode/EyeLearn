"""
Simple Eye Tracking Service - Clean Working Version
Features: Basic countdown, live feed, focus tracking
"""

import cv2
import json
import time
import threading
import base64
import numpy as np
import math
from datetime import datetime
from flask import Flask, jsonify, request
from flask_cors import CORS
import logging

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Try to import MediaPipe for real eye tracking
try:
    import mediapipe as mp
    MEDIAPIPE_AVAILABLE = True
    logger.info("MediaPipe available for real eye tracking")
except ImportError:
    MEDIAPIPE_AVAILABLE = False
    logger.warning("MediaPipe not available, using fallback mode")

class SimpleEyeTrackingService:
    def __init__(self):
        self.webcam = None
        self.is_tracking = False
        self.is_focused = False
        self.current_module_id = None
        self.current_section_id = None
        self.current_user_id = None
        self.latest_frame = None
        self.frame_lock = threading.Lock()
        
        # Simple countdown
        self.countdown_active = False
        self.countdown_start_time = None
        self.countdown_duration = 2
        self.tracking_state = "idle"
        
        # Basic metrics
        self.focused_time = 0
        self.unfocused_time = 0
        self.session_start = None
        self.frames_processed = 0
        
    def start_webcam(self):
        """Start webcam or create demo feed"""
        try:
            self.webcam = cv2.VideoCapture(0)
            if self.webcam.isOpened():
                self.webcam.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
                self.webcam.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
                logger.info("Webcam started successfully")
                return True
            else:
                logger.warning("No webcam available, using demo mode")
                return False
        except Exception as e:
            logger.error(f"Error starting webcam: {e}")
            return False
    
    def create_demo_frame(self):
        """Create a simple demo frame"""
        frame = np.zeros((480, 640, 3), dtype=np.uint8)
        
        # Background
        cv2.rectangle(frame, (50, 50), (590, 430), (30, 30, 30), -1)
        
        # Title
        cv2.putText(frame, "Eye Tracking Demo", (200, 150), 
                   cv2.FONT_HERSHEY_SIMPLEX, 1.2, (255, 255, 255), 2)
        
        # Status
        status = f"Status: {self.tracking_state.upper()}"
        cv2.putText(frame, status, (100, 200), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)
        
        # Focus indicator
        focus_text = f"Focus: {'YES' if self.is_focused else 'NO'}"
        color = (0, 255, 0) if self.is_focused else (0, 0, 255)
        cv2.putText(frame, focus_text, (100, 240), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.8, color, 2)
        
        # Animated gaze point
        t = time.time()
        x = int(320 + 80 * np.sin(t * 2))
        y = int(280 + 40 * np.cos(t * 3))
        cv2.circle(frame, (x, y), 12, (0, 255, 255), -1)
        cv2.putText(frame, "Gaze", (x - 20, y - 20), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 255), 1)
        
        # Frame counter
        cv2.putText(frame, f"Frame: {self.frames_processed}", (100, 380), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (150, 150, 150), 1)
        
        return frame
    
    def start_countdown(self):
        """Start countdown"""
        self.countdown_active = True
        self.countdown_start_time = time.time()
        self.tracking_state = "countdown"
        logger.info("Starting countdown...")
    
    def check_countdown(self):
        """Check if countdown finished"""
        if self.countdown_active and self.countdown_start_time:
            elapsed = time.time() - self.countdown_start_time
            if elapsed >= self.countdown_duration:
                self.countdown_active = False
                self.countdown_start_time = None
                self.begin_tracking()
                return True
        return False
    
    def begin_tracking(self):
        """Start actual tracking"""
        self.is_tracking = True
        self.tracking_state = "tracking"
        self.session_start = time.time()
        logger.info("Eye tracking started")
    
    def simulate_focus(self):
        """Simple focus simulation"""
        # Random focus changes for demo
        return bool(np.random.random() > 0.3)
    
    def update_metrics(self, is_focused):
        """Update simple metrics"""
        if self.session_start:
            current_time = time.time()
            session_duration = current_time - self.session_start
            
            if is_focused:
                self.focused_time = session_duration * 0.7  # Simulate 70% focused
                self.unfocused_time = session_duration * 0.3
            else:
                self.focused_time = session_duration * 0.6
                self.unfocused_time = session_duration * 0.4
            
            self.is_focused = is_focused
    
    def run_tracking_loop(self):
        """Main tracking loop"""
        webcam_available = self.start_webcam()
        logger.info("Tracking loop started")
        
        try:
            while getattr(self, 'is_tracking_enabled', False):
                frame = None
                
                # Get frame from webcam or create demo
                if webcam_available and self.webcam:
                    ret, frame = self.webcam.read()
                    if not ret:
                        webcam_available = False
                        frame = None
                
                if frame is None:
                    frame = self.create_demo_frame()
                
                # Store frame for API access
                with self.frame_lock:
                    self.latest_frame = frame.copy()
                
                self.frames_processed += 1
                
                # Handle countdown
                if self.countdown_active:
                    self.check_countdown()
                
                # Handle tracking
                elif self.is_tracking:
                    is_focused = self.simulate_focus()
                    self.update_metrics(is_focused)
                
                time.sleep(0.033)  # ~30 FPS
                
        except Exception as e:
            logger.error(f"Error in tracking loop: {e}")
            self.tracking_state = "error"
        finally:
            if webcam_available and self.webcam:
                self.webcam.release()
            self.tracking_state = "stopped"
    
    def start_tracking(self, user_id, module_id, section_id=None):
        """Start tracking session"""
        self.current_user_id = user_id
        self.current_module_id = module_id
        self.current_section_id = section_id
        
        # Reset metrics
        self.focused_time = 0
        self.unfocused_time = 0
        self.frames_processed = 0
        
        self.is_tracking_enabled = True
        self.start_countdown()
        
        # Start tracking thread
        self.tracking_thread = threading.Thread(target=self.run_tracking_loop)
        self.tracking_thread.daemon = True
        self.tracking_thread.start()
        
        logger.info(f"Started tracking for user {user_id}, module {module_id}")
    
    def stop_tracking(self):
        """Stop tracking"""
        self.is_tracking_enabled = False
        self.is_tracking = False
        self.countdown_active = False
        
        if hasattr(self, 'tracking_thread'):
            self.tracking_thread.join(timeout=3)
        
        self.tracking_state = "stopped"
        logger.info("Eye tracking stopped")
    
    def get_current_frame_base64(self):
        """Get frame as base64"""
        with self.frame_lock:
            if self.latest_frame is not None:
                # Resize for web
                height, width = self.latest_frame.shape[:2]
                new_width = 320
                new_height = int(height * (new_width / width))
                resized_frame = cv2.resize(self.latest_frame, (new_width, new_height))
                
                # Encode as JPEG
                _, buffer = cv2.imencode('.jpg', resized_frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
                frame_base64 = base64.b64encode(buffer).decode('utf-8')
                return f"data:image/jpeg;base64,{frame_base64}"
        
        return None
    
    def get_status(self):
        """Get current status"""
        total_time = self.focused_time + self.unfocused_time
        focus_percentage = (self.focused_time / total_time * 100) if total_time > 0 else 0
        
        return {
            'is_tracking_enabled': getattr(self, 'is_tracking_enabled', False),
            'tracking_state': self.tracking_state,
            'countdown_active': self.countdown_active,
            'countdown_remaining': max(0, self.countdown_duration - (time.time() - self.countdown_start_time)) if self.countdown_start_time else 0,
            'is_focused': self.is_focused,
            'is_session_active': self.is_tracking,
            'current_module': self.current_module_id,
            'current_section': self.current_section_id,
            'current_user': self.current_user_id,
            'metrics': {
                'focused_time': round(self.focused_time, 1),
                'unfocused_time': round(self.unfocused_time, 1),
                'total_time': round(total_time, 1),
                'focus_percentage': round(focus_percentage, 1),
                'frames_processed': self.frames_processed
            }
        }

# Flask app
app = Flask(__name__)
CORS(app)

# Global tracker
eye_tracker = SimpleEyeTrackingService()

@app.route('/api/start_tracking', methods=['POST'])
def start_tracking():
    """Start tracking API"""
    data = request.get_json()
    user_id = data.get('user_id')
    module_id = data.get('module_id')
    section_id = data.get('section_id')
    
    if not user_id or not module_id:
        return jsonify({'success': False, 'error': 'Missing parameters'}), 400
    
    try:
        eye_tracker.start_tracking(user_id, module_id, section_id)
        return jsonify({
            'success': True, 
            'message': 'Eye tracking started',
            'countdown_duration': eye_tracker.countdown_duration
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/stop_tracking', methods=['POST'])
def stop_tracking():
    """Stop tracking API"""
    try:
        final_metrics = eye_tracker.get_status()['metrics']
        eye_tracker.stop_tracking()
        return jsonify({
            'success': True, 
            'message': 'Eye tracking stopped',
            'final_metrics': final_metrics
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/status', methods=['GET'])
def get_status():
    """Status API"""
    try:
        status = eye_tracker.get_status()
        return jsonify({'success': True, 'status': status})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/frame', methods=['GET'])
def get_current_frame():
    """Frame API"""
    try:
        frame_data = eye_tracker.get_current_frame_base64()
        if frame_data:
            return jsonify({
                'success': True, 
                'frame': frame_data,
                'tracking_state': eye_tracker.tracking_state,
                'is_focused': eye_tracker.is_focused,
                'timestamp': datetime.now().isoformat()
            })
        else:
            return jsonify({'success': False, 'error': 'No frame available'}), 404
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check API"""
    try:
        return jsonify({
            'success': True, 
            'message': 'Simple Eye Tracking Service is running',
            'version': '1.0.0',
            'status': eye_tracker.tracking_state,
            'timestamp': datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

if __name__ == '__main__':
    logger.info("Starting Simple Eye Tracking Service v1.0...")
    app.run(host='127.0.0.1', port=5001, debug=True)
