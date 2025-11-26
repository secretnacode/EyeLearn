# Smodulepart.php Eye Tracking Verification

## Summary
✅ **YES** - `user/Smodulepart.php` uses the `eye_tracking_sessions` table through the WebSocket → Python → PHP endpoint flow.

---

## Data Flow

### 1. Frontend Initialization (Smodulepart.php)
**Location:** `user/Smodulepart.php` lines 3469-3657

**Code:**
```javascript
function initWebSocketEyeTracking() {
    const userId = <?php echo json_encode($user_id); ?>;
    const moduleId = <?php echo json_encode($selected_module_id); ?>;
    const sectionId = <?php echo json_encode($selected_section_id ?? null); ?>;
    
    // Initialize WebSocket connection
    webcamSocket = new WebcamWebSocket({
        onTrackingUpdate: function(data) { ... },
        onConnectionChange: function(connected) { ... },
        onError: function(error) { ... }
    });
    
    webcamSocket.initialize(userId, moduleId, sectionId);
}
```

**What it does:**
- Initializes WebSocket eye tracking client
- Passes `user_id`, `module_id`, `section_id` to WebSocket
- WebSocket captures webcam frames and sends to Python server

---

### 2. WebSocket Client (webcam-websocket.js)
**Location:** `user/js/webcam-websocket.js`

**Process:**
- Captures webcam frames
- Sends frames to Python WebSocket server via Socket.IO
- Receives tracking updates from server

---

### 3. Python WebSocket Server (eye_tracking_service_websocket.py)
**Location:** `python_services/eye_tracking_service_websocket.py` lines 350-393

**Save Endpoint:**
```python
php_endpoint = os.getenv(
    'PHP_ENDPOINT',
    'https://eyelearn-capstone.up.railway.app/user/database/save_enhanced_tracking.php'
)

data = {
    'user_id': self.user_id,
    'module_id': self.module_id,
    'section_id': self.section_id,
    'focused_time': metrics['focused_time'],
    'unfocused_time': metrics['unfocused_time'],
    'total_time': metrics['total_time'],
    'focus_percentage': metrics['focus_percentage'],
    ...
}

response = requests.post(php_endpoint, json=data, timeout=10)
```

**What it does:**
- Processes webcam frames with MediaPipe
- Calculates focus metrics
- Sends data to PHP endpoint: `/user/database/save_enhanced_tracking.php`

---

### 4. PHP Save Endpoint (save_enhanced_tracking.php)
**Location:** `user/database/save_enhanced_tracking.php`

#### INSERT Query (Lines 118-141)
```php
INSERT INTO eye_tracking_sessions (
    user_id, 
    module_id, 
    section_id, 
    total_time_seconds,
    focused_time_seconds,        // ✅ Uses eye_tracking_sessions
    unfocused_time_seconds,      // ✅ Uses eye_tracking_sessions
    session_type,
    session_data
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
```

#### UPDATE Query (Lines 96-110)
```php
UPDATE eye_tracking_sessions 
SET total_time_seconds = ?, 
    focused_time_seconds = ?,    // ✅ Uses eye_tracking_sessions
    unfocused_time_seconds = ?,  // ✅ Uses eye_tracking_sessions
    session_data = ?,
    last_updated = NOW() 
WHERE id = ?
```

**What it does:**
- Receives JSON data from Python server
- Maps to `eye_tracking_sessions` table columns
- Inserts new session or updates existing session
- Uses correct column names: `focused_time_seconds`, `unfocused_time_seconds`, `total_time_seconds`

---

## Verification

### ✅ Table Used
- **Table:** `eye_tracking_sessions` ✅
- **Location:** `user/database/save_enhanced_tracking.php` lines 96, 119

### ✅ Columns Used
- `user_id` ✅
- `module_id` ✅
- `section_id` ✅
- `total_time_seconds` ✅
- `focused_time_seconds` ✅
- `unfocused_time_seconds` ✅
- `session_type` ✅
- `session_data` ✅

### ✅ Data Flow
```
Smodulepart.php (Frontend)
    ↓ (WebSocket)
webcam-websocket.js (Client)
    ↓ (Socket.IO frames)
eye_tracking_service_websocket.py (Python Server)
    ↓ (HTTP POST JSON)
save_enhanced_tracking.php (PHP Endpoint)
    ↓ (SQL INSERT/UPDATE)
eye_tracking_sessions (Database Table) ✅
```

---

## Consistency Check

| Component | Table | Column | Status |
|-----------|-------|--------|--------|
| Smodulepart.php → Save | `eye_tracking_sessions` | `focused_time_seconds` | ✅ |
| User Dashboard → Fetch | `eye_tracking_sessions` | `focused_time_seconds` | ✅ |
| Admin Dashboard → Fetch | `eye_tracking_sessions` | `focused_time_seconds` | ✅ |

**Result:** ✅ **FULLY CONSISTENT** - All components use `eye_tracking_sessions` table with `focused_time_seconds` column.

---

## Conclusion

**YES** - `user/Smodulepart.php` uses the `eye_tracking_sessions` table.

The data flow is:
1. ✅ Frontend (`Smodulepart.php`) initializes WebSocket
2. ✅ WebSocket sends frames to Python server
3. ✅ Python server processes and saves via PHP endpoint
4. ✅ PHP endpoint (`save_enhanced_tracking.php`) saves to `eye_tracking_sessions` table
5. ✅ Uses correct columns: `focused_time_seconds`, `unfocused_time_seconds`, `total_time_seconds`

**The entire system is now consistent:**
- Save endpoint uses `eye_tracking_sessions` ✅
- User dashboard reads from `eye_tracking_sessions` ✅
- Admin dashboard reads from `eye_tracking_sessions` ✅
- All use `focused_time_seconds` for focus metrics ✅

