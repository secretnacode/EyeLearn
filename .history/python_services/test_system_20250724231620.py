"""
Eye Tracking System Test Script
Verifies that all components are working correctly
"""

import sys
import cv2
import requests
import json
from datetime import datetime

def test_python_version():
    """Test Python version compatibility"""
    print("🐍 Testing Python version...")
    version = sys.version_info
    if version.major >= 3 and version.minor >= 8:
        print(f"   ✅ Python {version.major}.{version.minor}.{version.micro} - Compatible")
        return True
    else:
        print(f"   ❌ Python {version.major}.{version.minor}.{version.micro} - Requires Python 3.8+")
        return False

def test_opencv():
    """Test OpenCV installation"""
    print("📷 Testing OpenCV...")
    try:
        print(f"   ✅ OpenCV {cv2.__version__} installed")
        return True
    except Exception as e:
        print(f"   ❌ OpenCV error: {e}")
        return False

def test_webcam():
    """Test webcam access"""
    print("📹 Testing webcam access...")
    try:
        cap = cv2.VideoCapture(0)
        if cap.isOpened():
            ret, frame = cap.read()
            if ret and frame is not None:
                height, width = frame.shape[:2]
                print(f"   ✅ Webcam working - Resolution: {width}x{height}")
                cap.release()
                return True
            else:
                print("   ❌ Could not read frame from webcam")
                cap.release()
                return False
        else:
            print("   ❌ Could not open webcam")
            return False
    except Exception as e:
        print(f"   ❌ Webcam error: {e}")
        return False

def test_gaze_tracking():
    """Test GazeTracking library"""
    print("👁️ Testing GazeTracking library...")
    try:
        from gaze_tracking import GazeTracking
        gaze = GazeTracking()
        print("   ✅ Advanced GazeTracking library imported successfully")
        return True
    except ImportError as e:
        if 'dlib' in str(e):
            try:
                from gaze_tracking_fallback import GazeTracking
                gaze = GazeTracking()
                print("   ✅ Fallback gaze tracking loaded (dlib not available)")
                return True
            except Exception as e2:
                print(f"   ❌ Fallback gaze tracking failed: {e2}")
                return False
        else:
            print("   ❌ GazeTracking library not found")
            print("   💡 Run: git clone https://github.com/antoinelame/GazeTracking.git")
            return False
    except Exception as e:
        print(f"   ❌ GazeTracking error: {e}")
        return False

def test_flask():
    """Test Flask installation"""
    print("🌐 Testing Flask...")
    try:
        import flask
        try:
            # Try new method first
            from importlib.metadata import version
            flask_version = version('flask')
        except ImportError:
            # Fallback to older method
            flask_version = flask.__version__
        print(f"   ✅ Flask {flask_version} installed")
        return True
    except ImportError:
        print("   ❌ Flask not installed")
        print("   💡 Run: pip install Flask Flask-CORS")
        return False

def test_service_connection():
    """Test connection to eye tracking service"""
    print("🔗 Testing service connection...")
    try:
        # Try to connect to the service
        response = requests.get('http://127.0.0.1:5000/api/health', timeout=5)
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                print("   ✅ Eye tracking service is running")
                return True
            else:
                print("   ❌ Service responded but with error")
                return False
        else:
            print(f"   ❌ Service returned status code: {response.status_code}")
            return False
    except requests.exceptions.ConnectionError:
        print("   ❌ Cannot connect to service (not running)")
        print("   💡 Start the service: python eye_tracking_service.py")
        return False
    except requests.exceptions.Timeout:
        print("   ❌ Service connection timed out")
        return False
    except Exception as e:
        print(f"   ❌ Service connection error: {e}")
        return False

def run_quick_eye_tracking_test():
    """Run a quick eye tracking test"""
    print("🧪 Running quick eye tracking test...")
    try:
        # Try advanced first, then fallback
        try:
            from gaze_tracking import GazeTracking
            print("   📚 Using advanced GazeTracking")
        except ImportError:
            from gaze_tracking_fallback import GazeTracking
            print("   📚 Using fallback gaze tracking")
        
        gaze = GazeTracking()
        webcam = cv2.VideoCapture(0)
        
        if not webcam.isOpened():
            print("   ❌ Cannot open webcam for test")
            return False
        
        print("   📷 Taking test frames...")
        frames_tested = 0
        successful_detections = 0
        
        for i in range(10):  # Test 10 frames
            ret, frame = webcam.read()
            if ret:
                frames_tested += 1
                gaze.refresh(frame)
                
                if gaze.pupils_located:
                    successful_detections += 1
        
        webcam.release()
        
        success_rate = (successful_detections / frames_tested) * 100 if frames_tested > 0 else 0
        print(f"   📊 Detection success rate: {success_rate:.1f}% ({successful_detections}/{frames_tested})")
        
        if success_rate >= 30:  # Lower threshold for fallback
            print("   ✅ Eye tracking test passed")
            return True
        else:
            print("   ⚠️ Low detection rate - check lighting and camera position")
            return False
            
    except Exception as e:
        print(f"   ❌ Eye tracking test failed: {e}")
        return False

def main():
    """Run all tests"""
    print("🎯 Eye Tracking System Test Suite")
    print("=" * 50)
    print(f"Test started at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print()
    
    tests = [
        test_python_version,
        test_opencv,
        test_flask,
        test_gaze_tracking,
        test_webcam,
        test_service_connection,
        run_quick_eye_tracking_test
    ]
    
    passed = 0
    total = len(tests)
    
    for test in tests:
        try:
            if test():
                passed += 1
            print()  # Add spacing between tests
        except Exception as e:
            print(f"   ❌ Test failed with exception: {e}")
            print()
    
    print("=" * 50)
    print(f"📋 Test Results: {passed}/{total} passed")
    
    if passed == total:
        print("🎉 All tests passed! System is ready to use.")
        print("\nNext steps:")
        print("1. Start the eye tracking service: python eye_tracking_service.py")
        print("2. Open your browser and navigate to a module")
        print("3. Allow webcam access when prompted")
    elif passed >= total - 2:
        print("⚠️ Most tests passed. Check the failed tests above.")
    else:
        print("❌ Multiple tests failed. Please review the setup guide.")
        print("   📖 See: CV_EYE_TRACKING_SETUP.md")
    
    return passed == total

if __name__ == "__main__":
    main()
