<!-- TensorFlow.js for Client-Side Eye Tracking -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/facemesh"></script>

<!-- Eye Tracking Improvements -->
<script src="js/eye-tracking-improvements.js"></script>

<!-- Client-Side Eye Tracking Module -->
<script src="js/client-eye-tracking.js"></script>

<script>
// Initialize Client-Side Eye Tracking
document.addEventListener('DOMContentLoaded', async () => {
    // Prevent reinitialization if already initialized (for AJAX navigation)
    if (window.clientEyeTracker && window.clientEyeTracker.isInitialized) {
        console.log('📹 Eye tracking already initialized, skipping reinitialization');
        return;
    }
    
    try {
        console.log('📹 Initializing client-side eye tracking...');
        
        const tracker = new ClientSideEyeTracking(
            <?php echo $selected_module_id; ?>,
            <?php echo json_encode($selected_section_id ?? null); ?>,
            <?php echo $user_id; ?>
        );
        
        // Make globally accessible
        window.clientEyeTracker = tracker;
        
        // Initialize tracker
        await tracker.init();
        
        // Mark as initialized
        tracker.isInitialized = true;
        
        console.log('✅ Client-side eye tracking initialized successfully');
        
    } catch (error) {
        console.warn('⚠️ Eye tracking initialization failed:', error.message);
        // Don't block page load if eye tracking fails
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.clientEyeTracker) {
        window.clientEyeTracker.stop();
    }
});
</script>
