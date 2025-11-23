<?php
require_once __DIR__ . '/includes/auth.php';
ampnm_require_auth();
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Users', 'users');
?>
<div class="grid two">
    <section class="card">
        <h2>Create user</h2>
        <form class="grid two">
            <div class="form-group">
                <label class="label">Name</label>
                <input class="input" placeholder="Jane Doe">
            </div>
            <div class="form-group">
                <label class="label">Email</label>
                <input class="input" placeholder="noc@example.com">
            </div>
            <div class="form-group">
                <label class="label">Role</label>
                <select class="input">
                    <option>Admin</option>
                    <option>Operator</option>
                    <option>Viewer</option>
                </select>
            </div>
            <div class="form-group">
                <label class="label">Password</label>
                <input class="input" type="password" placeholder="••••••">
            </div>
            <button type="button" class="btn">Save user</button>
            <button type="button" class="btn secondary">Send reset link</button>
        </form>
        <p style="color: var(--muted);">Wire these actions to your <code>users</code> table and SMTP settings to deliver resets and announcements.</p>
    </section>
    <section class="card">
        <h2>User directory</h2>
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
            <tbody>
                <tr><td>Admin</td><td>admin@example.com</td><td>Admin</td><td><button class="btn ghost">Reset password</button></td></tr>
                <tr><td>NOC User</td><td>noc@example.com</td><td>Operator</td><td><button class="btn ghost">Promote</button></td></tr>
                <tr><td>Client</td><td>client@example.com</td><td>Viewer</td><td><button class="btn ghost">Resend invite</button></td></tr>
            </tbody>
        </table>
    </section>
</div>
<?php renderPageEnd(); ?>
