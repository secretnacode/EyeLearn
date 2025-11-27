# Legacy Code Cleanup for Railway Deployment

## Overview

This document identifies legacy eye-tracking code that uses `cv2.VideoCapture()` and cannot work on Railway. These files should be removed to avoid confusion during deployment.

---

## Files to Remove

### Python Services (Legacy - Use cv2.VideoCapture)

1. **`python_services/eye_tracking_service.py`**
   - **Issue:** Uses `cv2.VideoCapture(0)` to access webcam directly
   - **Why it fails on Railway:** Railway servers have no camera hardware
   - **Status:** Legacy - replaced by WebSocket version
   - **Action:** DELETE

2. **`python_services/eye_tracking_service_simple.py`**
   - **Issue:** Uses `cv2.VideoCapture(0)` to access webcam directly
   - **Why it fails on Railway:** Railway servers have no camera hardware
   - **Status:** Legacy - replaced by WebSocket version
   - **Action:** DELETE

### JavaScript Files (Legacy - Reference Old Service)

3. **`user/js/cv-eye-tracking.js`**
   - **Issue:** References `eye_tracking_service.py` (legacy service)
   - **Status:** Legacy - replaced by `webcam-websocket.js`
   - **Action:** DELETE

4. **`user/js/cv-eye-tracking-fixed.js`**
   - **Issue:** Backup/fixed version of legacy service
   - **Status:** Legacy - replaced by `webcam-websocket.js`
   - **Action:** DELETE

5. **`user/js/cv-eye-tracking-backup.js`**
   - **Issue:** Backup version of legacy service
   - **Status:** Legacy - replaced by `webcam-websocket.js`
   - **Action:** DELETE

6. **`user/js/client-eye-tracking.js`**
   - **Issue:** Uses TensorFlow.js for client-side processing (alternative approach)
   - **Status:** Not used in main application (Smodulepart.php uses WebSocket version)
   - **Action:** DELETE (or keep if planning to use client-side processing)

### Test/Example Files (Keep for Reference)

These files can be kept for local testing but should not be used in production:

- `python_services/test_system.py` - Test file (uses cv2.VideoCapture for local testing)
- `python_services/setup.py` - Setup script (uses cv2.VideoCapture for local testing)
- `python_services/GazeTracking/example.py` - Example file

---

## Files to Keep (WebSocket Version)

### Active WebSocket Implementation

1. **`python_services/eye_tracking_service_websocket.py`** ✅
   - Uses WebSocket to receive frames from browser
   - Works on Railway
   - **KEEP**

2. **`user/js/webcam-websocket.js`** ✅
   - Captures webcam in browser
   - Sends frames via WebSocket
   - **KEEP**

3. **`user/websocket-integration.html`** ✅
   - Integration example
   - **KEEP**

---

## Configuration Files to Update

### Files That Reference Legacy Service

1. **`package.json`**
   - Line 37: `"start-tracking": "cd python_services && python eye_tracking_service.py"`
   - **Action:** Update to use `eye_tracking_service_websocket.py`

2. **`start.sh`**
   - Line 83: `python eye_tracking_service.py &`
   - **Action:** Update to use `eye_tracking_service_websocket.py`

3. **`start.bat`**
   - Line 59: References `eye_tracking_service.py`
   - **Action:** Update to use `eye_tracking_service_websocket.py`

4. **`setup.py`**
   - Multiple references to `eye_tracking_service.py`
   - **Action:** Update to use `eye_tracking_service_websocket.py`

5. **`restart_tracking.bat`**
   - Line 20: `python eye_tracking_service.py`
   - **Action:** Update to use `eye_tracking_service_websocket.py`

---

## Verification

After cleanup, verify:

1. ✅ Only `eye_tracking_service_websocket.py` exists in `python_services/`
2. ✅ Only `webcam-websocket.js` is used in `Smodulepart.php`
3. ✅ All configuration files reference WebSocket service
4. ✅ No references to `cv2.VideoCapture` in production code
5. ✅ `Procfile` uses WebSocket service (already correct)

---

## Summary

**Total files to delete:** 5-6 files
**Configuration files to update:** 5 files
**Result:** Clean codebase with only WebSocket-compatible version

