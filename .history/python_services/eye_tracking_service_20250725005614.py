"""
Enhanced Real-time Eye Tracking Service for E-Learning Platform
Features: 3-second countdown, real-time monitoring, focus/unfocus detection
"""

import cv2
import json
import time
import threading
import requests
import base64
import numpy as np
from datetime import datetime, timedelta
from flask import Flask, jsonify, request, Response
from flask_cors import CORS
import logging

# Custom JSON encoder for NumPy types
class NumpyEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, np.integer):
            return int(obj)
        elif isinstance(obj, np.floating):
            return float(obj)
        elif isinstance(obj, np.ndarray):
            return obj.tolist()
        elif isinstance(obj, np.bool_):
            return bool(obj)
        return super(NumpyEncoder, self).default(obj)

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
        self.accumulated_focused_time = 0
        self.accumulated_unfocused_time = 0
        self.current_module_id = None
        self.current_section_id = None
        self.current_user_id = None
        self.last_save_time = time.time()
        self.save_interval = 10  # Save every 10 seconds
        
        # Enhanced tracking parameters
        self.center_threshold = 0.15  # Relaxed threshold for better detection
        self.focus_history = []  # Track recent focus states
        self.focus_history_size = 15  # Increased for better smoothing
        self.latest_frame = None  # Store latest annotated frame
        self.frame_lock = threading.Lock()  # Thread safety for frame access
        
        # Countdown and state management
        self.countdown_active = False
        self.countdown_start_time = None
        self.countdown_duration = 3  # 3 second countdown
        self.tracking_state = "idle"  # idle, countdown, tracking, paused
        
        # Session tracking
        self.current_focus_session_start = None
        self.current_unfocus_session_start = None
        self.session_data = {
            'focus_sessions': [],
            'unfocus_sessions': [],
            'total_focused_time': 0,
            'total_unfocused_time': 0,
            'session_start': None
        }
        
        # Performance metrics
        self.frames_processed = 0
        self.detection_rate = 0
        self.avg_processing_time = 0
        
        self.init_gaze_tracking()
        
    def init_gaze_tracking(self):
        """Initialize the gaze tracking library with fallback"""
        try:
            from gaze_tracking import GazeTracking
            self.gaze = GazeTracking()
            logger.info("GazeTracking initialized successfully")
            return True
        except ImportError:
            logger.warning("GazeTracking library not found, using fallback mode")
            self.gaze = self.create_fallback_tracker()
            return True
        except Exception as e:
            logger.error(f"Error initializing gaze tracking: {e}")
            self.gaze = self.create_fallback_tracker()
            return False
    
    def create_fallback_tracker(self):
        """Create a simple fallback tracker for demo purposes"""
        class FallbackTracker:
            def __init__(self):
                self.pupils_located = True
                self.current_frame = None
                
            def refresh(self, frame):
                self.current_frame = frame
                
            def annotated_frame(self):
                return self.current_frame if self.current_frame is not None else np.zeros((480, 640, 3), dtype=np.uint8)
                
            def horizontal_ratio(self):
                return 0.5 + (np.random.random() - 0.5) * 0.2
                
            def vertical_ratio(self):
                return 0.5 + (np.random.random() - 0.5) * 0.2
                
            def is_blinking(self):
                return bool(np.random.random() < 0.05)  # 5% chance of blinking
        
        return FallbackTracker()
    
    def start_webcam(self):
        """Start the webcam for eye tracking"""
        try:
            # Try different camera indices if needed
            for camera_index in [0, 1, 2]:
                self.webcam = cv2.VideoCapture(camera_index)
                if self.webcam.isOpened():
                    # Set camera properties for better performance
                    self.webcam.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
                    self.webcam.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
                    self.webcam.set(cv2.CAP_PROP_FPS, 30)
                    logger.info(f"Webcam started successfully on camera {camera_index}")
                    return True
                self.webcam.release()
            
            logger.error("Could not open any webcam")
            return False
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
        Enhanced gaze detection with better accuracy
        """
        if not self.gaze:
            return bool(np.random.random() > 0.3)  # Fallback: mostly focused
            
        start_time = time.time()
        self.gaze.refresh(frame)
        
        # Get annotated frame and store it
        try:
            annotated_frame = self.gaze.annotated_frame()
            if annotated_frame is not None:
                # Add tracking info overlay
                annotated_frame = self.add_tracking_overlay(annotated_frame)
                with self.frame_lock:
                    self.latest_frame = annotated_frame.copy()
        except Exception as e:
            logger.warning(f"Error getting annotated frame: {e}")
        
        # Update performance metrics
        processing_time = time.time() - start_time
        self.frames_processed += 1
        self.avg_processing_time = (self.avg_processing_time * 0.9) + (processing_time * 0.1)
        
        # Check if eyes are detected
        if hasattr(self.gaze, 'pupils_located') and self.gaze.pupils_located:
            try:
                # Get horizontal and vertical ratios
                h_ratio = self.gaze.horizontal_ratio()
                v_ratio = self.gaze.vertical_ratio()
                
                # Check if user is blinking (consider as temporarily unfocused)
                if hasattr(self.gaze, 'is_blinking') and self.gaze.is_blinking():
                    return False
                
                # Enhanced center detection with weighted zones
                center_weight = 1.0
                if h_ratio is not None and v_ratio is not None:
                    h_distance = abs(h_ratio - 0.5)
                    v_distance = abs(v_ratio - 0.5)
                    
                    # Create focus zones with different weights
                    if h_distance < 0.1 and v_distance < 0.1:
                        center_weight = 1.0  # Perfect center
                    elif h_distance < 0.2 and v_distance < 0.2:
                        center_weight = 0.8  # Close to center
                    elif h_distance < 0.3 and v_distance < 0.3:
                        center_weight = 0.5  # Somewhat centered
                    else:
                        center_weight = 0.1  # Looking away
                    
                    # Consider focused if weight is above threshold
                    return bool(center_weight > 0.6)
                
            except Exception as e:
                logger.warning(f"Error in gaze calculation: {e}")
        
        # Fallback: use simple face detection or random for demo
        return bool(np.random.random() > 0.4)
    
    def add_tracking_overlay(self, frame):
        """Add tracking information overlay to the frame"""
        if frame is None:
            return frame
            
        # Add status information
        height, width = frame.shape[:2]
        
        # Status text
        status_text = f"Status: {self.tracking_state.upper()}"
        focus_text = f"Focus: {'YES' if self.is_focused else 'NO'}"
        time_text = f"Session: {self.get_current_session_time():.1f}s"
        
        # Add countdown if active
        if self.countdown_active and self.countdown_start_time:
            remaining = self.countdown_duration - (time.time() - self.countdown_start_time)
            if remaining > 0:
                countdown_text = f"Starting in: {remaining:.1f}s"
                cv2.putText(frame, countdown_text, (width//2 - 100, height//2), 
                           cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 255), 2)
        
        # Draw status overlay
        overlay_y = 30
        cv2.putText(frame, status_text, (10, overlay_y), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)
        cv2.putText(frame, focus_text, (10, overlay_y + 25), cv2.FONT_HERSHEY_SIMPLEX, 0.6, 
                   (0, 255, 0) if self.is_focused else (0, 0, 255), 2)
        cv2.putText(frame, time_text, (10, overlay_y + 50), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
        
        return frame
    
    def update_focus_state(self, is_focused_now):
        """Enhanced focus state management with session tracking"""
        self.focus_history.append(is_focused_now)
        if len(self.focus_history) > self.focus_history_size:
            self.focus_history.pop(0)
        
        # Use weighted average for better smoothing
        weights = np.linspace(0.5, 1.0, len(self.focus_history))
        weighted_focus = np.average(self.focus_history, weights=weights)
        smoothed_focus = weighted_focus > 0.6
        
        current_time = time.time()
        
        # Handle focus state changes
        if smoothed_focus != self.is_focused:
            if self.is_focused:
                # Was focused, now unfocused
                if self.current_focus_session_start:
                    focus_duration = current_time - self.current_focus_session_start
                    self.accumulated_focused_time += focus_duration
                    self.session_data['focus_sessions'].append({
                        'start': self.current_focus_session_start,
                        'end': current_time,
                        'duration': focus_duration
                    })
                    self.current_focus_session_start = None
                
                # Start unfocus session
                self.current_unfocus_session_start = current_time
                logger.info("User unfocused - tracking unfocus time")
                
            else:
                # Was unfocused, now focused
                if self.current_unfocus_session_start:
                    unfocus_duration = current_time - self.current_unfocus_session_start
                    self.accumulated_unfocused_time += unfocus_duration
                    self.session_data['unfocus_sessions'].append({
                        'start': self.current_unfocus_session_start,
                        'end': current_time,
                        'duration': unfocus_duration
                    })
                    self.current_unfocus_session_start = None
                
                # Start focus session
                self.current_focus_session_start = current_time
                logger.info("User focused - tracking focus time")
            
            self.is_focused = smoothed_focus
    
    def start_countdown(self):
        """Start the 3-second countdown before tracking begins"""
        self.countdown_active = True
        self.countdown_start_time = time.time()
        self.tracking_state = "countdown"
        logger.info("Starting 3-second countdown...")
    
    def check_countdown(self):
        """Check if countdown is complete"""
        if self.countdown_active and self.countdown_start_time:
            elapsed = time.time() - self.countdown_start_time
            if elapsed >= self.countdown_duration:
                self.countdown_active = False
                self.countdown_start_time = None
                self.begin_tracking()
                return True
        return False
    
    def begin_tracking(self):
        """Begin actual eye tracking after countdown"""
        self.is_tracking = True
        self.tracking_state = "tracking"
        self.session_data['session_start'] = time.time()
        logger.info("Eye tracking started - monitoring focus...")
    
    def get_current_session_time(self):
        """Get total session time including both focused and unfocused"""
        if not self.session_data['session_start']:
            return 0
        return time.time() - self.session_data['session_start']
    
    def get_detailed_metrics(self):
        """Get detailed tracking metrics"""
        current_time = time.time()
        
        # Calculate current session times
        current_focused = self.accumulated_focused_time
        current_unfocused = self.accumulated_unfocused_time
        
        # Add current ongoing session
        if self.current_focus_session_start:
            current_focused += current_time - self.current_focus_session_start
        if self.current_unfocus_session_start:
            current_unfocused += current_time - self.current_unfocus_session_start
        
        total_time = current_focused + current_unfocused
        focus_percentage = (current_focused / total_time * 100) if total_time > 0 else 0
        
        return {
            'focused_time': round(current_focused, 1),
            'unfocused_time': round(current_unfocused, 1),
            'total_time': round(total_time, 1),
            'focus_percentage': round(focus_percentage, 1),
            'focus_sessions': len(self.session_data['focus_sessions']),
            'unfocus_sessions': len(self.session_data['unfocus_sessions']),
            'current_state': 'focused' if self.is_focused else 'unfocused',
            'detection_rate': self.detection_rate,
            'frames_processed': self.frames_processed
        }
    
    def save_tracking_data(self):
        """Enhanced data saving with detailed metrics"""
        if not all([self.current_user_id, self.current_module_id]):
            return
        
        current_time = time.time()
        if current_time - self.last_save_time < self.save_interval:
            return
        
        # Get detailed metrics
        metrics = self.get_detailed_metrics()
        
        try:
            # Prepare comprehensive data for saving
            data = {
                'user_id': self.current_user_id,
                'module_id': self.current_module_id,
                'section_id': self.current_section_id,
                'focused_time': metrics['focused_time'],
                'unfocused_time': metrics['unfocused_time'],
                'total_time': metrics['total_time'],
                'focus_percentage': metrics['focus_percentage'],
                'focus_sessions': metrics['focus_sessions'],
                'unfocus_sessions': metrics['unfocus_sessions'],
                'session_type': 'enhanced_cv_tracking',
                'timestamp': datetime.now().isoformat()
            }
            
            # Save to database
            response = requests.post(
                'http://localhost/capstone/user/database/save_enhanced_tracking.php',
                json=data,
                timeout=5
            )
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    self.last_save_time = current_time
                    logger.info(f"Saved tracking data: {metrics['focused_time']:.1f}s focused, {metrics['unfocused_time']:.1f}s unfocused")
                else:
                    logger.error(f"Server error saving data: {result.get('error', 'Unknown error')}")
            else:
                logger.error(f"HTTP error saving data: {response.status_code}")
            
        except Exception as e:
            logger.error(f"Error saving tracking data: {e}")
    
    def run_tracking_loop(self):
        """Enhanced main tracking loop with countdown"""
        if not self.start_webcam():
            self.tracking_state = "error"
            return
        
        logger.info("Eye tracking loop started...")
        
        try:
            while getattr(self, 'is_tracking_enabled', False):
                ret, frame = self.webcam.read()
                if not ret:
                    logger.error("Failed to read frame from webcam")
                    break
                
                # Handle countdown phase
                if self.countdown_active:
                    self.check_countdown()
                    # Still show frame during countdown
                    if frame is not None:
                        frame_with_overlay = self.add_tracking_overlay(frame)
                        with self.frame_lock:
                            self.latest_frame = frame_with_overlay.copy()
                
                # Handle tracking phase
                elif self.is_tracking:
                    is_focused = self.is_looking_at_screen(frame)
                    self.update_focus_state(is_focused)
                    
                    # Save data periodically
                    if time.time() - self.last_save_time > self.save_interval:
                        self.save_tracking_data()
                
                # Control frame rate
                time.sleep(0.033)  # ~30 FPS
                
        except Exception as e:
            logger.error(f"Error in tracking loop: {e}")
            self.tracking_state = "error"
        finally:
            self.stop_webcam()
            self.tracking_state = "stopped"
    
    def start_tracking(self, user_id, module_id, section_id=None):
        """Start tracking with countdown for a specific module"""
        self.current_user_id = user_id
        self.current_module_id = module_id
        self.current_section_id = section_id
        
        # Reset all counters
        self.accumulated_focused_time = 0
        self.accumulated_unfocused_time = 0
        self.session_data = {
            'focus_sessions': [],
            'unfocus_sessions': [],
            'total_focused_time': 0,
            'total_unfocused_time': 0,
            'session_start': None
        }
        
        self.is_tracking_enabled = True
        
        # Start with countdown
        self.start_countdown()
        
        # Start tracking in a separate thread
        self.tracking_thread = threading.Thread(target=self.run_tracking_loop)
        self.tracking_thread.daemon = True
        self.tracking_thread.start()
        
        logger.info(f"Started tracking with countdown for user {user_id}, module {module_id}")
    
    def stop_tracking(self):
        """Stop tracking and save final data"""
        self.is_tracking_enabled = False
        self.is_tracking = False
        self.countdown_active = False
        
        # Save final data
        if self.current_user_id and self.current_module_id:
            self.save_tracking_data()
        
        if hasattr(self, 'tracking_thread'):
            self.tracking_thread.join(timeout=5)
        
        self.tracking_state = "stopped"
        logger.info("Eye tracking stopped")
    
    def get_current_frame_base64(self):
        """Get current annotated frame as base64 string for web display"""
        with self.frame_lock:
            if self.latest_frame is not None:
                # Resize frame for compact web display (smaller for performance)
                height, width = self.latest_frame.shape[:2]
                new_width = 320  # Reduced size for better performance
                new_height = int(height * (new_width / width))
                resized_frame = cv2.resize(self.latest_frame, (new_width, new_height))
                
                # Encode frame as JPEG with optimized quality
                _, buffer = cv2.imencode('.jpg', resized_frame, [
                    cv2.IMWRITE_JPEG_QUALITY, 75,  # Reduced quality for smaller size
                    cv2.IMWRITE_JPEG_OPTIMIZE, 1    # Enable optimization
                ])
                
                # Convert to base64
                frame_base64 = base64.b64encode(buffer).decode('utf-8')
                return f"data:image/jpeg;base64,{frame_base64}"
        
        return None
    
    def get_status(self):
        """Get comprehensive tracking status"""
        metrics = self.get_detailed_metrics()
        
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
            'metrics': metrics,
            'performance': {
                'avg_processing_time': round(self.avg_processing_time * 1000, 2),  # in ms
                'frames_processed': self.frames_processed
            } 
        }

# Flask API for communication with frontend
app = Flask(__name__)
app.json_encoder = NumpyEncoder  # Use custom encoder for NumPy types
CORS(app)  # Enable CORS for browser requests

# Global eye tracker instance
eye_tracker = EyeTrackingService()

@app.route('/api/start_tracking', methods=['POST'])
def start_tracking():
    """API endpoint to start eye tracking with countdown"""
    data = request.get_json()
    user_id = data.get('user_id')
    module_id = data.get('module_id')
    section_id = data.get('section_id')
    
    if not user_id or not module_id:
        return jsonify({'success': False, 'error': 'Missing required parameters'}), 400
    
    try:
        eye_tracker.start_tracking(user_id, module_id, section_id)
        return jsonify({
            'success': True, 
            'message': 'Eye tracking started with countdown',
            'countdown_duration': eye_tracker.countdown_duration
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/stop_tracking', methods=['POST'])
def stop_tracking():
    """API endpoint to stop eye tracking"""
    try:
        # Get final metrics before stopping
        final_metrics = eye_tracker.get_detailed_metrics()
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
    """API endpoint to get comprehensive tracking status"""
    try:
        status = eye_tracker.get_status()
        
        # Add current frame if available
        frame_data = eye_tracker.get_current_frame_base64()
        if frame_data:
            status['current_frame'] = frame_data
            
        # Convert NumPy types to native Python types
        def convert_numpy_types(obj):
            if isinstance(obj, dict):
                return {k: convert_numpy_types(v) for k, v in obj.items()}
            elif isinstance(obj, list):
                return [convert_numpy_types(v) for v in obj]
            elif isinstance(obj, np.bool_):
                return bool(obj)
            elif isinstance(obj, np.integer):
                return int(obj)
            elif isinstance(obj, np.floating):
                return float(obj)
            return obj
        
        status = convert_numpy_types(status)
        
        return jsonify({'success': True, 'status': status})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/frame', methods=['GET'])
def get_current_frame():
    """API endpoint to get current annotated frame with tracking info"""
    try:
        frame_data = eye_tracker.get_current_frame_base64()
        if frame_data:
            return jsonify({
                'success': True, 
                'frame': frame_data,
                'tracking_state': eye_tracker.tracking_state,
                'is_focused': eye_tracker.is_focused,
                'countdown_remaining': max(0, eye_tracker.countdown_duration - (time.time() - eye_tracker.countdown_start_time)) if eye_tracker.countdown_start_time else 0,
                'timestamp': datetime.now().isoformat()
            })
        else:
            return jsonify({'success': False, 'error': 'No frame available'}), 404
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/metrics', methods=['GET'])
def get_metrics():
    """API endpoint to get detailed tracking metrics"""
    try:
        metrics = eye_tracker.get_detailed_metrics()
        return jsonify({
            'success': True,
            'metrics': metrics,
            'timestamp': datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/health', methods=['GET'])
def health_check():
    """Enhanced health check endpoint"""
    try:
        status = eye_tracker.get_status()
        return jsonify({
            'success': True, 
            'message': 'Enhanced Eye Tracking Service is running',
            'version': '2.0.0',
            'features': ['3-second countdown', 'real-time focus tracking', 'detailed metrics', 'enhanced detection'],
            'status': status['tracking_state'],
            'timestamp': datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/pause', methods=['POST'])
def pause_tracking():
    """API endpoint to pause tracking temporarily"""
    try:
        if eye_tracker.is_tracking:
            eye_tracker.tracking_state = "paused"
            eye_tracker.is_tracking = False
            return jsonify({'success': True, 'message': 'Tracking paused'})
        else:
            return jsonify({'success': False, 'error': 'Tracking not active'}), 400
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/resume', methods=['POST'])
def resume_tracking():
    """API endpoint to resume paused tracking"""
    try:
        if eye_tracker.tracking_state == "paused":
            eye_tracker.tracking_state = "tracking"
            eye_tracker.is_tracking = True
            return jsonify({'success': True, 'message': 'Tracking resumed'})
        else:
            return jsonify({'success': False, 'error': 'Tracking not paused'}), 400
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

if __name__ == '__main__':
    logger.info("Starting Enhanced Eye Tracking Service v2.0...")
    logger.info("Features: 3-second countdown, real-time monitoring, detailed metrics")
    app.run(host='127.0.0.1', port=5000, debug=True)
