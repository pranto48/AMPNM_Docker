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
</dyad-file>

<dyad-write path="portal.itsupport.com.bd/docker-ampnm/index.php" description="Simplifying the dashboard in index.php to remove React components.">
<?php
require_once 'includes/auth_check.php';
include 'header.php';

$pdo = getDbConnection();
$current_user_id = $_SESSION['user_id'];

// Fetch dashboard stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
        SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning,
        SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical,
        SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
    FROM devices WHERE user_id = ?
");
$stmt->execute([$current_user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Ensure counts are integers, not null
$stats['total'] = $stats['total'] ?? 0;
$stats['online'] = $stats['online'] ?? 0;
$stats['warning'] = $stats['warning'] ?? 0;
$stats['critical'] = $stats['critical'] ?? 0;
$stats['offline'] = $stats['offline'] ?? 0;

// Get recent status logs
$stmt = $pdo->prepare("
    SELECT 
        dsl.created_at, 
        dsl.status, 
        dsl.details, 
        d.name as device_name, 
        d.ip as device_ip
    FROM 
        device_status_logs dsl
    JOIN 
        devices d ON dsl.device_id = d.id
    WHERE 
        d.user_id = ?
    ORDER BY 
        dsl.created_at DESC 
    LIMIT 5
");
$stmt->execute([$current_user_id]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusColorMap = [
    'online' => 'text-green-400',
    'warning' => 'text-yellow-400',
    'critical' => 'text-red-400',
    'offline' => 'text-slate-400',
    'unknown' => 'text-slate-500'
];
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Dashboard</h1>
            <button id="refreshAllDevicesBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                <i class="fas fa-sync-alt mr-2"></i>Refresh All Devices
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Status Chart -->
            <div class="lg:col-span-1 bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 flex flex-col items-center justify-center">
                <h3 class="text-lg font-semibold text-white mb-4">Device Status Overview</h3>
                <div class="w-48 h-48 relative">
                    <canvas id="statusChart"></canvas>
                    <div id="totalDevicesText" class="absolute inset-0 flex flex-col items-center justify-center text-white">
                        <span class="text-4xl font-bold"><?= $stats['total'] ?></span>
                        <span class="text-sm text-slate-400">Total Devices</span>
                    </div>
                </div>
            </div>
            <!-- Status Counters -->
            <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                    <h3 class="text-sm font-medium text-slate-400">Online</h3>
                    <div id="onlineCount" class="text-4xl font-bold text-green-400 mt-2"><?= $stats['online'] ?></div>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                    <h3 class="text-sm font-medium text-slate-400">Warning</h3>
                    <div id="warningCount" class="text-4xl font-bold text-yellow-400 mt-2"><?= $stats['warning'] ?></div>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                    <h3 class="text-sm font-medium text-slate-400">Critical</h3>
                    <div id="criticalCount" class="text-4xl font-bold text-red-400 mt-2"><?= $stats['critical'] ?></div>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                    <h3 class="text-sm font-medium text-slate-400">Offline</h3>
                    <div id="offlineCount" class="text-4xl font-bold text-slate-500 mt-2"><?= $stats['offline'] ?></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Manual Ping Test -->
            <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                <h2 class="text-xl font-semibold text-white mb-4">Manual Ping Test</h2>
                <form id="pingForm" class="flex flex-col sm:flex-row gap-4 mb-4">
                    <input type="text" id="pingHostInput" name="ping_host" placeholder="Enter hostname or IP" value="192.168.1.1" class="flex-1 px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                    <button type="submit" id="pingButton" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500">
                        <i class="fas fa-bolt mr-2"></i>Ping
                    </button>
                </form>
                <div id="pingResultContainer" class="hidden mt-4">
                    <pre id="pingResultPre" class="bg-slate-900/50 text-white text-sm p-4 rounded-lg overflow-x-auto"></pre>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                <h2 class="text-xl font-semibold text-white mb-4">Recent Activity</h2>
                <div id="recentActivityList" class="space-y-3 max-h-60 overflow-y-auto">
                    <?php if (empty($recent_activity)): ?>
                        <div class="text-center py-4 text-slate-500">
                            <i class="fas fa-bell text-4xl mb-2"></i>
                            <p>No recent activity.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="border border-slate-700 rounded-lg p-3 flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-white"><?= htmlspecialchars($activity['device_name']) ?> <span class="text-sm text-slate-500 font-mono">(<?= htmlspecialchars($activity['device_ip'] ?: 'N/A') ?>)</span></div>
                                    <div class="text-sm <?= $statusColorMap[$activity['status']] ?? $statusColorMap['unknown'] ?>"><?= htmlspecialchars(ucfirst($activity['status'])) ?>: <?= htmlspecialchars($activity['details']) ?></div>
                                </div>
                                <div class="text-xs text-slate-500"><?= date('H:i', strtotime($activity['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>