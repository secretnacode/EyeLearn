#!/bin/bash

# EyeLearn Platform Startup Script
# Compatible with macOS and Linux

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}"
echo "========================================"
echo "  EyeLearn Platform - Quick Start"
echo "========================================"
echo -e "${NC}"

# Function to check command availability
check_command() {
    if command -v "$1" >/dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Check Python installation
echo -e "${BLUE}[1/4]${NC} Checking Python installation..."
if check_command python3; then
    PYTHON_CMD="python3"
    echo -e "${GREEN}OK:${NC} Python3 found ($(python3 --version))"
elif check_command python; then
    PYTHON_CMD="python"
    echo -e "${GREEN}OK:${NC} Python found ($(python --version))"
else
    echo -e "${RED}ERROR:${NC} Python not found! Please install Python 3.8+ from python.org"
    exit 1
fi

# Check/create virtual environment
echo -e "${BLUE}[2/4]${NC} Checking virtual environment..."
if [ ! -d "python_services/venv" ]; then
    echo "Creating Python virtual environment..."
    cd python_services
    $PYTHON_CMD -m venv venv
    if [ $? -ne 0 ]; then
        echo -e "${RED}ERROR:${NC} Failed to create virtual environment"
        exit 1
    fi
    cd ..
fi
echo -e "${GREEN}OK:${NC} Virtual environment ready"

# Install/update dependencies
echo -e "${BLUE}[3/4]${NC} Installing/updating dependencies..."
cd python_services
source venv/bin/activate
pip install -r requirements.txt --quiet
if [ $? -ne 0 ]; then
    echo -e "${YELLOW}WARNING:${NC} Some dependencies may have failed to install"
    echo "The system may still work with limited functionality"
fi
cd ..
echo -e "${GREEN}OK:${NC} Dependencies processed"

# Start services
echo -e "${BLUE}[4/4]${NC} Starting EyeLearn services..."
echo
echo -e "${YELLOW}===== IMPORTANT INSTRUCTIONS =====${NC}"
echo "1. Ensure XAMPP is running (Apache + MySQL)"
echo "2. Database setup: http://localhost/capstone/database_setup.php"
echo "3. Main application: http://localhost/capstone"
echo -e "${YELLOW}================================${NC}"
echo

echo "Starting Eye Tracking Service..."
cd python_services
source venv/bin/activate

# Start the eye tracking service in background
echo "Eye Tracking Service Starting..."
python eye_tracking_service.py &
EYE_TRACKING_PID=$!
cd ..

echo
echo -e "${GREEN}===== EyeLearn Platform Started! =====${NC}"
echo
echo "Services Status:"
echo "- Eye Tracking Service: Running (PID: $EYE_TRACKING_PID)"
echo "- Web Application: http://localhost/capstone"
echo
echo "Default Admin Login:"
echo "- Email: admin@admin.eyelearn"  
echo "- Password: admin123"
echo
echo "To stop services: Press Ctrl+C or run: kill $EYE_TRACKING_PID"
echo

# Function to cleanup on exit
cleanup() {
    echo
    echo "Stopping Eye Tracking Service..."
    kill $EYE_TRACKING_PID 2>/dev/null
    echo "EyeLearn services stopped."
    exit 0
}

# Set trap to cleanup on interrupt
trap cleanup INT TERM

echo "Press Ctrl+C to stop all services..."
echo "Monitoring Eye Tracking Service (PID: $EYE_TRACKING_PID)..."

# Wait for the background process
wait $EYE_TRACKING_PID
