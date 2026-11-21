// Supports compiled entries loaded before or after DOMContentLoaded.
export function onReady(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, { once: true });
    else callback();
}

export function bindAction(action, callback) {
    document.querySelectorAll(`[data-action="${action}"]`).forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            callback(button, event);
        });
    });
}
