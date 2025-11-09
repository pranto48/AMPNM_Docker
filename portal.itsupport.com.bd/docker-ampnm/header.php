<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <nav class="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center gap-2 text-white font-bold">
                        <i class="fas fa-shield-halved text-cyan-400 text-2xl"></i>
                        <span>AMPNM</span>
                    </a>
                </div>
                <div class="hidden md:block">
                    <div id="main-nav" class="ml-10 flex items-baseline space-x-1">
                        <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt fa-fw mr-2"></i>Dashboard</a>
                        <a href="devices.php" class="nav-link"><i class="fas fa-server fa-fw mr-2"></i>Devices</a>
                        <a href="history.php" class="nav-link"><i class="fas fa-history fa-fw mr-2"></i>History</a>
                        <a href="map.php" class="nav-link"><i class="fas fa-project-diagram fa-fw mr-2"></i>Map</a>
                        <a href="status_logs.php" class="nav-link"><i class="fas fa-clipboard-list fa-fw mr-2"></i>Status Logs</a>
                        <a href="email_notifications.php" class="nav-link"><i class="fas fa-envelope fa-fw mr-2"></i>Email Notifications</a>
                        <?php if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin'): ?>
                            <a href="users.php" class="nav-link"><i class="fas fa-users-cog fa-fw mr-2"></i>Users</a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt fa-fw mr-2"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <div class="page-content">
    <?php if (isset($_SESSION['license_status'])): ?>
        <?php
            $license_status = $_SESSION['license_status'];
            $license_message = $_SESSION['license_message'];
            $max_devices = $_SESSION['license_max_devices'];
            $current_devices = $_SESSION['current_device_count'];
            $expires_at = $_SESSION['license_expires_at'];

            $status_class = '';
            $status_icon = '';
            $display_message = '';

            switch ($license_status) {
                case 'active':
                    $status_class = 'bg-green-500/20 text-green-400 border-green-500/30';
                    $status_icon = '<i class="fas fa-check-circle mr-1"></i>';
                    $display_message = "License Active ({$current_devices}/{$max_devices} devices)";
                    if ($expires_at) {
                        $display_message .= " - Expires: " . date('Y-m-d', strtotime($expires_at));
                    }
                    break;
                case 'free': // Assuming 'free' is also a valid active status
                    $status_class = 'bg-green-500/20 text-green-400 border-green-500/30';
                    $status_icon = '<i class="fas fa-check-circle mr-1"></i>';
                    $display_message = "Free License Active ({$current_devices}/{$max_devices} devices)";
                    if ($expires_at) {
                        $display_message .= " - Expires: " . date('Y-m-d', strtotime($expires_at));
                    }
                    break;
                case 'expired':
                    $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                    $status_icon = '<i class="fas fa-exclamation-triangle mr-1"></i>';
                    $display_message = "License Expired! ({$license_message})";
                    break;
                case 'revoked':
                    $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                    $status_icon = '<i class="fas fa-ban mr-1"></i>';
                    $display_message = "License Revoked! ({$license_message})";
                    break;
                case 'in_use':
                    $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                    $status_icon = '<i class="fas fa-server mr-1"></i>';
                    $display_message = "License in use by another server! ({$license_message})";
                    break;
                case 'unconfigured':
                    $status_class = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                    $status_icon = '<i class="fas fa-exclamation-circle mr-1"></i>';
                    $display_message = "License Unconfigured! Please set up your license key.";
                    break;
                case 'invalid':
                case 'not_found':
                case 'error':
                default:
                    $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                    $status_icon = '<i class="fas fa-times-circle mr-1"></i>';
                    $display_message = "License Invalid! ({$license_message})";
                    break;
            }
        ?>
        <div class="container mx-auto px-4 mt-4">
            <div class="p-3 rounded-lg text-sm flex items-center justify-between <?= $status_class ?>">
                <div><?= $status_icon ?> <?= htmlspecialchars($display_message) ?></div>
                <?php if ($license_status !== 'active' && $license_status !== 'free'): ?>
                    <a href="license_setup_page.php" class="text-cyan-400 hover:underline ml-4">Manage License</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>