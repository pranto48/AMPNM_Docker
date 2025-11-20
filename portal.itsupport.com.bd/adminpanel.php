<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: admin/index.php'); // Redirect to actual admin dashboard
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        if (authenticateAdmin($username, $password)) {
            header('Location: admin/index.php'); // Redirect to actual admin dashboard
            exit;
        } else {
            $error_message = 'Invalid username or password.';
        }
    }
}

admin_header("Admin Login");
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 admin-body relative">
    <div class="animated-grid"></div>
    <div class="max-w-5xl w-full grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="admin-card p-10 form-fade-in tilt-card">
            <div class="tilt-inner">
                <div class="flex items-center justify-between mb-4">
                    <span class="accent-badge"><i class="fas fa-shield-alt"></i>Secure Console</span>
                    <span class="text-sm text-gray-300">Trusted admin-only zone</span>
                </div>
                <h1 class="text-3xl font-bold text-gray-100 mb-2">Admin Login</h1>
                <p class="text-gray-300">Enter your credentials to manage licenses, payments, and user activity.</p>

                <?php if ($error_message): ?>
                    <div class="alert-admin-error mb-4">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form action="adminpanel.php" method="POST" class="mt-6 space-y-6">
                    <div>
                        <label for="username" class="block text-sm text-gray-300 mb-2">Username</label>
                        <input id="username" name="username" type="text" autocomplete="username" required
                               class="form-admin-input"
                               placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="password" class="block text-sm text-gray-300 mb-2">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="form-admin-input"
                               placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn-admin-primary w-full flex justify-center items-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card p-10 tilt-card">
            <div class="tilt-inner space-y-4">
                <h2 class="text-2xl font-semibold text-white">Operations at a glance</h2>
                <p class="text-gray-300">Modern gradients, glowing accents, and mobile-first grids make the admin panel feel cohesive with the public portal.</p>
                <ul class="text-gray-200 space-y-3 list-disc list-inside">
                    <li>Consistent neon-accent colors for quick scanning.</li>
                    <li>Blurred glass blocks to highlight key widgets.</li>
                    <li>Responsive two-column layout for tablets and phones.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php admin_footer(); ?>