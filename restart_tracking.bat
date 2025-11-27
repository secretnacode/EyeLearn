@echo off
echo.
echo ======================================
echo   Restarting EyeLearn Eye Tracking
echo ======================================
echo.

echo [1/2] Stopping any existing Python processes...
taskkill /f /im python.exe >nul 2>&1
timeout /t 2 >nul

echo [2/2] Starting Eye Tracking Service with database integration...
cd python_services
call venv\Scripts\activate
echo.
echo 🎯 Database Integration Status: ENABLED
echo 📊 Data will now be saved to dashboard-compatible format
echo 🔄 Service restarting...
echo.
python eye_tracking_service.py
