<?php
require_once 'includes/auth_check.php';
include 'header.php';

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'basic';

// Fetch user profile data
$profile_data = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $profile_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile_data) {
        // Should not happen if auth_check passes
        header('Location: logout.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
    // Handle error gracefully
    $profile_data = ['username' => 'Error', 'role' => 'unknown', 'created_at' => 'N/A'];
}

?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">My Profile</h1>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 max-w-2xl mx-auto">
            <h2 class="text-xl font-semibold text-white mb-4">User Information</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-slate-700 pb-2">
                    <span class="text-slate-400">Username:</span>
                    <span class="text-white font-medium"><?= htmlspecialchars($profile_data['username']) ?></span>
                </div>
                <div class="flex items-center justify-between border-b border-slate-700 pb-2">
                    <span class="text-slate-400">Role:</span>
                    <span class="text-white font-medium capitalize"><?= htmlspecialchars($profile_data['role']) ?></span>
                </div>
                <div class="flex items-center justify-between border-b border-slate-700 pb-2">
                    <span class="text-slate-400">Member Since:</span>
                    <span class="text-white font-medium"><?= date('Y-m-d H:i', strtotime($profile_data['created_at'])) ?></span>
                </div>
            </div>

            <h2 class="text-xl font-semibold text-white mt-8 mb-4">Change Password</h2>
            <form id="changePasswordForm" class="space-y-4">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-slate-400 mb-1">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-slate-400 mb-1">New Password</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="confirm_new_password" class="block text-sm font-medium text-slate-400 mb-1">Confirm New Password</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div class="flex justify-end">
                    <button type="submit" id="savePasswordBtn" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                        <i class="fas fa-save mr-2"></i>Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>