export function init(config) {
    // Auto-refresh upcoming sessions every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
}
