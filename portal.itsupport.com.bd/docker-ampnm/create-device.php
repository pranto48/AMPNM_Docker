<?php
require_once 'includes/auth_check.php';
include 'header.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = trim($_POST['type'] ?? 'server');
    $check_port = !empty($_POST['check_port']) ? (int)$_POST['check_port'] : null;
    $ping_interval = !empty($_POST['ping_interval']) ? (int)$_POST['ping_interval'] : null;
    $icon_size = !empty($_POST['icon_size']) ? (int)$_POST['icon_size'] : 50;
    $name_text_size = !empty($_POST['name_text_size']) ? (int)$_POST['name_text_size'] : 14;
    $icon_url = trim($_POST['icon_url'] ?? '');
    $warning_latency_threshold = !empty($_POST['warning_latency_threshold']) ? (int)$_POST['warning_latency_threshold'] : null;
    $warning_packetloss_threshold = !empty($_POST['warning_packetloss_threshold']) ? (int)$_POST['warning_packetloss_threshold'] : null;
    $critical_latency_threshold = !empty($_POST['critical_latency_threshold']) ? (int)$_POST['critical_latency_threshold'] : null;
    $critical_packetloss_threshold = !empty($_POST['critical_packetloss_threshold']) ? (int)$_POST['critical_packetloss_threshold'] : null;
    $show_live_ping = isset($_POST['show_live_ping']) ? 1 : 0;

    if (empty($name)) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Device name is required.</div>';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO devices (user_id, name, ip, description, type, check_port, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'], $name, $ip, $description, $type, $check_port, $ping_interval, $icon_size, $name_text_size, $icon_url,
                $warning_latency_threshold, $warning_packetloss_threshold, $critical_latency_threshold, $critical_packetloss_threshold, $show_live_ping
            ]);
            $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center mb-4">Device created successfully!</div>';
            // Redirect to devices.php after successful creation
            header('Location: devices.php?msg=' . urlencode($message));
            exit;
        } catch (PDOException $e) {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Error creating device: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Create New Device</h1>
            <a href="devices.php" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-500">
                <i class="fas fa-arrow-left mr-2"></i>Back to Devices
            </a>
        </div>

        <?= $message ?>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 max-w-2xl mx-auto">
            <form method="POST" action="create-device.php" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-400 mb-1">Device Name</label>
                    <input type="text" id="name" name="name" placeholder="e.g., Main Router" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                </div>
                <div>
                    <label for="ip" class="block text-sm font-medium text-slate-400 mb-1">IP Address (Optional)</label>
                    <input type="text" id="ip" name="ip" placeholder="e.g., 192.168.1.1" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-400 mb-1">Description (Optional)</label>
                    <textarea id="description" name="description" rows="2" placeholder="Optional notes about the device" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"></textarea>
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-slate-400 mb-1">Type (Default Icon)</label>
                    <select id="type" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="server">Server</option>
                        <option value="router">Router</option>
                        <option value="switch">Switch</option>
                        <option value="printer">Printer</option>
                        <option value="nas">NAS</option>
                        <option value="camera">CC Camera</option>
                        <option value="firewall">Firewall</option>
                        <option value="ipphone">IP Phone</option>
                        <option value="punchdevice">Punch Device</option>
                        <option value="wifi-router">WiFi Router</option>
                        <option value="radio-tower">Radio Tower</option>
                        <option value="rack">Networking Rack</option>
                        <option value="laptop">Laptop/PC</option>
                        <option value="tablet">Tablet</option>
                        <option value="mobile">Mobile Phone</option>
                        <option value="cloud">Cloud</option>
                        <option value="database">Database</option>
                        <option value="box">Box (Group)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="check_port" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                    <input type="number" id="check_port" name="check_port" placeholder="e.g., 80 for HTTP (leave blank for ICMP ping)" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    <p class="text-xs text-slate-500 mt-1">If set, status is based on this port. If empty, it will use ICMP (ping).</p>
                </div>
                <div>
                    <label for="ping_interval" class="block text-sm font-medium text-slate-400 mb-1">Ping Interval (seconds, Optional)</label>
                    <input type="number" id="ping_interval" name="ping_interval" placeholder="e.g., 60 (leave blank for no auto ping)" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="icon_size" class="block text-sm font-medium text-slate-400 mb-1">Icon Size (e.g., 50)</label>
                    <input type="number" id="icon_size" name="icon_size" value="50" min="20" max="100" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="name_text_size" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size (e.g., 14)</label>
                    <input type="number" id="name_text_size" name="name_text_size" value="14" min="8" max="24" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="icon_url" class="block text-sm font-medium text-slate-400 mb-1">Custom Icon URL (Optional)</label>
                    <input type="text" id="icon_url" name="icon_url" placeholder="e.g., https://example.com/icon.png" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Status Thresholds (Optional)</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="warning_latency_threshold" class="block text-xs text-slate-400 mb-1">Warn Latency (ms)</label>
                            <input type="number" id="warning_latency_threshold" name="warning_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="warning_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Warn Packet Loss (%)</label>
                            <input type="number" id="warning_packetloss_threshold" name="warning_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="critical_latency_threshold" class="block text-xs text-slate-400 mb-1">Critical Latency (ms)</label>
                            <input type="number" id="critical_latency_threshold" name="critical_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="critical_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Critical Packet Loss (%)</label>
                            <input type="number" id="critical_packetloss_threshold" name="critical_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                    </div>
                </fieldset>
                <div>
                    <label for="show_live_ping" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="show_live_ping" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                        <span class="ml-2">Show live ping status on map (if assigned to a map)</span>
                    </label>
                </div>
                <button type="submit" class="w-full px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                    <i class="fas fa-plus mr-2"></i>Create Device
                </button>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>