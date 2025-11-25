# Client-Side Eye Tracking Integration Guide

## ✅ Current Status

- Client-side eye tracking implemented
- Test page created and working
- Simplified Python API deployed
- Ready for integration into main app

## 🧪 Test It Now!

Visit the test page to verify camera eye tracking works:

**Local:**
```
http://localhost/capstone/user/test-eye-tracking.php
```

**Deployed:**
```
https://eyelearn-capstone.up.railway.app/user/test-eye-tracking.php
```

## 📝 Integration Instructions

To integrate client-side eye tracking into `Smodulepart.php`:

### Super Simple Method (Recommended):

Find the closing `</body>` tag in `Smodulepart.php` (last few lines) and add ONE line before it:

```php
<?php include 'includes/eye-tracking-init.php'; ?>
</body>
```

**That's it!** The include file already has all the TensorFlow.js libraries and initialization code.

### Manual Method (If you want full control):

#### Step 1: Add TensorFlow.js Libraries

Find the closing `</head>` tag in Smodulepart.php and add these scripts before it:

```html
<!-- TensorFlow.js for client-side eye tracking -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/facemesh"></script>

<!-- Client-side eye tracking -->
<script src="js/client-eye-tracking.js"></script>
```

#### Step 2: Initialize Tracker

Find the closing `</body>` tag and add this before it:

```html
<script>
// Initialize client-side eye tracking
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const tracker = new ClientSideEyeTracking(
            <?php echo $selected_module_id; ?>,
            <?php echo json_encode($selected_section_id ?? null); ?>,
            <?php echo $user_id; ?>
        );
        
        window.clientEyeTracker = tracker;
        await tracker.init();
        
        console.log('✅ Eye tracking initialized');
    } catch (error) {
        console.error('❌ Eye tracking error:', error);
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.clientEyeTracker) {
        window.clientEyeTracker.stop();
    }
});
</script>
```

### Step 3: Remove Old Eye Tracking (Optional)

You can remove the old `cv-eye-tracking.js` script tag:
```html
<!-- Remove this line: -->
<script src="js/cv-eye-tracking.js?canvas_debug_<?php echo time(); ?>"></script>
```

## 🎯 Features

Once integrated, users will see:
- ✅ Live camera feed in floating widget
- ✅ Real-time face mesh overlay
- ✅ Focus detection (green = focused, red = unfocused)
- ✅ Live stats (session time, focused time, unfocused time)
- ✅ Automatic data saving every 30 seconds

## 🔒 Privacy

- Camera feed processes 100% in the browser
- No video sent to server
- Only tracking statistics saved to database
- Users must grant camera permission

## 🐛 Troubleshooting

**Camera not working:**
1. Check HTTPS (required for camera access)
2. Grant camera permission when prompted
3. Check browser compatibility (Chrome 87+, Firefox 85+, Safari 14+)

**Model not loading:**
1. Check internet connection (TensorFlow.js libs load from CDN)
2. Check browser console for errors

**Data not saving:**
1. Verify `/api/save_tracking` endpoint works
2. Check browser console for API errors

## 📊 Database

The Python API currently uses in-memory storage. To persist data, update `python_services/eye_tracking_api.py` to save to your database.

## ✨ Next Steps

1. Test on deployed site
2. Integrate into Smodulepart.php
3. Connect API to database
4. Monitor user feedback

---

**Questions?** Check `walkthrough.md` for more details!
