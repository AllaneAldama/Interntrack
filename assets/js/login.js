const passwordToggle = document.querySelector('.password-toggle');

if (passwordToggle) {
    const passwordInput = document.getElementById(passwordToggle.getAttribute('aria-controls'));
    const toggleLabel = passwordToggle.querySelector('span');

    passwordToggle.addEventListener('click', () => {
        const revealPassword = passwordInput.type === 'password';
        passwordInput.type = revealPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(revealPassword));
        passwordToggle.setAttribute('aria-label', revealPassword ? 'Hide password' : 'Show password');
        toggleLabel.textContent = revealPassword ? 'Hide' : 'Show';
    });
}
