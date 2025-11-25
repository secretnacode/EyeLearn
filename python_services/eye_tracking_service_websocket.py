"""
WebSocket-Enabled Eye Tracking Service for Railway Deployment
Receives video frames from browser via Socket.IO instead of cv2.VideoCapture
"""

import cv2
import json
import os
import time
import base64
import numpy as np
import logging
from datetime import datetime
from flask import Flask, jsonify, request
from flask_cors import CORS
from flask_socketio import SocketIO, emit
import requests
import mediapipe as mp

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Flask app
app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

# Socket.IO configuration
socketio = SocketIO(
    app,
    cors_allowed_origins="*",
    async_mode='eventlet',
    ping_timeout=60,
    ping_interval=25
)

# Global tracking sessions (user_id -> session_data)
active_sessions = {}

class WebSocketEyeTrackingSession:
    """Eye tracking session that processes frames from WebSocket"""
    
    def __init__(self, user_id, module_id, section_id=None):
        self.user_id = user_id
        self.module_id = module_id
        self.section_id = section_id
        
        # MediaPipe Face Mesh
        self.mp_face_mesh = mp.solutions.face_mesh
        self.face_mesh = self.mp_face_mesh.FaceMesh(
            max_num_faces=1,
            refine_landmarks=True,
            min_detection_confidence=0.5,
            min_tracking_confidence=0.5
        )
        
        # Tracking state
        self.is_focused = False
        self.focus_history = []
        self.focus_history_size = 10
        
        # Session timing
        self.session_start = time.time()
        self.accumulated_focused_time = 0
        self.accumulated_unfocused_time = 0
        self.current_focus_session_start = None
        self.current_unfocus_session_start = None
        
        # Metrics
        self.last_save_time = time.time()
        self.save_interval = 30  # Save every 30 seconds
        
        # Session data
        self.session_data = {
            'focus_sessions': [],
            'unfocus_sessions': [],
            'session_start': time.time()
        }
        
        logger.info(f"✅ Created tracking session for user {user_id}, module {module_id}, section {section_id}")
    
    def process_frame(self, frame_data):
        """Process a single frame from WebSocket"""
        try:
            # Decode base64 frame
            frame = self.decode_frame(frame_data)
            
            if frame is None:
                return None
            
            # Convert BGR to RGB for MediaPipe
            rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            
            # Process with MediaPipe
            results = self.face_mesh.process(rgb_frame)
            
            # Determine if user is focused
            is_focused_now = self.is_looking_at_screen(results)
            
            # Update focus state
            self.update_focus_state(is_focused_now)
            
            # Get current metrics
            metrics = self.get_metrics()
            
            # Save data periodically
            current_time = time.time()
            if current_time - self.last_save_time >= self.save_interval:
                self.save_tracking_data(metrics)
                self.last_save_time = current_time
            
            return {
                'is_focused': self.is_focused,
                'metrics': metrics,
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            logger.error(f"❌ Error processing frame: {e}")
            return None
    
    def decode_frame(self, frame_data):
        """Decode base64 frame data to OpenCV image"""
        try:
            # Remove data URL prefix if present
            if ',' in frame_data:
                frame_data = frame_data.split(',')[1]
            
            # Decode base64
            frame_bytes = base64.b64decode(frame_data)
            
            # Convert to numpy array
            nparr = np.frombuffer(frame_bytes, np.uint8)
            
            # Decode image
            frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            
            return frame
            
        except Exception as e:
            logger.error(f"❌ Error decoding frame: {e}")
            return None
    
    def is_looking_at_screen(self, results):
        """Determine if user is looking at screen based on face landmarks"""
        if not results or not results.multi_face_landmarks:
            return False
        
        try:
            landmarks = results.multi_face_landmarks[0]
            
            # Get eye landmarks (simplified detection)
            left_eye = landmarks.landmark[33]   # Left eye center
            right_eye = landmarks.landmark[263]  # Right eye center
            nose_tip = landmarks.landmark[1]     # Nose tip
            
            # Calculate face orientation (basic check)
            # If eyes are visible and nose is centered, user is likely focused
            eye_distance = abs(left_eye.x - right_eye.x)
            
            # Simple heuristic: if eyes are visible with reasonable distance
            is_focused = eye_distance > 0.1 and eye_distance < 0.4
            
            return is_focused
            
        except Exception as e:
            logger.error(f"❌ Error in face detection: {e}")
            return False
    
    def update_focus_state(self, is_focused_now):
        """Update focus tracking with smoothing"""
        # Add to history
        self.focus_history.append(is_focused_now)
        if len(self.focus_history) > self.focus_history_size:
            self.focus_history.pop(0)
        
        # Smooth focus detection
        focus_ratio = sum(self.focus_history) / len(self.focus_history)
        smoothed_focus = focus_ratio > 0.6
        
        current_time = time.time()
        
        # Handle focus state changes
        if smoothed_focus != self.is_focused:
            if self.is_focused:
                # Was focused, now unfocused
                if self.current_focus_session_start:
                    duration = current_time - self.current_focus_session_start
                    self.accumulated_focused_time += duration
                    self.session_data['focus_sessions'].append({
                        'start': self.current_focus_session_start,
                        'end': current_time,
                        'duration': duration
                    })
                    self.current_focus_session_start = None
                
                # Start unfocus session
                self.current_unfocus_session_start = current_time
                logger.info("User unfocused")
                
            else:
                # Was unfocused, now focused
                if self.current_unfocus_session_start:
                    duration = current_time - self.current_unfocus_session_start
                    self.accumulated_unfocused_time += duration
                    self.session_data['unfocus_sessions'].append({
                        'start': self.current_unfocus_session_start,
                        'end': current_time,
                        'duration': duration
                    })
                    self.current_unfocus_session_start = None
                
                # Start focus session
                self.current_focus_session_start = current_time
                logger.info("User focused")
            
            self.is_focused = smoothed_focus
    
    def get_metrics(self):
        """Get current tracking metrics"""
        current_time = time.time()
        
        # Calculate current session times
        current_focused = self.accumulated_focused_time
        current_unfocused = self.accumulated_unfocused_time
        
        # Add ongoing session
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
            'current_state': 'focused' if self.is_focused else 'unfocused'
        }
    
    def save_tracking_data(self, metrics):
        """Save tracking data to database"""
        if not all([self.user_id, self.module_id]):
            return
        
        try:
            data = {
                'user_id': self.user_id,
                'module_id': self.module_id,
                'section_id': self.section_id,
                'focused_time': metrics['focused_time'],
                'unfocused_time': metrics['unfocused_time'],
                'total_time': metrics['total_time'],
                'focus_percentage': metrics['focus_percentage'],
                'focus_sessions': metrics['focus_sessions'],
                'unfocus_sessions': metrics['unfocus_sessions'],
                'session_type': 'websocket_cv_tracking',
                'timestamp': datetime.now().isoformat()
            }
            
            # Save to database via PHP endpoint
            # Use environment variable for Railway, fallback to production URL
            php_endpoint = os.getenv(
                'PHP_ENDPOINT',
                'https://eyelearn-capstone.up.railway.app/user/database/save_enhanced_tracking.php'
            )
            
            response = requests.post(
                php_endpoint,
                json=data,
                timeout=10
            )
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    logger.info(f"✅ Saved tracking data: {metrics['focused_time']:.1f}s focused, {metrics['unfocused_time']:.1f}s unfocused")
                else:
                    logger.error(f"❌ Server error saving data: {result.get('error')}")
            else:
                logger.error(f"❌ HTTP error saving data: {response.status_code}")
                
        except Exception as e:
            logger.error(f"❌ Error saving tracking data: {e}")
    
    def cleanup(self):
        """Cleanup when session ends"""
        # Save final data
        metrics = self.get_metrics()
        self.save_tracking_data(metrics)
        
        # Close face mesh
        if self.face_mesh:
            self.face_mesh.close()
        
        logger.info(f"🧹 Cleaned up session for user {self.user_id}")


# WebSocket Event Handlers

@socketio.on('connect')
def handle_connect():
    """Handle client connection"""
    logger.info(f"🔌 Client connected: {request.sid}")
    emit('connected', {'message': 'Connected to eye tracking server'})

@socketio.on('disconnect')
def handle_disconnect():
    """Handle client disconnection"""
    logger.info(f"🔌 Client disconnected: {request.sid}")
    
    # Clean up any sessions for this client
    for user_id, session_data in list(active_sessions.items()):
        if session_data.get('sid') == request.sid:
            if 'session' in session_data:
                session_data['session'].cleanup()
            del active_sessions[user_id]
            logger.info(f"🧹 Removed session for user {user_id}")

@socketio.on('start_tracking')
def handle_start_tracking(data):
    """Start tracking session for a user"""
    try:
        user_id = data.get('user_id')
        module_id = data.get('module_id')
        section_id = data.get('section_id')
        
        if not user_id or not module_id:
            emit('error', {'message': 'Missing user_id or module_id'})
            return
        
        logger.info(f"🚀 Starting tracking for user {user_id}, module {module_id}, section {section_id}")
        
        # Create new session
        session = WebSocketEyeTrackingSession(user_id, module_id, section_id)
        active_sessions[user_id] = {
            'session': session,
            'sid': request.sid
        }
        
        emit('tracking_started', {
            'user_id': user_id,
            'module_id': module_id,
            'section_id': section_id,
            'message': 'Tracking started successfully'
        })
        
    except Exception as e:
        logger.error(f"❌ Error starting tracking: {e}")
        emit('error', {'message': f'Error starting tracking: {str(e)}'})

@socketio.on('video_frame')
def handle_video_frame(data):
    """Process incoming video frame"""
    try:
        frame_data = data.get('frame')
        
        if not frame_data:
            return
        
        # Find session for this client
        session = None
        for user_id, session_data in active_sessions.items():
            if session_data.get('sid') == request.sid:
                session = session_data.get('session')
                break
        
        if not session:
            logger.warning("⚠️ No active session for this client")
            return
        
        # Process frame
        result = session.process_frame(frame_data)
        
        if result:
            # Send tracking update back to client
            emit('tracking_update', result)
        
    except Exception as e:
        logger.error(f"❌ Error processing video frame: {e}")
        emit('error', {'message': f'Error processing frame: {str(e)}'})

@socketio.on('stop_tracking')
def handle_stop_tracking():
    """Stop tracking session"""
    try:
        # Find and cleanup session for this client
        for user_id, session_data in list(active_sessions.items()):
            if session_data.get('sid') == request.sid:
                if 'session' in session_data:
                    session_data['session'].cleanup()
                del active_sessions[user_id]
                logger.info(f"⏹️ Stopped tracking for user {user_id}")
                emit('tracking_stopped', {'message': 'Tracking stopped successfully'})
                return
        
        logger.warning("⚠️ No active session to stop")
        
    except Exception as e:
        logger.error(f"❌ Error stopping tracking: {e}")
        emit('error', {'message': f'Error stopping tracking: {str(e)}'})


# REST API Health Check

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'success': True,
        'message': 'WebSocket Eye Tracking Service is running',
        'version': '3.0.0 - WebSocket',
        'active_sessions': len(active_sessions),
        'timestamp': datetime.now().isoformat()
    })

@app.route('/api/status', methods=['GET'])
def get_status():
    """Get service status"""
    return jsonify({
        'success': True,
        'active_sessions': len(active_sessions),
        'sessions': [
            {
                'user_id': user_id,
                'module_id': data['session'].module_id,
                'section_id': data['session'].section_id
            }
            for user_id, data in active_sessions.items()
        ]
    })


if __name__ == '__main__':
    logger.info("🚀 Starting WebSocket Eye Tracking Service...")
    logger.info("📡 WebSocket endpoint: /socket.io")
    logger.info("🏥 Health check: /api/health")
    
    host = os.getenv('HOST', '0.0.0.0')
    port = int(os.getenv('PORT', 5000))
    
    # Use eventlet for production
    socketio.run(app, host=host, port=port, debug=False)
