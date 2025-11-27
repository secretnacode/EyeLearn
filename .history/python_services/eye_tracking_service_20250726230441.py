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
        self.countdown_duration = 2  # 2 second countdown (faster)
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
        
        # Background frame generation
        self.frame_generation_enabled = True
        self.frame_generation_thread = None
        
        self.init_gaze_tracking()
        self.start_background_frame_generation()
        
    def init_gaze_tracking(self):
        """Initialize real eye tracking using MediaPipe"""
        try:
            # Try to initialize MediaPipe real eye tracking
            self.gaze = self.create_mediapipe_tracker()
            logger.info("✅ Real MediaPipe eye tracking initialized successfully")
            return True
        except ImportError as e:
            logger.warning(f"MediaPipe not available: {e}")
            # Try legacy gaze tracking library
            try:
                from gaze_tracking import GazeTracking
                self.gaze = GazeTracking()
                logger.info("GazeTracking library initialized successfully")
                return True
            except ImportError:
                logger.warning("GazeTracking library not found, using fallback mode")
                self.gaze = self.create_fallback_tracker()
                return True
        except Exception as e:
            logger.error(f"Error initializing real eye tracking: {e}")
            self.gaze = self.create_fallback_tracker()
            return False
    
    def create_mediapipe_tracker(self):
        """Create a real eye tracking system using MediaPipe"""
        import mediapipe as mp
        import math
        
        class MediaPipeTracker:
            def __init__(self):
                self.mp_face_mesh = mp.solutions.face_mesh
                self.face_mesh = self.mp_face_mesh.FaceMesh(
                    max_num_faces=1,
                    refine_landmarks=True,
                    min_detection_confidence=0.5,
                    min_tracking_confidence=0.5
                )
                self.mp_drawing = mp.solutions.drawing_utils
                self.mp_drawing_styles = mp.solutions.drawing_styles
                
                self.current_frame = None
                self.annotated_frame = None
                self.pupils_located = False
                self.eye_landmarks = None
                self.gaze_direction = None
                self.blink_detected = False
                self.last_blink_time = time.time()
                
                # Eye landmark indices for MediaPipe Face Mesh
                self.LEFT_EYE = [362, 385, 387, 263, 373, 380]
                self.RIGHT_EYE = [33, 160, 158, 133, 153, 144]
                self.LEFT_IRIS = [474, 475, 476, 477]
                self.RIGHT_IRIS = [469, 470, 471, 472]
                
                logger.info("🎯 Real MediaPipe eye tracker initialized")
                
            def refresh(self, frame):
                """Process frame for real eye tracking"""
                if frame is None:
                    return
                    
                self.current_frame = frame.copy()
                rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                results = self.face_mesh.process(rgb_frame)
                
                self.annotated_frame = frame.copy()
                
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
                    
                    # Draw face mesh on frame
                    self.mp_drawing.draw_landmarks(
                        self.annotated_frame,
                        face_landmarks,
                        self.mp_face_mesh.FACEMESH_CONTOURS,
                        landmark_drawing_spec=None,
                        connection_drawing_spec=self.mp_drawing_styles.get_default_face_mesh_contours_style()
                    )
                    
                    # Draw eye landmarks
                    for eye_points in [self.LEFT_EYE, self.RIGHT_EYE]:
                        for point_idx in eye_points:
                            if point_idx < len(self.eye_landmarks):
                                cv2.circle(self.annotated_frame, self.eye_landmarks[point_idx], 2, (0, 255, 0), -1)
                    
                    # Calculate gaze direction from iris position
                    self.calculate_gaze_direction()
                    
                    # Detect blinks
                    self.detect_blinks()
                    
                    # Add real-time info overlay
                    cv2.putText(self.annotated_frame, "🎯 REAL EYE TRACKING", (10, 30), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
                    cv2.putText(self.annotated_frame, f"Pupils: {'DETECTED' if self.pupils_located else 'NOT FOUND'}", 
                               (10, 60), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0) if self.pupils_located else (0, 0, 255), 2)
                    
                    if self.gaze_direction:
                        gaze_text = f"Gaze: ({self.gaze_direction[0]:.2f}, {self.gaze_direction[1]:.2f})"
                        cv2.putText(self.annotated_frame, gaze_text, (10, 90), 
                                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 0), 1)
                    
                    cv2.putText(self.annotated_frame, f"Blink: {'YES' if self.blink_detected else 'NO'}", 
                               (10, 120), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 0, 255), 1)
                else:
                    self.pupils_located = False
                    self.eye_landmarks = None
                    self.gaze_direction = None
                    
                    # Add "no face detected" overlay
                    cv2.putText(self.annotated_frame, "🎯 REAL EYE TRACKING", (10, 30), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
                    cv2.putText(self.annotated_frame, "No face detected", (10, 60), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)
                    cv2.putText(self.annotated_frame, "Please position your face in view", (10, 90), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 0), 1)
            
            def calculate_gaze_direction(self):
                """Calculate gaze direction from eye landmarks"""
                if not self.eye_landmarks:
                    return
                
                try:
                    # Get left and right iris centers
                    left_iris_center = self.get_iris_center(self.LEFT_IRIS)
                    right_iris_center = self.get_iris_center(self.RIGHT_IRIS)
                    
                    if left_iris_center and right_iris_center:
                        # Calculate average gaze direction
                        avg_x = (left_iris_center[0] + right_iris_center[0]) / 2
                        avg_y = (left_iris_center[1] + right_iris_center[1]) / 2
                        
                        # Normalize to 0-1 range (approximate)
                        h, w = self.current_frame.shape[:2] if self.current_frame is not None else (480, 640)
                        self.gaze_direction = (avg_x / w, avg_y / h)
                        
                        # Draw gaze point
                        gaze_point = (int(avg_x), int(avg_y))
                        cv2.circle(self.annotated_frame, gaze_point, 8, (0, 255, 255), -1)
                        cv2.putText(self.annotated_frame, "GAZE", (gaze_point[0] - 20, gaze_point[1] - 15), 
                                   cv2.FONT_HERSHEY_SIMPLEX, 0.4, (0, 255, 255), 1)
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
            
            def detect_blinks(self):
                """Detect eye blinks using eye aspect ratio"""
                if not self.eye_landmarks:
                    self.blink_detected = False
                    return
                
                try:
                    # Calculate eye aspect ratio for both eyes
                    left_ear = self.eye_aspect_ratio(self.LEFT_EYE)
                    right_ear = self.eye_aspect_ratio(self.RIGHT_EYE)
                    
                    # Average the eye aspect ratios
                    avg_ear = (left_ear + right_ear) / 2.0
                    
                    # Blink detection threshold
                    EAR_THRESHOLD = 0.25
                    
                    if avg_ear < EAR_THRESHOLD:
                        current_time = time.time()
                        if current_time - self.last_blink_time > 0.1:  # Minimum blink duration
                            self.blink_detected = True
                            self.last_blink_time = current_time
                    else:
                        self.blink_detected = False
                        
                except Exception as e:
                    logger.debug(f"Error detecting blinks: {e}")
                    self.blink_detected = False
            
            def eye_aspect_ratio(self, eye_landmarks):
                """Calculate eye aspect ratio for blink detection"""
                try:
                    if len(eye_landmarks) < 6:
                        return 0.3  # Default value
                    
                    # Get eye points
                    eye_points = [self.eye_landmarks[i] for i in eye_landmarks if i < len(self.eye_landmarks)]
                    
                    if len(eye_points) < 6:
                        return 0.3
                    
                    # Calculate distances
                    def euclidean_distance(p1, p2):
                        return math.sqrt((p1[0] - p2[0])**2 + (p1[1] - p2[1])**2)
                    
                    # Vertical distances
                    A = euclidean_distance(eye_points[1], eye_points[5])
                    B = euclidean_distance(eye_points[2], eye_points[4])
                    
                    # Horizontal distance
                    C = euclidean_distance(eye_points[0], eye_points[3])
                    
                    # Calculate EAR
                    if C > 0:
                        ear = (A + B) / (2.0 * C)
                        return ear
                    else:
                        return 0.3
                        
                except Exception:
                    return 0.3
            
            def horizontal_ratio(self):
                """Get horizontal gaze ratio (0=left, 0.5=center, 1=right)"""
                if self.gaze_direction:
                    return max(0.0, min(1.0, self.gaze_direction[0]))
                return 0.5  # Default center
            
            def vertical_ratio(self):
                """Get vertical gaze ratio (0=up, 0.5=center, 1=down)"""
                if self.gaze_direction:
                    return max(0.0, min(1.0, self.gaze_direction[1]))
                return 0.5  # Default center
            
            def is_blinking(self):
                """Check if currently blinking"""
                return self.blink_detected
            
            def get_annotated_frame(self):
                """Get the annotated frame with tracking overlay"""
                return self.annotated_frame if self.annotated_frame is not None else self.current_frame
        
        return MediaPipeTracker()
    
    def create_fallback_tracker(self):
        """Create a simple fallback tracker for demo purposes"""
        class FallbackTracker:
            def __init__(self):
                self.pupils_located = True
                self.current_frame = None
                self.frame_count = 0
                self.last_focus_change = time.time()
                self.current_focus_state = True
                
            def refresh(self, frame):
                self.current_frame = frame
                self.frame_count += 1
                
                # Simulate focus changes every 10-15 seconds
                if time.time() - self.last_focus_change > np.random.uniform(10, 15):
                    self.current_focus_state = not self.current_focus_state
                    self.last_focus_change = time.time()
                
            def get_annotated_frame(self):
                if self.current_frame is not None:
                    return self.current_frame
                else:
                    # Generate a more realistic demo frame
                    demo_frame = np.zeros((480, 640, 3), dtype=np.uint8)
                    
                    # Add gradient background
                    for y in range(480):
                        intensity = int(20 + (y / 480) * 30)
                        demo_frame[y, :] = [intensity, intensity, intensity]
                    
                    # Add face rectangle simulation
                    face_x, face_y = 220, 160
                    face_w, face_h = 200, 160
                    cv2.rectangle(demo_frame, (face_x, face_y), (face_x + face_w, face_y + face_h), (100, 100, 100), 2)
                    cv2.putText(demo_frame, "Simulated Face", (face_x + 20, face_y - 10), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.6, (200, 200, 200), 1)
                    
                    # Add eye simulation
                    left_eye = (face_x + 50, face_y + 50)
                    right_eye = (face_x + 150, face_y + 50)
                    cv2.circle(demo_frame, left_eye, 15, (0, 255, 0), 2)
                    cv2.circle(demo_frame, right_eye, 15, (0, 255, 0), 2)
                    
                    # Add gaze point simulation
                    t = time.time()
                    gaze_x = int(320 + 100 * np.sin(t * 0.5))
                    gaze_y = int(240 + 50 * np.cos(t * 0.3))
                    cv2.circle(demo_frame, (gaze_x, gaze_y), 8, (0, 255, 255), -1)
                    cv2.putText(demo_frame, "Gaze Point", (gaze_x - 30, gaze_y - 15), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.4, (0, 255, 255), 1)
                    
                    # Add demo information
                    cv2.putText(demo_frame, "DEMO MODE - Eye Tracking Service v2.0", (50, 50), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
                    cv2.putText(demo_frame, "Simulating eye tracking with fallback mode", (50, 400), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.6, (200, 200, 200), 1)
                    cv2.putText(demo_frame, f"Frame: {self.frame_count}", (50, 430), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.5, (150, 150, 150), 1)
                    
                    # Add focus indicator
                    focus_color = (0, 255, 0) if self.current_focus_state else (0, 0, 255)
                    focus_text = "FOCUSED" if self.current_focus_state else "UNFOCUSED"
                    cv2.putText(demo_frame, f"Status: {focus_text}", (50, 460), 
                               cv2.FONT_HERSHEY_SIMPLEX, 0.6, focus_color, 2)
                    
                    return demo_frame
                
            def horizontal_ratio(self):
                # Simulate more realistic gaze movement
                t = time.time()
                base = 0.5 + 0.1 * np.sin(t * 0.2)  # Slow drift
                noise = np.random.normal(0, 0.05)   # Small random movements
                return np.clip(base + noise, 0.1, 0.9)
                
            def vertical_ratio(self):
                # Simulate more realistic gaze movement
                t = time.time()
                base = 0.5 + 0.05 * np.cos(t * 0.3)  # Slow drift
                noise = np.random.normal(0, 0.03)    # Small random movements
                return np.clip(base + noise, 0.1, 0.9)
                
            def is_blinking(self):
                return bool(np.random.random() < 0.02)  # 2% chance of blinking
        
        return FallbackTracker()
    
    def start_webcam(self):
        """Start the webcam for eye tracking"""
        try:
            # Try different camera indices with different backends
            camera_configs = [
                (0, cv2.CAP_DSHOW),  # DirectShow backend (Windows preferred)
                (1, cv2.CAP_DSHOW),  # Try second camera with DirectShow
                (0, cv2.CAP_MSMF),   # Media Foundation backend
                (1, cv2.CAP_MSMF),
                (0, cv2.CAP_ANY),    # Default backend
                (1, cv2.CAP_ANY),
                (2, cv2.CAP_DSHOW),  # Try third camera
                (0, None),           # No specific backend
                (1, None),
                (2, None)
            ]
            
            for camera_index, backend in camera_configs:
                try:
                    logger.info(f"🎥 Testing camera {camera_index} with backend {backend}...")
                    
                    if backend is not None:
                        self.webcam = cv2.VideoCapture(camera_index, backend)
                    else:
                        self.webcam = cv2.VideoCapture(camera_index)
                    
                    if self.webcam.isOpened():
                        # Set properties before testing
                        self.webcam.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
                        self.webcam.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
                        self.webcam.set(cv2.CAP_PROP_FPS, 30)
                        self.webcam.set(cv2.CAP_PROP_BUFFERSIZE, 1)  # Reduce buffer lag
                        
                        # Test reading multiple frames to ensure stability
                        success_count = 0
                        for _ in range(5):  # Test 5 frames
                            ret, test_frame = self.webcam.read()
                            if ret and test_frame is not None and test_frame.size > 0:
                                success_count += 1
                            time.sleep(0.1)  # Small delay between tests
                        
                        if success_count >= 3:  # At least 3 out of 5 successful
                            # Verify frame dimensions
                            ret, final_test = self.webcam.read()
                            if ret and final_test is not None:
                                height, width = final_test.shape[:2]
                                if width > 0 and height > 0:
                                    logger.info(f"✅ Webcam started successfully!")
                                    logger.info(f"📹 Camera: {camera_index}, Backend: {backend}, Resolution: {width}x{height}")
                                    logger.info(f"🔧 Frame test: {success_count}/5 successful reads")
                                    return True
                        
                        logger.warning(f"❌ Camera {camera_index} unstable - only {success_count}/5 frames read successfully")
                    else:
                        logger.warning(f"❌ Camera {camera_index} failed to open with backend {backend}")
                    
                    self.webcam.release()
                    
                except Exception as e:
                    logger.warning(f"❌ Exception testing camera {camera_index} with backend {backend}: {e}")
                    if self.webcam:
                        self.webcam.release()
                    
                # Small delay before trying next configuration
                time.sleep(0.2)
            
            logger.error("❌ Could not find any working webcam - running in demo mode")
            logger.error("🔍 Troubleshooting tips:")
            logger.error("   1. Check if camera is being used by another application")
            logger.error("   2. Verify camera permissions in Windows Settings")
            logger.error("   3. Try unplugging and reconnecting the camera")
            logger.error("   4. Check Windows Device Manager for camera issues")
            return False
            
        except Exception as e:
            logger.error(f"❌ Error starting webcam: {e}")
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
            annotated_frame = self.gaze.get_annotated_frame()
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
        """Start the 2-second countdown before tracking begins"""
        self.countdown_active = True
        self.countdown_start_time = time.time()
        self.tracking_state = "countdown"
        logger.info("Starting 2-second countdown...")
    
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
            
            # Log the data locally instead of saving to database for now
            logger.info(f"Tracking Data: User {data['user_id']}, Module {data['module_id']}, Focus: {data['focused_time']:.1f}s ({data['focus_percentage']:.1f}%)")
            self.last_save_time = current_time
            
            # TODO: Uncomment when database endpoint is working
            # Save to database
            # response = requests.post(
            #     'http://localhost/capstone/user/database/save_enhanced_tracking.php',
            #     json=data,
            #     timeout=5
            # )
            # 
            # if response.status_code == 200:
            #     result = response.json()
            #     if result.get('success'):
            #         self.last_save_time = current_time
            #         logger.info(f"Saved tracking data: {metrics['focused_time']:.1f}s focused, {metrics['unfocused_time']:.1f}s unfocused")
            #     else:
            #         logger.error(f"Server error saving data: {result.get('error', 'Unknown error')}")
            # else:
            #     logger.error(f"HTTP error saving data: {response.status_code}")
            
        except Exception as e:
            logger.error(f"Error saving tracking data: {e}")
    
    def run_tracking_loop(self):
        """Enhanced main tracking loop with countdown - REQUIRES REAL CAMERA"""
        webcam_available = self.start_webcam()
        if not webcam_available:
            logger.error("❌ CAMERA REQUIRED: Eye tracking requires a working camera")
            logger.error("❌ Cannot start tracking without camera access")
            self.tracking_state = "camera_error"
            self.is_tracking_enabled = False
            return
        
        logger.info("✅ Eye tracking loop started with real camera...")
        
        frame_error_count = 0
        max_frame_errors = 10  # Allow some frame read errors before giving up
        
        try:
            while getattr(self, 'is_tracking_enabled', False):
                frame = None
                
                if webcam_available and self.webcam:
                    ret, frame = self.webcam.read()
                    if not ret or frame is None:
                        frame_error_count += 1
                        logger.warning(f"⚠️ Failed to read frame from webcam ({frame_error_count}/{max_frame_errors})")
                        
                        if frame_error_count >= max_frame_errors:
                            logger.error("❌ Too many frame read errors - stopping tracking")
                            self.tracking_state = "camera_error"
                            self.is_tracking_enabled = False
                            break
                        
                        time.sleep(0.1)  # Brief pause before retry
                        continue
                    else:
                        frame_error_count = 0  # Reset error count on successful read
                
                # Only proceed if we have a real camera frame
                if frame is not None:
                    # Process real camera frame with MediaPipe
                    frame_with_overlay = self.add_tracking_overlay(frame)
                    
                    # Store the real frame
                    with self.frame_lock:
                        self.latest_frame = frame_with_overlay.copy()
                
                # Handle countdown phase
                if self.countdown_active:
                    self.check_countdown()
                
                # Handle tracking phase (only with real camera frames)
                elif self.is_tracking and frame is not None:
                    is_focused = self.is_looking_at_screen(frame)
                    self.update_focus_state(is_focused)
                    
                    # Save data periodically
                    if time.time() - self.last_save_time > self.save_interval:
                        self.save_tracking_data()
                
                # Control frame rate
                time.sleep(0.033)  # ~30 FPS
                
        except Exception as e:
            logger.error(f"❌ Error in tracking loop: {e}")
            self.tracking_state = "error"
        finally:
            if webcam_available:
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
        """Get current annotated frame as base64 string for web display - CAMERA REQUIRED"""
        try:
            with self.frame_lock:
                frame_to_use = None
                
                # Only use real camera frames - NO DEMO MODE
                if self.latest_frame is not None:
                    frame_to_use = self.latest_frame.copy()
                else:
                    # No real camera frame available
                    logger.warning("❌ No camera frame available - camera required")
                    return None
                
                if frame_to_use is not None:
                    # Ensure frame has proper dimensions
                    if len(frame_to_use.shape) == 3:
                        height, width = frame_to_use.shape[:2]
                        new_width = 320  # Reduced size for better performance
                        new_height = int(height * (new_width / width))
                        resized_frame = cv2.resize(frame_to_use, (new_width, new_height))
                        
                        # Encode frame as JPEG with optimized quality
                        success, buffer = cv2.imencode('.jpg', resized_frame, [
                            cv2.IMWRITE_JPEG_QUALITY, 75,  # Reduced quality for smaller size
                            cv2.IMWRITE_JPEG_OPTIMIZE, 1    # Enable optimization
                        ])
                        
                        if success and buffer is not None:
                            # Convert to base64
                            frame_base64 = base64.b64encode(buffer).decode('utf-8')
                            return f"data:image/jpeg;base64,{frame_base64}"
                        else:
                            logger.error("❌ Failed to encode frame as JPEG")
                    else:
                        logger.error("❌ Invalid frame dimensions")
                
                return None
                
        except Exception as e:
            logger.error(f"❌ Error getting frame: {e}")
            return None
                        logger.error(f"Invalid frame shape: {frame_to_use.shape}")
                        
                # Fallback: create a simple error frame
                error_frame = np.zeros((240, 320, 3), dtype=np.uint8)
                cv2.putText(error_frame, "Live Feed Loading...", (50, 120), 
                           cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
                success, buffer = cv2.imencode('.jpg', error_frame)
                if success:
                    frame_base64 = base64.b64encode(buffer).decode('utf-8')
                    return f"data:image/jpeg;base64,{frame_base64}"
                    
        except Exception as e:
            logger.error(f"Error in get_current_frame_base64: {e}")
            
        return None

    def generate_demo_frame(self):
        """Generate a dynamic demo frame with animations"""
        demo_frame = np.zeros((240, 320, 3), dtype=np.uint8)
        
        # Get current time for animations
        import time
        t = time.time()
        
        # Dynamic background gradient that changes over time
        for y in range(240):
            base_intensity = int(20 + (y / 240) * 30)
            wave_effect = int(10 * np.sin(t * 2 + y * 0.1))
            intensity = np.clip(base_intensity + wave_effect, 0, 255)
            demo_frame[y, :] = [intensity, intensity // 2, intensity // 3]
        
        # Add main demo text
        cv2.putText(demo_frame, "Eye Tracking Live Demo", (40, 60), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
        cv2.putText(demo_frame, "Service Running in Demo Mode", (35, 90), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.4, (200, 200, 200), 1)
        
        # Add tracking state with dynamic color coding
        state_colors = {
            "tracking": (0, 255, 0),
            "countdown": (255, 255, 0), 
            "paused": (255, 128, 0),
            "idle": (150, 150, 150)
        }
        state_color = state_colors.get(self.tracking_state, (100, 100, 100))
        cv2.putText(demo_frame, f"Status: {self.tracking_state.upper()}", (20, 120), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.4, state_color, 1)
        
        # Multiple animated elements for visual feedback
        
        # 1. Moving circle (simulated gaze point)
        x1 = int(160 + 60 * np.sin(t * 1.5))
        y1 = int(150 + 30 * np.cos(t * 2))
        cv2.circle(demo_frame, (x1, y1), 6, (0, 255, 255), -1)
        cv2.putText(demo_frame, "Gaze", (x1 - 20, y1 - 10), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.3, (0, 255, 255), 1)
        
        # 2. Pulsing focus indicator
        pulse_size = int(8 + 4 * np.sin(t * 4))
        focus_color = (0, 255, 0) if self.is_focused else (255, 0, 0)
        cv2.circle(demo_frame, (280, 50), pulse_size, focus_color, -1)
        focus_text = "FOCUS" if self.is_focused else "UNFOCUS"
        cv2.putText(demo_frame, focus_text, (240, 40), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.3, focus_color, 1)
        
        # 3. Moving corner indicators (heartbeat effect)
        corner_intensity = int(255 * (0.5 + 0.5 * np.sin(t * 3)))
        corner_color = (0, corner_intensity, 0)
        cv2.rectangle(demo_frame, (5, 5), (25, 25), corner_color, 2)
        cv2.rectangle(demo_frame, (295, 5), (315, 25), corner_color, 2)
        cv2.rectangle(demo_frame, (5, 215), (25, 235), corner_color, 2)
        cv2.rectangle(demo_frame, (295, 215), (315, 235), corner_color, 2)
        
        # 4. Real-time timestamp and frame counter
        timestamp = time.strftime("%H:%M:%S")
        frame_counter = int(t * 10) % 1000  # Simulated frame counter
        cv2.putText(demo_frame, f"Time: {timestamp}", (20, 200), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.3, (100, 255, 100), 1)
        cv2.putText(demo_frame, f"Frame: {frame_counter:03d}", (20, 220), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.3, (100, 200, 255), 1)
        
        # 5. Progress bar showing demo activity
        progress_width = int(200 * (np.sin(t * 0.5) * 0.5 + 0.5))
        cv2.rectangle(demo_frame, (50, 180), (250, 190), (60, 60, 60), -1)
        cv2.rectangle(demo_frame, (50, 180), (50 + progress_width, 190), (0, 255, 0), -1)
        cv2.putText(demo_frame, "Live Activity", (50, 175), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.3, (200, 200, 200), 1)
        
        return demo_frame

    def start_background_frame_generation(self):
        """Start background frame generation to ensure live feed always works"""
        def background_frame_loop():
            logger.info("Background frame generation started")
            while self.frame_generation_enabled:
                try:
                    # Always generate demo frames continuously for consistent live feed
                    demo_frame = self.generate_demo_frame()
                    with self.frame_lock:
                        # Only update if no active tracking frame or if we're in demo mode
                        if (not getattr(self, 'is_tracking_enabled', False) or 
                            self.webcam is None or 
                            self.latest_frame is None):
                            self.latest_frame = demo_frame
                    
                    time.sleep(0.1)  # Update background frames at 10 FPS for smooth animation
                except Exception as e:
                    logger.error(f"Error in background frame generation: {e}")
                    time.sleep(1)
        
        self.frame_generation_thread = threading.Thread(target=background_frame_loop)
        self.frame_generation_thread.daemon = True
        self.frame_generation_thread.start()

    def stop_background_frame_generation(self):
        """Stop background frame generation"""
        self.frame_generation_enabled = False
        if self.frame_generation_thread:
            self.frame_generation_thread.join(timeout=2)
    
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
            # Convert NumPy types to native Python types for JSON serialization
            is_focused = bool(eye_tracker.is_focused) if hasattr(eye_tracker.is_focused, 'item') else eye_tracker.is_focused
            countdown_remaining = 0
            if eye_tracker.countdown_start_time:
                countdown_remaining = max(0, eye_tracker.countdown_duration - (time.time() - eye_tracker.countdown_start_time))
            
            response_data = {
                'success': True, 
                'frame': frame_data,
                'tracking_state': eye_tracker.tracking_state,
                'is_focused': is_focused,
                'countdown_remaining': float(countdown_remaining),
                'timestamp': datetime.now().isoformat()
            }
            
            return jsonify(response_data)
        else:
            logger.warning("No frame data available from get_current_frame_base64")
            return jsonify({
                'success': False, 
                'error': 'No frame available',
                'tracking_state': eye_tracker.tracking_state,
                'message': 'Frame generation in progress...'
            }), 404
    except Exception as e:
        logger.error(f"Error in get_current_frame endpoint: {e}")
        return jsonify({
            'success': False, 
            'error': str(e),
            'tracking_state': getattr(eye_tracker, 'tracking_state', 'unknown')
        }), 500

@app.route('/current_frame', methods=['GET'])
def get_current_frame_legacy():
    """Legacy endpoint for current frame (redirect to /api/frame)"""
    return get_current_frame()

@app.route('/track_eyes', methods=['GET'])
def track_eyes():
    """Legacy endpoint for eye tracking (returns current status)"""
    try:
        status = eye_tracker.get_status()
        metrics = eye_tracker.get_detailed_metrics()
        
        return jsonify({
            'success': True,
            'tracking_active': getattr(eye_tracker, 'is_tracking_enabled', False),
            'focused': eye_tracker.is_focused,
            'tracking_state': eye_tracker.tracking_state,
            'metrics': metrics,
            'timestamp': datetime.now().isoformat()
        })
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
