<?php
require_once 'includes/auth_check.php';
include 'header.php';

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'basic';

// Only admin can create devices
if ($user_role !== 'admin') {
    header('Location: devices.php'); // Redirect if not admin
    exit;
}

$message = '';
$pdo = getDbConnection();

// Fetch available maps for the dropdown
$stmt_maps = $pdo->prepare("SELECT id, name FROM maps WHERE user_id = ? ORDER BY name ASC");
$stmt_maps->execute([$current_user_id]);
$availableMaps = $stmt_maps->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $check_port = trim($_POST['check_port'] ?? '');
    $type = trim($_POST['type'] ?? 'server');
    $map_id = trim($_POST['map_id'] ?? '');
    $ping_interval = trim($_POST['ping_interval'] ?? '');
    $icon_size = trim($_POST['icon_size'] ?? '');
    $name_text_size = trim($_POST['name_text_size'] ?? '');
    $icon_url = trim($_POST['icon_url'] ?? '');
    $warning_latency_threshold = trim($_POST['warning_latency_threshold'] ?? '');
    $warning_packetloss_threshold = trim($_POST['warning_packetloss_threshold'] ?? '');
    $critical_latency_threshold = trim($_POST['critical_latency_threshold'] ?? '');
    $critical_packetloss_threshold = trim($_POST['critical_packetloss_threshold'] ?? '');
    $show_live_ping = isset($_POST['show_live_ping']) ? 1 : 0;

    if (empty($name)) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Device name is required.</div>';
    } else {
        try {
            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;

            if ($max_devices > 0 && $current_devices >= $max_devices) {
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">License limit reached. You cannot add more than ' . $max_devices . ' devices.</div>';
            } else {
                $sql = "INSERT INTO devices (user_id, name, ip, check_port, type, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $current_user_id,
                    $name,
                    empty($ip) ? null : $ip,
                    empty($check_port) ? null : $check_port,
                    $type,
                    empty($description) ? null : $description,
                    empty($map_id) ? null : $map_id,
                    100, // Default X position
                    100, // Default Y position
                    empty($ping_interval) ? null : $ping_interval,
                    empty($icon_size) ? 50 : $icon_size,
                    empty($name_text_size) ? 14 : $name_text_size,
                    empty($icon_url) ? null : $icon_url,
                    empty($warning_latency_threshold) ? null : $warning_latency_threshold,
                    empty($warning_packetloss_threshold) ? null : $warning_packetloss_threshold,
                    empty($critical_latency_threshold) ? null : $critical_latency_threshold,
                    empty($critical_packetloss_threshold) ? null : $critical_packetloss_threshold,
                    $show_live_ping
                ]);
                $newDeviceId = $pdo->lastInsertId();
                $_SESSION['success_message'] = 'Device "' . htmlspecialchars($name) . '" added successfully!';
                header('Location: devices.php');
                exit;
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Error adding device: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">Add New Device</h1>

        <?= $message ?>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 max-w-2xl mx-auto">
            <form method="POST" action="add_device.php" class="space-y-4">
                <div>
                    <label for="deviceName" class="block text-sm font-medium text-slate-400 mb-1">Name</label>
                    <input type="text" id="deviceName" name="name" placeholder="Device Name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="deviceIp" class="block text-sm font-medium text-slate-400 mb-1">IP Address (Optional)</label>
                    <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['ip'] ?? '') ?>">
                </div>
                <div>
                    <label for="deviceDescription" class="block text-sm font-medium text-slate-400 mb-1">Description (Optional)</label>
                    <textarea id="deviceDescription" name="description" rows="2" placeholder="Optional notes about the device" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="checkPort" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                    <input type="number" id="checkPort" name="check_port" placeholder="e.g., 80 for HTTP" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['check_port'] ?? '') ?>">
                    <p class="text-xs text-slate-500 mt-1">If set, status is based on this port. If empty, it will use ICMP (ping).</p>
                </div>
                <div>
                    <label for="deviceType" class="block text-sm font-medium text-slate-400 mb-1">Type (Default Icon)</label>
                    <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <?php
                        $deviceTypes = ['box', 'camera', 'cloud', 'database', 'firewall', 'ipphone', 'laptop', 'mobile', 'nas', 'rack', 'printer', 'punchdevice', 'radio-tower', 'router', 'server', 'switch', 'tablet', 'wifi-router', 'other'];
                        foreach ($deviceTypes as $typeOption) {
                            $selected = (($_POST['type'] ?? 'server') === $typeOption) ? 'selected' : '';
                            echo "<option value=\"{$typeOption}\" {$selected}>" . htmlspecialchars(ucfirst($typeOption)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="deviceMap" class="block text-sm font-medium text-slate-400 mb-1">Map Assignment (Optional)</label>
                    <select id="deviceMap" name="map_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="">Unassigned</option>
                        <?php
                        foreach ($availableMaps as $map) {
                            $selected = (($_POST['map_id'] ?? '') == $map['id']) ? 'selected' : '';
                            echo "<option value=\"{$map['id']}\" {$selected}>" . htmlspecialchars($map['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Custom Icon (Optional)</legend>
                    <div>
                        <label for="icon_url" class="block text-sm font-medium text-slate-400 mb-1">Icon URL</label>
                        <input type="text" id="icon_url" name="icon_url" placeholder="Leave blank to use default icon" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['icon_url'] ?? '') ?>">
                    </div>
                </fieldset>
                <div>
                    <label for="pingInterval" class="block text-sm font-medium text-slate-400 mb-1">Ping Interval (seconds, Optional)</label>
                    <input type="number" id="pingInterval" name="ping_interval" placeholder="e.g., 60" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['ping_interval'] ?? '') ?>">
                </div>
                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Status Thresholds (Optional)</legend>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="warning_latency_threshold" class="block text-xs text-slate-400 mb-1">Warn Latency (ms)</label>
                            <input type="number" id="warning_latency_threshold" name="warning_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['warning_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="warning_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Warn Packet Loss (%)</label>
                            <input type="number" id="warning_packetloss_threshold" name="warning_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['warning_packetloss_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_latency_threshold" class="block text-xs text-slate-400 mb-1">Critical Latency (ms)</label>
                            <input type="number" id="critical_latency_threshold" name="critical_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['critical_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Critical Packet Loss (%)</label>
                            <input type="number" id="critical_packetloss_threshold" name="critical_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['critical_packetloss_threshold'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>
                <div>
                    <label for="iconSize" class="block text-sm font-medium text-slate-400 mb-1">Icon Size (Optional)</label>
                    <input type="number" id="iconSize" name="icon_size" placeholder="e.g., 50" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['icon_size'] ?? '') ?>">
                </div>
                <div>
                    <label for="nameTextSize" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size (Optional)</label>
                    <input type="number" id="nameTextSize" name="name_text_size" placeholder="e.g., 14" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['name_text_size'] ?? '') ?>">
                </div>
                <div>
                    <label for="showLivePing" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="showLivePing" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500" <?= isset($_POST['show_live_ping']) ? 'checked' : '' ?>>
                        <span class="ml-2">Show live ping status on map</span>
                    </label>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <a href="devices.php" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Add Device</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>