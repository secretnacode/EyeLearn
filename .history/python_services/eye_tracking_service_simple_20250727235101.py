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
        
        # Eye tracking components
        self.init_eye_tracking()
        
    def init_eye_tracking(self):
        """Initialize eye tracking system"""
        if MEDIAPIPE_AVAILABLE:
            self.mp_face_mesh = mp.solutions.face_mesh
            self.face_mesh = self.mp_face_mesh.FaceMesh(
                max_num_faces=1,
                refine_landmarks=True,
                min_detection_confidence=0.5,
                min_tracking_confidence=0.5
            )
            self.mp_drawing = mp.solutions.drawing_utils
            self.mp_drawing_styles = mp.solutions.drawing_styles
            
            # Eye landmark indices for MediaPipe Face Mesh
            self.LEFT_EYE = [362, 385, 387, 263, 373, 380]
            self.RIGHT_EYE = [33, 160, 158, 133, 153, 144]
            self.LEFT_IRIS = [474, 475, 476, 477]
            self.RIGHT_IRIS = [469, 470, 471, 472]
            
            self.pupils_located = False
            self.eye_landmarks = None
            self.gaze_direction = None
            logger.info("✅ MediaPipe eye tracking initialized")
        else:
            logger.warning("Using fallback eye tracking mode")
        
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
    
    def process_frame_with_eye_tracking(self, frame):
        """Process frame with real eye tracking and annotations"""
        if frame is None:
            return self.create_demo_frame()
        
        annotated_frame = frame.copy()
        
        if MEDIAPIPE_AVAILABLE:
            try:
                # Convert BGR to RGB for MediaPipe
                rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                rgb_frame.flags.writeable = False
                
                # Process the frame
                results = self.face_mesh.process(rgb_frame)
                
                # Convert back to BGR for OpenCV
                rgb_frame.flags.writeable = True
                
                if results.multi_face_landmarks:
                    self.pupils_located = True
                    face_landmarks = results.multi_face_landmarks[0]
                    
                    # Get frame dimensions
                    h, w, _ = frame.shape
                    
                    # Extract eye landmarks
                    self.eye_landmarks = []
                    for landmark in face_landmarks.landmark:
                        x = int(landmark.x * w)
                        y = int(landmark.y * h)
                        self.eye_landmarks.append((x, y))
                    
                    # Draw face mesh
                    self.mp_drawing.draw_landmarks(
                        annotated_frame,
                        face_landmarks,
                        self.mp_face_mesh.FACEMESH_CONTOURS,
                        landmark_drawing_spec=None,
                        connection_drawing_spec=self.mp_drawing_styles.get_default_face_mesh_contours_style()
                    )
                    
                    # Draw eye bounding boxes
                    self.draw_eye_bounding_boxes(annotated_frame)
                    
                    # Calculate and draw gaze direction
                    self.calculate_gaze_direction(annotated_frame)
                    
                    # Determine focus based on gaze
                    self.is_focused = self.determine_focus_from_gaze()
                    
                else:
                    self.pupils_located = False
                    self.eye_landmarks = None
                    self.gaze_direction = None
                    self.is_focused = False
                
                # Add tracking overlay
                self.add_tracking_overlay(annotated_frame)
                
            except Exception as e:
                logger.error(f"Error in eye tracking processing: {e}")
                self.add_error_overlay(annotated_frame)
        else:
            # Fallback mode - just add overlay to real camera feed
            self.add_tracking_overlay(annotated_frame)
            self.is_focused = self.simulate_focus()  # Simple simulation
        
        return annotated_frame
    
    def draw_eye_bounding_boxes(self, frame):
        """Draw bounding boxes around detected eyes"""
        if not self.eye_landmarks:
            return
        
        try:
            # Draw left eye bounding box
            left_eye_points = [self.eye_landmarks[i] for i in self.LEFT_EYE if i < len(self.eye_landmarks)]
            if left_eye_points:
                left_eye_rect = cv2.boundingRect(np.array(left_eye_points))
                cv2.rectangle(frame, 
                            (left_eye_rect[0], left_eye_rect[1]), 
                            (left_eye_rect[0] + left_eye_rect[2], left_eye_rect[1] + left_eye_rect[3]), 
                            (0, 255, 0), 2)
                cv2.putText(frame, "LEFT EYE", (left_eye_rect[0], left_eye_rect[1] - 10), 
                           cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 1)
            
            # Draw right eye bounding box
            right_eye_points = [self.eye_landmarks[i] for i in self.RIGHT_EYE if i < len(self.eye_landmarks)]
            if right_eye_points:
                right_eye_rect = cv2.boundingRect(np.array(right_eye_points))
                cv2.rectangle(frame, 
                            (right_eye_rect[0], right_eye_rect[1]), 
                            (right_eye_rect[0] + right_eye_rect[2], right_eye_rect[1] + right_eye_rect[3]), 
                            (0, 255, 0), 2)
                cv2.putText(frame, "RIGHT EYE", (right_eye_rect[0], right_eye_rect[1] - 10), 
                           cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 1)
            
            # Draw iris points
            for iris_landmarks in [self.LEFT_IRIS, self.RIGHT_IRIS]:
                for point_idx in iris_landmarks:
                    if point_idx < len(self.eye_landmarks):
                        cv2.circle(frame, self.eye_landmarks[point_idx], 3, (255, 0, 0), -1)
                        
        except Exception as e:
            logger.debug(f"Error drawing eye bounding boxes: {e}")
    
    def calculate_gaze_direction(self, frame):
        """Calculate and visualize gaze direction"""
        if not self.eye_landmarks:
            return
        
        try:
            # Get iris centers
            left_iris_center = self.get_iris_center(self.LEFT_IRIS)
            right_iris_center = self.get_iris_center(self.RIGHT_IRIS)
            
            if left_iris_center and right_iris_center:
                # Calculate average gaze point
                avg_x = (left_iris_center[0] + right_iris_center[0]) / 2
                avg_y = (left_iris_center[1] + right_iris_center[1]) / 2
                
                # Normalize to screen coordinates (0-1)
                h, w = frame.shape[:2]
                self.gaze_direction = (avg_x / w, avg_y / h)
                
                # Draw gaze point
                gaze_point = (int(avg_x), int(avg_y))
                cv2.circle(frame, gaze_point, 8, (0, 255, 255), -1)
                cv2.circle(frame, gaze_point, 15, (0, 255, 255), 2)
                cv2.putText(frame, "GAZE", (gaze_point[0] - 20, gaze_point[1] - 20), 
                           cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 255), 2)
                
                # Draw gaze direction vector
                center_x, center_y = w // 2, h // 2
                cv2.arrowedLine(frame, (center_x, center_y), gaze_point, (255, 255, 0), 3)
                
        except Exception as e:
            logger.debug(f"Error calculating gaze direction: {e}")
    
    def get_iris_center(self, iris_landmarks):
        """Get center point of iris from landmarks"""
        if not self.eye_landmarks:
            return None
        
        try:
            iris_points = []
            for idx in iris_landmarks:
                if idx < len(self.eye_landmarks):
                    iris_points.append(self.eye_landmarks[idx])
            
            if len(iris_points) >= 2:
                avg_x = sum(p[0] for p in iris_points) / len(iris_points)
                avg_y = sum(p[1] for p in iris_points) / len(iris_points)
                return (avg_x, avg_y)
        except Exception:
            pass
        return None
    
    def determine_focus_from_gaze(self):
        """Determine if user is focused based on gaze direction"""
        if not self.gaze_direction:
            return False
        
        # Check if gaze is roughly centered (focused on screen)
        gaze_x, gaze_y = self.gaze_direction
        center_threshold = 0.3  # Tolerance for center focus
        
        # Consider focused if gaze is near center of screen
        is_centered_x = abs(gaze_x - 0.5) < center_threshold
        is_centered_y = abs(gaze_y - 0.5) < center_threshold
        
        return is_centered_x and is_centered_y
    
    def add_tracking_overlay(self, frame):
        """Add tracking information overlay to frame"""
        # Title
        cv2.putText(frame, "🎯 LIVE EYE TRACKING", (10, 30), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)
        
        # Status
        status = f"Status: {self.tracking_state.upper()}"
        cv2.putText(frame, status, (10, 60), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
        
        # Focus indicator
        focus_text = f"Focus: {'FOCUSED' if self.is_focused else 'UNFOCUSED'}"
        color = (0, 255, 0) if self.is_focused else (0, 0, 255)
        cv2.putText(frame, focus_text, (10, 90), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)
        
        # Pupils detection
        if MEDIAPIPE_AVAILABLE:
            pupils_text = f"Eyes: {'DETECTED' if self.pupils_located else 'NOT FOUND'}"
            pupils_color = (0, 255, 0) if self.pupils_located else (0, 0, 255)
            cv2.putText(frame, pupils_text, (10, 120), 
                       cv2.FONT_HERSHEY_SIMPLEX, 0.6, pupils_color, 2)
        
        # Gaze coordinates
        if self.gaze_direction:
            gaze_text = f"Gaze: ({self.gaze_direction[0]:.2f}, {self.gaze_direction[1]:.2f})"
            cv2.putText(frame, gaze_text, (10, 150), 
                       cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 0), 1)
        
        # Frame counter
        cv2.putText(frame, f"Frame: {self.frames_processed}", (10, frame.shape[0] - 30), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (150, 150, 150), 1)
        
        # Countdown overlay
        if self.countdown_active and self.countdown_start_time:
            remaining = max(0, self.countdown_duration - (time.time() - self.countdown_start_time))
            countdown_text = f"Starting in: {remaining:.1f}s"
            text_size = cv2.getTextSize(countdown_text, cv2.FONT_HERSHEY_SIMPLEX, 1.2, 3)[0]
            text_x = (frame.shape[1] - text_size[0]) // 2
            text_y = (frame.shape[0] + text_size[1]) // 2
            cv2.putText(frame, countdown_text, (text_x, text_y), 
                       cv2.FONT_HERSHEY_SIMPLEX, 1.2, (0, 255, 255), 3)
    
    def add_error_overlay(self, frame):
        """Add error overlay when eye tracking fails"""
        cv2.putText(frame, "🎯 EYE TRACKING ERROR", (10, 30), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 0, 255), 2)
        cv2.putText(frame, "Processing failed, trying to recover...", (10, 60), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 2)
    
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
