#!/usr/bin/env python3
"""
EyeLearn Setup Script
Automated installation and configuration for the EyeLearn platform
"""

import os
import sys
import subprocess
import platform
import shutil
from pathlib import Path

def print_header():
    """Print the setup header"""
    print("=" * 60)
    print("🎓 EyeLearn - E-Learning Platform with Eye Tracking")
    print("   Automated Setup Script")
    print("=" * 60)
    print()

def check_python_version():
    """Check if Python version is compatible"""
    print("🐍 Checking Python version...")
    version = sys.version_info
    if version.major < 3 or (version.major == 3 and version.minor < 8):
        print(f"❌ Python {version.major}.{version.minor} detected")
        print("   Python 3.8 or higher is required")
        return False
    
    print(f"✅ Python {version.major}.{version.minor}.{version.micro} - Compatible")
    return True

def check_system():
    """Check system requirements"""
    print("\n🖥️  Checking system requirements...")
    system = platform.system()
    print(f"   Operating System: {system}")
    print(f"   Architecture: {platform.machine()}")
    
    # Check if camera is available (basic check)
    try:
        import cv2
        cap = cv2.VideoCapture(0)
        if cap.isOpened():
            print("✅ Camera detected")
            cap.release()
        else:
            print("⚠️  No camera detected - eye tracking may not work")
    except ImportError:
        print("⚠️  OpenCV not installed - will install during setup")
    except Exception as e:
        print(f"⚠️  Camera check failed: {e}")

def create_virtual_environment():
    """Create Python virtual environment"""
    print("\n📦 Setting up Python virtual environment...")
    
    venv_path = Path("venv")
    if venv_path.exists():
        print("   Virtual environment already exists")
        return True
    
    try:
        subprocess.run([sys.executable, "-m", "venv", "venv"], check=True)
        print("✅ Virtual environment created")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to create virtual environment: {e}")
        return False

def get_pip_command():
    """Get the correct pip command for the platform"""
    if platform.system() == "Windows":
        return str(Path("venv") / "Scripts" / "pip.exe")
    else:
        return str(Path("venv") / "bin" / "pip")

def install_dependencies():
    """Install Python dependencies"""
    print("\n📚 Installing Python dependencies...")
    
    pip_cmd = get_pip_command()
    
    # Upgrade pip first
    try:
        subprocess.run([pip_cmd, "install", "--upgrade", "pip"], check=True)
        print("✅ Pip upgraded successfully")
    except subprocess.CalledProcessError as e:
        print(f"⚠️  Pip upgrade failed: {e}")
    
    # Install requirements
    requirements_file = Path("python_services") / "requirements.txt"
    if not requirements_file.exists():
        print(f"❌ Requirements file not found: {requirements_file}")
        return False
    
    try:
        print("   Installing packages... (this may take several minutes)")
        subprocess.run([pip_cmd, "install", "-r", str(requirements_file)], check=True)
        print("✅ All dependencies installed successfully")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to install dependencies: {e}")
        print("\n🔧 Troubleshooting tips:")
        print("   1. Try installing packages individually")
        print("   2. Check your internet connection")
        print("   3. On Windows, ensure Visual C++ Redistributable is installed")
        print("   4. On macOS, ensure Xcode command line tools are installed")
        return False

def test_imports():
    """Test if critical packages can be imported"""
    print("\n🧪 Testing package imports...")
    
    pip_cmd = get_pip_command()
    python_cmd = pip_cmd.replace("pip.exe", "python.exe").replace("pip", "python")
    
    test_script = """
import sys
try:
    import cv2
    print("✅ OpenCV imported successfully")
except ImportError as e:
    print(f"❌ OpenCV import failed: {e}")
    sys.exit(1)

try:
    import numpy as np
    print("✅ NumPy imported successfully")
except ImportError as e:
    print(f"❌ NumPy import failed: {e}")
    sys.exit(1)

try:
    import mediapipe as mp
    print("✅ MediaPipe imported successfully")
except ImportError as e:
    print(f"❌ MediaPipe import failed: {e}")
    sys.exit(1)

try:
    import flask
    print("✅ Flask imported successfully")
except ImportError as e:
    print(f"❌ Flask import failed: {e}")
    sys.exit(1)

print("🎉 All critical packages imported successfully!")
"""
    
    try:
        result = subprocess.run([python_cmd, "-c", test_script], 
                              capture_output=True, text=True, check=True)
        print(result.stdout)
        return True
    except subprocess.CalledProcessError as e:
        print("❌ Package import test failed:")
        print(e.stdout)
        print(e.stderr)
        return False

def check_xampp():
    """Check if XAMPP is installed and running"""
    print("\n🌐 Checking XAMPP installation...")
    
    # Common XAMPP installation paths
    xampp_paths = [
        "C:\\xampp",
        "/Applications/XAMPP",
        "/opt/lampp",
        "/usr/local/xampp"
    ]
    
    xampp_found = False
    for path in xampp_paths:
        if Path(path).exists():
            print(f"✅ XAMPP found at: {path}")
            xampp_found = True
            break
    
    if not xampp_found:
        print("⚠️  XAMPP not found in common locations")
        print("   Please install XAMPP from: https://www.apachefriends.org/")
        print("   Make sure Apache and MySQL services are running")
    
    return xampp_found

def create_database_setup_script():
    """Create a database setup script"""
    print("\n🗄️  Creating database setup helper...")
    
    db_script = """<?php
// EyeLearn Database Setup Script
echo "<h1>EyeLearn Database Setup</h1>";

$servername = "localhost";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$servername", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS elearn_db");
    echo "<p>✅ Database 'elearn_db' created successfully</p>";
    
    // Use the database
    $pdo->exec("USE elearn_db");
    
    // Create basic tables (simplified version)
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('student', 'admin') DEFAULT 'student',
            gender VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS user_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            module_id INT,
            completion_percentage DECIMAL(5,2) DEFAULT 0.00,
            last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (module_id) REFERENCES modules(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS eye_tracking_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            module_id INT,
            section_id INT,
            total_time_seconds INT DEFAULT 0,
            session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (module_id) REFERENCES modules(id)
        )"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    echo "<p>✅ Database tables created successfully</p>";
    
    // Create default admin user
    $admin_check = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@admin.eyelearn'")->fetchColumn();
    if ($admin_check == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Admin', 'User', 'admin@admin.eyelearn', $admin_password, 'admin']);
        echo "<p>✅ Default admin user created</p>";
        echo "<p><strong>Admin Login:</strong></p>";
        echo "<p>Email: admin@admin.eyelearn</p>";
        echo "<p>Password: admin123</p>";
    } else {
        echo "<p>ℹ️ Admin user already exists</p>";
    }
    
    echo "<h2>🎉 Database setup completed successfully!</h2>";
    echo "<p>You can now access the application at: <a href='index.php'>EyeLearn Platform</a></p>";
    
} catch(PDOException $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure MySQL is running in XAMPP</p>";
}
?>"""
    
    with open("database_setup.php", "w") as f:
        f.write(db_script)
    
    print("✅ Database setup script created: database_setup.php")

def create_start_script():
    """Create startup scripts for different platforms"""
    print("\n🚀 Creating startup scripts...")
    
    if platform.system() == "Windows":
        # Windows batch script
        bat_script = """@echo off
echo Starting EyeLearn Platform...
echo.

echo 1. Starting Eye Tracking Service...
cd python_services
start "Eye Tracking Service" cmd /k "venv\\Scripts\\activate && python eye_tracking_service.py"
cd ..

echo 2. Eye Tracking Service started in new window
echo 3. Web application should be accessible at: http://localhost/capstone
echo.
echo To stop the eye tracking service, close its window or press Ctrl+C
echo.
pause
"""
        with open("start_eyelearn.bat", "w") as f:
            f.write(bat_script)
        print("✅ Windows startup script created: start_eyelearn.bat")
    
    else:
        # Unix shell script
        sh_script = """#!/bin/bash
echo "Starting EyeLearn Platform..."
echo

echo "1. Starting Eye Tracking Service..."
cd python_services
source venv/bin/activate
python eye_tracking_service.py &
EYE_TRACKING_PID=$!
cd ..

echo "2. Eye Tracking Service started (PID: $EYE_TRACKING_PID)"
echo "3. Web application should be accessible at: http://localhost/capstone"
echo
echo "To stop the eye tracking service, run: kill $EYE_TRACKING_PID"
echo "Or press Ctrl+C"
echo

# Wait for interrupt
trap "kill $EYE_TRACKING_PID; exit" INT
wait
"""
        with open("start_eyelearn.sh", "w") as f:
            f.write(sh_script)
        
        # Make executable
        os.chmod("start_eyelearn.sh", 0o755)
        print("✅ Unix startup script created: start_eyelearn.sh")

def print_final_instructions():
    """Print final setup instructions"""
    print("\n" + "=" * 60)
    print("🎉 EyeLearn Setup Complete!")
    print("=" * 60)
    print()
    print("📋 Next Steps:")
    print("1. Ensure XAMPP is running (Apache + MySQL)")
    print("2. Copy this project to your XAMPP htdocs folder")
    print("3. Run database setup: http://localhost/capstone/database_setup.php")
    print("4. Start the eye tracking service:")
    
    if platform.system() == "Windows":
        print("   - Double-click: start_eyelearn.bat")
        print("   - Or manually: cd python_services && venv\\Scripts\\activate && python eye_tracking_service.py")
    else:
        print("   - Run: ./start_eyelearn.sh")
        print("   - Or manually: cd python_services && source venv/bin/activate && python eye_tracking_service.py")
    
    print("5. Access the platform: http://localhost/capstone")
    print()
    print("👤 Default Admin Login:")
    print("   Email: admin@admin.eyelearn")
    print("   Password: admin123")
    print()
    print("📚 Documentation: README.md")
    print("🆘 Troubleshooting: Check the README.md file")
    print()

def main():
    """Main setup function"""
    print_header()
    
    # Check requirements
    if not check_python_version():
        sys.exit(1)
    
    check_system()
    check_xampp()
    
    # Setup Python environment
    if not create_virtual_environment():
        sys.exit(1)
    
    if not install_dependencies():
        print("\n⚠️  Some dependencies failed to install.")
        print("You may need to install them manually or check the troubleshooting section.")
    
    if not test_imports():
        print("\n⚠️  Some packages failed to import.")
        print("The system may still work, but with limited functionality.")
    
    # Create helper scripts
    create_database_setup_script()
    create_start_script()
    
    # Final instructions
    print_final_instructions()

if __name__ == "__main__":
    main()
