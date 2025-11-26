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
(function() {
    // Use IIFE to prevent re-execution when script is loaded multiple times
    // Check if already initialized (for AJAX navigation)
    if (window.clientEyeTracker && window.clientEyeTracker.isInitialized && window.clientEyeTracker.isTracking) {
        console.log('📹 Eye tracking already initialized and running, updating section ID only');
        
        // Just update the section ID if it changed
        const newSectionId = <?php echo json_encode($selected_section_id ?? null); ?>;
        const newModuleId = <?php echo $selected_module_id; ?>;
        
        // Only update if module or section changed
        if (window.clientEyeTracker.moduleId !== newModuleId || window.clientEyeTracker.sectionId !== newSectionId) {
            if (window.clientEyeTracker.moduleId !== newModuleId) {
                console.log('📹 Module changed, need to reinitialize');
                // Module changed - need to reinitialize
                window.clientEyeTracker.stop();
                window.clientEyeTracker = null;
                // Continue with initialization below
            } else if (window.clientEyeTracker.sectionId !== newSectionId) {
                console.log('📹 Section changed, updating section ID');
                window.clientEyeTracker.updateSectionId(newSectionId);
                return; // Don't reinitialize
            }
        } else {
            return; // Nothing changed, don't reinitialize
        }
    }
    
    // Initialize only if not already initialized
    const initEyeTracking = async () => {
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
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEyeTracking);
    } else {
        // DOM already loaded, initialize immediately
        initEyeTracking();
    }
})();

// Cleanup and save data on page unload
window.addEventListener('beforeunload', () => {
    if (window.clientEyeTracker) {
        // Save final data using sendBeacon for reliability
        window.clientEyeTracker.saveFinalData();
        window.clientEyeTracker.stop();
    }
});

// Also save on visibility change (tab switch, minimize)
document.addEventListener('visibilitychange', () => {
    if (document.hidden && window.clientEyeTracker) {
        // Save data when tab becomes hidden
        window.clientEyeTracker.saveTrackingData();
    }
});

// Save on pagehide (more reliable than beforeunload)
window.addEventListener('pagehide', () => {
    if (window.clientEyeTracker) {
        window.clientEyeTracker.saveFinalData();
    }
});
</script>
