function initProfile() {
    const API_URL = 'api.php';

    const els = {
        changePasswordForm: document.getElementById('changePasswordForm'),
        currentPassword: document.getElementById('current_password'),
        newPassword: document.getElementById('new_password'),
        confirmNewPassword: document.getElementById('confirm_new_password'),
        savePasswordBtn: document.getElementById('savePasswordBtn'),
    };

    const api = {
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    els.changePasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const currentPassword = els.currentPassword.value;
        const newPassword = els.newPassword.value;
        const confirmNewPassword = els.confirmNewPassword.value;

        if (newPassword !== confirmNewPassword) {
            window.notyf.error('New passwords do not match.');
            return;
        }
        if (newPassword.length < 6) {
            window.notyf.error('New password must be at least 6 characters long.');
            return;
        }

        els.savePasswordBtn.disabled = true;
        els.savePasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        try {
            const result = await api.post('update_profile', { current_password: currentPassword, new_password: newPassword });
            if (result.success) {
                window.notyf.success(result.message);
                els.changePasswordForm.reset();
            } else {
                window.notyf.error(`Error: ${result.error}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred while changing password.');
            console.error(error);
        } finally {
            els.savePasswordBtn.disabled = false;
            els.savePasswordBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Change Password';
        }
    });
}