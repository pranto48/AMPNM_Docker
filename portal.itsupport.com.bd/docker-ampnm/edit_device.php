<?php
require_once 'includes/auth_check.php';
include 'header.php';

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'basic';

$device_id = $_GET['id'] ?? null;
if (!$device_id) {
    $_SESSION['error_message'] = 'No device ID provided for editing.';
    header('Location: devices.php');
    exit;
}

$message = '';
$pdo = getDbConnection();
$device = null;

// Fetch device details
try {
    $sql = "SELECT d.*, u.role as owner_role FROM devices d JOIN users u ON d.user_id = u.id WHERE d.id = ?";
    $params = [$device_id];

    if ($user_role === 'basic') {
        $admin_ids = getAdminUserIds($pdo); // Assuming this function exists in functions.php or is defined
        if (!empty($admin_ids)) {
            $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
            $sql .= " AND (d.user_id = ? OR d.user_id IN ({$admin_id_placeholders}))";
            $params[] = $current_user_id;
            $params = array_merge($params, $admin_ids);
        } else {
            $sql .= " AND d.user_id = ?";
            $params[] = $current_user_id;
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        $_SESSION['error_message'] = 'Device not found or you do not have permission to edit it.';
        header('Location: devices.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error fetching device details: ' . htmlspecialchars($e->getMessage());
    header('Location: devices.php');
    exit;
}

// Fetch available maps for the dropdown (only maps owned by the current user or admin maps)
$sql_maps = "SELECT id, name, user_id FROM maps WHERE user_id = ?";
$params_maps = [$current_user_id];

if ($user_role === 'basic') {
    $admin_ids = getAdminUserIds($pdo);
    if (!empty($admin_ids)) {
        $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
        $sql_maps .= " OR user_id IN ({$admin_id_placeholders})";
        $params_maps = array_merge($params_maps, $admin_ids);
    }
}
$sql_maps .= " ORDER BY name ASC";
$stmt_maps = $pdo->prepare($sql_maps);
$stmt_maps->execute($params_maps);
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
            $sql = "UPDATE devices SET name = ?, ip = ?, check_port = ?, type = ?, description = ?, map_id = ?, ping_interval = ?, icon_size = ?, name_text_size = ?, icon_url = ?, warning_latency_threshold = ?, warning_packetloss_threshold = ?, critical_latency_threshold = ?, critical_packetloss_threshold = ?, show_live_ping = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name,
                empty($ip) ? null : $ip,
                empty($check_port) ? null : $check_port,
                $type,
                empty($description) ? null : $description,
                empty($map_id) ? null : $map_id,
                empty($ping_interval) ? null : $ping_interval,
                empty($icon_size) ? 50 : $icon_size,
                empty($name_text_size) ? 14 : $name_text_size,
                empty($icon_url) ? null : $icon_url,
                empty($warning_latency_threshold) ? null : $warning_latency_threshold,
                empty($warning_packetloss_threshold) ? null : $warning_packetloss_threshold,
                empty($critical_latency_threshold) ? null : $critical_latency_threshold,
                empty($critical_packetloss_threshold) ? null : $critical_packetloss_threshold,
                $show_live_ping,
                $device_id
            ]);
            $_SESSION['success_message'] = 'Device "' . htmlspecialchars($name) . '" updated successfully!';
            header('Location: devices.php');
            exit;
        } catch (PDOException $e) {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Error updating device: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// If form was not submitted or submission failed, use existing device data for pre-filling
$formData = $_POST ?? $device;

// Helper function to get admin user IDs (if not already in functions.php)
if (!function_exists('getAdminUserIds')) {
    function getAdminUserIds($pdo) {
        $stmt = $pdo->prepare("SELECT id FROM `users` WHERE role = 'admin'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">Edit Device: <?= htmlspecialchars($device['name']) ?></h1>

        <?= $message ?>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 max-w-2xl mx-auto">
            <form method="POST" action="edit_device.php?id=<?= htmlspecialchars($device_id) ?>" class="space-y-4">
                <div>
                    <label for="deviceName" class="block text-sm font-medium text-slate-400 mb-1">Name</label>
                    <input type="text" id="deviceName" name="name" placeholder="Device Name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="deviceIp" class="block text-sm font-medium text-slate-400 mb-1">IP Address (Optional)</label>
                    <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($formData['ip'] ?? '') ?>">
                </div>
                <div>
                    <label for="deviceDescription" class="block text-sm font-medium text-slate-400 mb-1">Description (Optional)</label>
                    <textarea id="deviceDescription" name="description" rows="2" placeholder="Optional notes about the device" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="checkPort" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                    <input type="number" id="checkPort" name="check_port" placeholder="e.g., 80 for HTTP" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($formData['check_port'] ?? '') ?>">
                    <p class="text-xs text-slate-500 mt-1">If set, status is based on this port. If empty, it will use ICMP (ping).</p>
                </div>
                <div>
                    <label for="deviceType" class="block text-sm font-medium text-slate-400 mb-1">Type (Default Icon)</label>
                    <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <?php
                        $deviceTypes = ['box', 'camera', 'cloud', 'database', 'firewall', 'ipphone', 'laptop', 'mobile', 'nas', 'rack', 'printer', 'punchdevice', 'radio-tower', 'router', 'server', 'switch', 'tablet', 'wifi-router', 'other'];
                        foreach ($deviceTypes as $typeOption) {
                            $selected = (($formData['type'] ?? 'server') === $typeOption) ? 'selected' : '';
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
                            $selected = (($formData['map_id'] ?? '') == $map['id']) ? 'selected' : '';
                            echo "<option value=\"{$map['id']}\" {$selected}>" . htmlspecialchars($map['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Custom Icon (Optional)</legend>
                    <div>
                        <label for="icon_url" class="block text-sm font-medium text-slate-400 mb-1">Icon URL</label>
                        <input type="text" id="icon_url" name="icon_url" placeholder="Leave blank to use default icon" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($formData['icon_url'] ?? '') ?>">
                    </div>
                    <div class="text-center text-slate-500 text-sm">OR</div>
                    <div>
                        <label for="icon_upload" class="block text-sm font-medium text-slate-400 mb-1">Upload Icon</label>
                        <input type="file" id="icon_upload" name="icon_upload" accept="image/*" class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-cyan-600/20 file:text-cyan-300 hover:file:bg-cyan-600/40">
                        <div id="icon_upload_loader" class="hidden mt-2"><div class="loader inline-block w-4 h-4"></div><span class="ml-2 text-sm">Uploading...</span></div>
                    </div>
                    <?php if (!empty($formData['icon_url'])): ?>
                        <div id="icon_preview_wrapper" class="mt-2 text-center">
                            <img id="icon_preview" src="<?= htmlspecialchars($formData['icon_url']) ?>" alt="Icon Preview" class="max-w-full h-16 mx-auto bg-slate-700 p-1 rounded">
                        </div>
                    <?php else: ?>
                        <div id="icon_preview_wrapper" class="hidden mt-2 text-center">
                            <img id="icon_preview" src="" alt="Icon Preview" class="max-w-full h-16 mx-auto bg-slate-700 p-1 rounded">
                        </div>
                    <?php endif; ?>
                </fieldset>
                <div>
                    <label for="pingInterval" class="block text-sm font-medium text-slate-400 mb-1">Ping Interval (seconds, Optional)</label>
                    <input type="number" id="pingInterval" name="ping_interval" placeholder="e.g., 60" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($formData['ping_interval'] ?? '') ?>">
                </div>
                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Status Thresholds (Optional)</legend>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="warning_latency_threshold" class="block text-xs text-slate-400 mb-1">Warn Latency (ms)</label>
                            <input type="number" id="warning_latency_threshold" name="warning_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($formData['warning_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="warning_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Warn Packet Loss (%)</label>
                            <input type="number" id="warning_packetloss_threshold" name="warning_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($formData['warning_packetloss_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_latency_threshold" class="block text-xs text-slate-400 mb-1">Critical Latency (ms)</label>
                            <input type="number" id="critical_latency_threshold" name="critical_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($formData['critical_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Critical Packet Loss (%)</label>
                            <input type="number" id="critical_packetloss_threshold" name="critical_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($formData['critical_packetloss_threshold'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>
                <div>
                    <label for="iconSize" class="block text-sm font-medium text-slate-400 mb-1">Icon Size (Optional)</label>
                    <input type="number" id="iconSize" name="icon_size" placeholder="e.g., 50" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($formData['icon_size'] ?? '') ?>">
                </div>
                <div>
                    <label for="nameTextSize" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size (Optional)</label>
                    <input type="number" id="nameTextSize" name="name_text_size" placeholder="e.g., 14" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($formData['name_text_size'] ?? '') ?>">
                </div>
                <div>
                    <label for="showLivePing" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="showLivePing" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500" <?= ($formData['show_live_ping'] ?? 0) ? 'checked' : '' ?>>
                        <span class="ml-2">Show live ping status on map</span>
                    </label>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <a href="devices.php" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const iconUploadInput = document.getElementById('icon_upload');
        const iconUrlInput = document.getElementById('icon_url');
        const iconPreviewWrapper = document.getElementById('icon_preview_wrapper');
        const iconPreview = document.getElementById('icon_preview');
        const iconUploadLoader = document.getElementById('icon_upload_loader');
        const deviceId = <?= json_encode($device_id) ?>;

        // Function to update preview based on URL input
        const updatePreview = () => {
            if (iconUrlInput.value) {
                iconPreview.src = iconUrlInput.value;
                iconPreviewWrapper.classList.remove('hidden');
            } else {
                iconPreviewWrapper.classList.add('hidden');
                iconPreview.src = '';
            }
        };

        iconUrlInput.addEventListener('input', updatePreview);
        updatePreview(); // Initial call to set preview if URL is already present

        iconUploadInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            iconUploadLoader.classList.remove('hidden');

            const formData = new FormData();
            formData.append('id', deviceId);
            formData.append('iconFile', file);

            try {
                const res = await fetch('api.php?action=upload_device_icon', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    iconUrlInput.value = result.url; // Set the URL input with the uploaded URL
                    updatePreview(); // Update the preview
                    window.notyf.success('Icon uploaded. Remember to click "Save Changes" to finalize.');
                } else {
                    throw new Error(result.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Icon upload failed:', error);
                window.notyf.error(error.message);
            } finally {
                iconUploadLoader.classList.add('hidden');
                e.target.value = ''; // Clear file input
            }
        });
    });
</script>

<?php include 'footer.php'; ?>