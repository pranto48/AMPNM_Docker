<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    redirectToDashboard();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } else {
        if (authenticateCustomer($email, $password)) {
            redirectToDashboard();
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}

portal_header("Login - IT Support BD Portal");
?>

<div class="min-h-screen grid grid-cols-1 lg:grid-cols-2 gap-6 py-12 px-4 sm:px-6 lg:px-8 relative">
    <div class="animated-grid"></div>
    <div class="glass-card p-10 space-y-6 form-fade-in tilt-card">
        <div class="tilt-inner">
            <div class="flex items-center justify-between mb-4">
                <span class="accent-badge"><i class="fas fa-lock"></i>Secure Access</span>
                <a href="registration.php" class="text-sm text-blue-200 hover:underline">Need an account?</a>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Welcome Back</h1>
            <p class="text-gray-300">Sign in to monitor your network licenses, devices, and support tickets.</p>

            <?php if ($error_message): ?>
                <div class="alert-glass-error mb-4">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="mt-4 space-y-5">
                <div>
                    <label for="email" class="block text-sm text-gray-300 mb-2">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="form-glass-input"
                           placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div>
                    <label for="password" class="block text-sm text-gray-300 mb-2">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="form-glass-input"
                           placeholder="••••••••">
                </div>

                <button type="submit" class="btn-glass-primary w-full flex justify-center items-center">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </form>
        </div>
    </div>

    <div class="glass-card p-10 space-y-5 tilt-card">
        <div class="tilt-inner space-y-4">
            <h2 class="text-2xl font-semibold text-white">Built for mobile monitoring</h2>
            <p class="text-gray-300">Enjoy a responsive experience on phones and tablets with thumb-friendly controls and quick actions.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-gray-200">
                <div class="glass-card p-4">
                    <p class="text-sm uppercase text-blue-200">3D Motion</p>
                    <p class="text-lg font-semibold">Subtle parallax, focus-ready forms.</p>
                </div>
                <div class="glass-card p-4">
                    <p class="text-sm uppercase text-blue-200">Support</p>
                    <p class="text-lg font-semibold">Direct access to our engineers.</p>
                </div>
            </div>
            <ul class="text-gray-200 space-y-2 list-disc list-inside">
                <li>Faster navigation for license renewals.</li>
                <li>Save time with prefilled account data.</li>
                <li>Stay synced with your AMPNM Docker nodes.</li>
            </ul>
        </div>
    </div>
</div>

<?php portal_footer(); ?>