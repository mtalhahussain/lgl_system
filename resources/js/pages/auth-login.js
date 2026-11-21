export function init(config) {
    document.getElementById('passwordToggle').addEventListener('click', function () {
        const input = document.getElementById('password');
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        this.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        this.setAttribute('aria-pressed', String(visible));
        this.querySelector('i').className = visible ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
    document.querySelector('form').addEventListener('submit', function () {
        const button = this.querySelector('[type=submit]');
        button.disabled = true;
        button.textContent = 'Signing in…';
    });
}
