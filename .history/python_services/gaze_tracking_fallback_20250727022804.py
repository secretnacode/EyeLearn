"""
Simplified Eye Tracking Module
Fallback implementation when dlib is not available
"""

import cv2
import numpy as np

class SimpleGazeTracking:
    """
    Simplified gaze tracking that doesn't require dlib
    Uses basic computer vision techniques
    """
    
    def __init__(self):
        self.frame = None
        self.eyes_detected = False
        self.face_cascade = None
        self.eye_cascade = None
        self.init_cascades()
        
    def init_cascades(self):
        """Initialize OpenCV cascade classifiers"""
        try:
            # Load pre-trained cascade classifiers from OpenCV
            self.face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
            self.eye_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_eye.xml')
            print("✅ OpenCV cascades loaded successfully")
        except Exception as e:
            print(f"❌ Error loading cascades: {e}")
    
    def refresh(self, frame):
        """Analyze frame for gaze detection"""
        self.frame = frame.copy()
        self.eyes_detected = False
        
        if self.face_cascade is None or self.eye_cascade is None:
            return
        
        # Convert to grayscale
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        
        # Detect faces
        faces = self.face_cascade.detectMultiScale(gray, 1.3, 5)
        
        for (x, y, w, h) in faces:
            # Draw rectangle around face
            cv2.rectangle(self.frame, (x, y), (x+w, y+h), (255, 0, 0), 2)
            
            # Region of interest for eyes
            roi_gray = gray[y:y+h, x:x+w]
            roi_color = self.frame[y:y+h, x:x+w]
            
            # Detect eyes in face region
            eyes = self.eye_cascade.detectMultiScale(roi_gray)
            
            if len(eyes) >= 2:  # At least 2 eyes detected
                self.eyes_detected = True
                
                # Draw rectangles around eyes and add labels
                for i, (ex, ey, ew, eh) in enumerate(eyes[:2]):  # Only first 2 eyes
                    # Draw eye rectangle
                    cv2.rectangle(roi_color, (ex, ey), (ex+ew, ey+eh), (0, 255, 0), 2)
                    
                    # Add eye center point
                    eye_center_x = ex + ew // 2
                    eye_center_y = ey + eh // 2
                    cv2.circle(roi_color, (eye_center_x, eye_center_y), 3, (0, 0, 255), -1)
                    
                    # Add eye label
                    label = "L" if i == 0 else "R"
                    cv2.putText(roi_color, label, (ex, ey-10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 1)
                
                # Add gaze status text
                gaze_text = "FOCUSED" if self.eyes_detected else "LOOKING AWAY"
                text_color = (0, 255, 0) if self.eyes_detected else (0, 0, 255)
                cv2.putText(self.frame, gaze_text, (x, y-30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, text_color, 2)
                
                break
        
        # Add overall status
        if not self.eyes_detected:
            cv2.putText(self.frame, "NO FACE DETECTED", (20, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)
        
        # Add timestamp
        timestamp = cv2.getTickCount() / cv2.getTickFrequency()
        cv2.putText(self.frame, f"Frame: {timestamp:.1f}s", (20, self.frame.shape[0]-20), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1)
    
    @property
    def pupils_located(self):
        """Check if pupils/eyes are located"""
        return self.eyes_detected
    
    def is_blinking(self):
        """Simple blink detection"""
        # For now, return False - could be enhanced with eye aspect ratio
        return False
    
    def is_center(self):
        """Assume user is looking at center if eyes are detected"""
        return self.eyes_detected
    
    def is_left(self):
        """Placeholder for left gaze detection"""
        return False
    
    def is_right(self):
        """Placeholder for right gaze detection"""
        return False
    
    def horizontal_ratio(self):
        """Return center ratio if eyes detected"""
        return 0.5 if self.eyes_detected else None
    
    def vertical_ratio(self):
        """Return center ratio if eyes detected"""
        return 0.5 if self.eyes_detected else None
    
    def annotated_frame(self):
        """Return frame with annotations"""
        if self.frame is not None:
            return self.frame
        return np.zeros((480, 640, 3), dtype=np.uint8)

# Try to import the real GazeTracking, fallback to simple version
try:
    from gaze_tracking import GazeTracking
    print("✅ Using advanced GazeTracking library")
except ImportError:
    print("⚠️ Using simplified gaze tracking (dlib not available)")
    GazeTracking = SimpleGazeTracking
