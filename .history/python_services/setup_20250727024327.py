"""
Eye Tracking Setup Script
Helps set up the computer vision eye tracking system
"""

import os
import sys
import subprocess
import requests
import zipfile
import shutil

def check_python_version():
    """Check if Python version is compatible"""
    if sys.version_info < (3, 8):
        print("❌ Python 3.8 or higher is required")
        return False
    print(f"✅ Python {sys.version_info.major}.{sys.version_info.minor} detected")
    return True

def install_dependencies():
    """Install required Python packages"""
    print("📦 Installing Python dependencies...")
    try:
        subprocess.check_call([sys.executable, "-m", "pip", "install", "-r", "requirements.txt"])
        print("✅ Dependencies installed successfully")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Error installing dependencies: {e}")
        return False

def download_gaze_tracking():
    """Download and setup GazeTracking library"""
    print("👁️ Setting up GazeTracking library...")
    
    gaze_tracking_url = "https://github.com/antoinelame/GazeTracking/archive/refs/heads/master.zip"
    
    try:
        # Download the library
        print("📥 Downloading GazeTracking...")
        response = requests.get(gaze_tracking_url, stream=True)
        response.raise_for_status()
        
        # Save and extract
        with open("gaze_tracking.zip", "wb") as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)
        
        # Extract the zip file
        with zipfile.ZipFile("gaze_tracking.zip", 'r') as zip_ref:
            zip_ref.extractall("temp_gaze")
        
        # Move the gaze_tracking folder to current directory
        source_dir = os.path.join("temp_gaze", "GazeTracking-master", "gaze_tracking")
        if os.path.exists(source_dir):
            if os.path.exists("gaze_tracking"):
                shutil.rmtree("gaze_tracking")
            shutil.move(source_dir, "gaze_tracking")
            print("✅ GazeTracking library setup complete")
        else:
            print("❌ Could not find gaze_tracking folder in downloaded archive")
            return False
        
        # Cleanup
        os.remove("gaze_tracking.zip")
        shutil.rmtree("temp_gaze")
        
        return True
        
    except Exception as e:
        print(f"❌ Error setting up GazeTracking: {e}")
        return False

def test_webcam():
    """Test if webcam is accessible"""
    print("📷 Testing webcam access...")
    try:
        import cv2
        cap = cv2.VideoCapture(0)
        if cap.isOpened():
            ret, frame = cap.read()
            if ret:
                print("✅ Webcam test successful")
                cap.release()
                return True
            else:
                print("❌ Could not read from webcam")
                cap.release()
                return False
        else:
            print("❌ Could not open webcam")
            return False
    except ImportError:
        print("❌ OpenCV not installed")
        return False
    except Exception as e:
        print(f"❌ Webcam test failed: {e}")
        return False

def create_startup_scripts():
    """Create startup scripts for the service"""
    
    # Windows batch file
    windows_script = """@echo off
echo Starting Eye Tracking Service...
cd /d "%~dp0"
python eye_tracking_service.py
pause
"""
    
    with open("start_eye_tracking.bat", "w") as f:
        f.write(windows_script)
    
    # Unix shell script
    unix_script = """#!/bin/bash
echo "Starting Eye Tracking Service..."
cd "$(dirname "$0")"
python eye_tracking_service.py
read -p "Press enter to continue..."
"""
    
    with open("start_eye_tracking.sh", "w") as f:
        f.write(unix_script)
    
    # Make shell script executable on Unix systems
    if os.name != 'nt':
        os.chmod("start_eye_tracking.sh", 0o755)
    
    print("✅ Startup scripts created")

def main():
    """Main setup function"""
    print("🎯 Eye Tracking System Setup")
    print("=" * 40)
    
    # Check Python version
    if not check_python_version():
        return False
    
    # Install dependencies
    if not install_dependencies():
        return False
    
    # Download GazeTracking library
    if not download_gaze_tracking():
        return False
    
    # Test webcam
    if not test_webcam():
        print("⚠️ Webcam test failed. The system may not work properly.")
        print("   Make sure your webcam is connected and not used by other applications.")
    
    # Create startup scripts
    create_startup_scripts()
    
    print("\n🎉 Setup completed successfully!")
    print("\nNext steps:")
    print("1. Run the eye tracking service:")
    print("   - Windows: Double-click start_eye_tracking.bat")
    print("   - Mac/Linux: ./start_eye_tracking.sh")
    print("2. Open your e-learning platform in the browser")
    print("3. Navigate to a module to start eye tracking")
    
    return True

if __name__ == "__main__":
    main()
