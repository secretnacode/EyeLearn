@echo off
title EyeLearn Platform Startup
color 0A

echo.
echo ========================================
echo   EyeLearn Platform - Quick Start
echo ========================================
echo.

echo [1/4] Checking Python installation...
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python not found! Please install Python 3.8+ from python.org
    pause
    exit /b 1
)
echo OK: Python found

echo.
echo [2/4] Checking virtual environment...
if not exist "python_services\venv" (
    echo Creating Python virtual environment...
    cd python_services
    python -m venv venv
    if errorlevel 1 (
        echo ERROR: Failed to create virtual environment
        pause
        exit /b 1
    )
    cd ..
)
echo OK: Virtual environment ready

echo.
echo [3/4] Installing/updating dependencies...
cd python_services
call venv\Scripts\activate
pip install -r requirements.txt --quiet
if errorlevel 1 (
    echo WARNING: Some dependencies may have failed to install
    echo The system may still work with limited functionality
)
cd ..
echo OK: Dependencies processed

echo.
echo [4/4] Starting EyeLearn services...
echo.
echo ===== IMPORTANT INSTRUCTIONS =====
echo 1. Ensure XAMPP is running (Apache + MySQL)
echo 2. Database setup: http://localhost/capstone/database_setup.php
echo 3. Main application: http://localhost/capstone
echo ================================
echo.

echo Starting Eye Tracking Service...
cd python_services
start "EyeLearn - Eye Tracking Service" cmd /k "venv\Scripts\activate && echo Eye Tracking Service Starting... && python eye_tracking_service.py"
cd ..

echo.
echo ===== EyeLearn Platform Started! =====
echo.
echo Services Status:
echo - Eye Tracking Service: Running in separate window
echo - Web Application: http://localhost/capstone
echo.
echo Default Admin Login:
echo - Email: admin@admin.eyelearn  
echo - Password: admin123
echo.
echo To stop services: Close the Eye Tracking Service window
echo.
pause
