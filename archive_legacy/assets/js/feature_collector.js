// feature_collector.js
// Fixed deprecated parameters: using a single config object instead of separate arguments
function init(config) {
    if (config) {
        console.log("Feature collector initialized successfully with config object.");
    }
}

if (typeof window.feature_collector === 'undefined') {
    window.feature_collector = { init: init };
}
