# WebSocket Eye-Tracking Quick Start Guide

## 🚀 What This Does

Makes eye-tracking work on Railway by capturing webcam frames in the browser instead of the server.

---

## 📁 Files Created

1. **`user/js/webcam-websocket.js`** - Browser camera capture & WebSocket client
2. **`python_services/eye_tracking_service_websocket.py`** - WebSocket server
3. **`python_services/requirements.txt`** - Updated dependencies
4. **`Procfile`** - Railway deployment config
5. **`user/websocket-integration.html`** - Integration code snippets

---

## 🔧 Quick Integration

### In `user/Smodulepart.php`, add before `</head>`:

```html
<!-- Socket.IO -->
<script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
<!-- WebSocket Eye Tracking -->
<script src="js/webcam-websocket.js"></script>
```

### Initialize tracking:

```javascript
const webcamSocket = new WebcamWebSocket({
    onTrackingUpdate: (data) => console.log('Tracking:', data),
    onError: (error) => console.error('Error:', error)
});

await webcamSocket.initialize(userId, moduleId, sectionId);
```

### Cleanup on page unload:

```javascript
window.addEventListener('beforeunload', () => webcamSocket.disconnect());
```

---

## 🧪 Test Locally

```bash
# Install dependencies
cd python_services
pip install -r requirements.txt

# Start server
python eye_tracking_service_websocket.py

# Open browser
# http://localhost/capstone/user/Smodulepart.php?module_id=22&section_id=82
```

---

## 🚀 Deploy to Railway

```bash
# Commit changes
git add .
git commit -m "feat: WebSocket eye-tracking for Railway"
git push origin main

# Railway auto-deploys!
# Check: https://your-app.up.railway.app/api/health
```

---

## ✅ Verify

1. Camera permission prompt appears ✓
2. Browser console shows "✅ WebSocket connected" ✓
3. Tracking data appears in UI ✓
4. Database `eye_tracking_sessions` has new records ✓
5. Admin dashboard graphs populate ✓

---

## 🆘 Troubleshooting

| Issue | Solution |
|-------|----------|
| Camera permission denied | User must allow in browser settings |
| WebSocket fails | Check `/api/health` endpoint |
| No data saved | Verify `save_enhanced_tracking.php` works |
| Face not detected | Good lighting, face camera |

---

## 📚 Full Documentation

See [websocket_walkthrough.md](file:///C:/Users/JM/.gemini/antigravity/brain/16f44372-c2be-4c20-a5fa-0d48e602b6b9/websocket_walkthrough.md) for complete guide.
