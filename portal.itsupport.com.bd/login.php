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
    <div class="glass-card p-10 space-y-8 form-fade-in tilt-card user-login-card">
        <div class="tilt-inner space-y-6">
            <div class="flex items-center justify-between">
                <span class="accent-badge"><i class="fas fa-lock"></i>Customer Login</span>
                <a href="registration.php" class="text-sm text-blue-200 hover:underline">Need an account?</a>
            </div>
            <div class="space-y-2">
                <h1 class="text-3xl font-bold text-white">Welcome back, operator</h1>
                <p class="text-gray-300">Sign in to track licenses, devices, and support tickets with a dashboard tuned for uptime.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert-glass-error mb-2">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-5">
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
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to Portal
                </button>
            </form>

            <div class="login-meta grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-200">
                <div class="meta-pill"><i class="fas fa-mobile-alt"></i> Mobile-first UI</div>
                <div class="meta-pill"><i class="fas fa-bell"></i> Renewal alerts</div>
                <div class="meta-pill"><i class="fas fa-hands-helping"></i> Human support</div>
            </div>
        </div>
    </div>

    <div class="glass-card p-10 space-y-5 tilt-card user-login-aside">
        <div class="tilt-inner space-y-5">
            <h2 class="text-2xl font-semibold text-white">Stay synced on the move</h2>
            <p class="text-gray-300">Thumb-friendly spacing, adaptive cards, and focused contrast keep AMPNM usable in server rooms and on mobile devices.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-gray-200">
                <div class="glass-chip">
                    <p class="text-sm uppercase text-blue-200">3D Motion</p>
                    <p class="text-lg font-semibold">Subtle parallax, focus-ready forms.</p>
                </div>
                <div class="glass-chip">
                    <p class="text-sm uppercase text-blue-200">Support</p>
                    <p class="text-lg font-semibold">Direct access to our engineers.</p>
                </div>
            </div>
            <ul class="text-gray-200 space-y-2 list-disc list-inside">
                <li>Faster navigation for license renewals.</li>
                <li>Save time with prefilled account data.</li>
                <li>Stay synced with your AMPNM Docker nodes.</li>
            </ul>
            <div class="inline-flex items-center gap-3 text-blue-200 text-sm">
                <span class="status-dot"></span> Optimized for low-light NOC environments
            </div>
        </div>
    </div>
</div>

<?php portal_footer(); ?>
