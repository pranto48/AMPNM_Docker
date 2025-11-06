<?php
require_once 'includes/auth_check.php';
include 'header.php';

$device_id = $_GET['id'] ?? null;
$device_data = null;

if ($device_id) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
    $stmt->execute([$device_id, $_SESSION['user_id']]);
    $device_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device_data) {
        // Device not found or not owned by user
        header('Location: devices.php');
        exit;
    }
} else {
    // No ID provided, redirect to add device page
    header('Location: add_device.php');
    exit;
}

?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Edit Device: <?= htmlspecialchars($device_data['name']) ?></h1>
            <a href="devices.php" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-500">
                <i class="fas fa-arrow-left mr-2"></i>Back to Devices
            </a>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 w-full max-w-md mx-auto">
            <form id="deviceForm" class="space-y-4">
                <input type="hidden" id="deviceId" name="id" value="<?= htmlspecialchars($device_data['id']) ?>">
                <div>
                    <label for="deviceName" class="block text-sm font-medium text-slate-400 mb-1">Name</label>
                    <input type="text" id="deviceName" name="name" placeholder="Device Name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($device_data['name']) ?>" required>
                </div>
                <div id="deviceIpWrapper">
                    <label for="deviceIp" class="block text-sm font-medium text-slate-400 mb-1">IP Address</label>
                    <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($device_data['ip'] ?? '') ?>">
                </div>
                <div>
                    <label for="deviceDescription" class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                    <textarea id="deviceDescription" name="description" rows="2" placeholder="Optional notes about the device" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"><?= htmlspecialchars($device_data['description'] ?? '') ?></textarea>
                </div>
                <div id="devicePortWrapper">
                    <label for="checkPort" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                    <input type="number" id="checkPort" name="check_port" placeholder="e.g., 80 for HTTP" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($device_data['check_port'] ?? '') ?>">
                    <p class="text-xs text-slate-500 mt-1">If set, status is based on this port. If empty, it will use ICMP (ping).</p>
                </div>
                <div>
                    <label for="deviceType" class="block text-sm font-medium text-slate-400 mb-1">Type (Default Icon)</label>
                    <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="box" <?= ($device_data['type'] ?? '') == 'box' ? 'selected' : '' ?>>Box (Group)</option>
                        <option value="camera" <?= ($device_data['type'] ?? '') == 'camera' ? 'selected' : '' ?>>CC Camera</option>
                        <option value="cloud" <?= ($device_data['type'] ?? '') == 'cloud' ? 'selected' : '' ?>>Cloud</option>
                        <option value="database" <?= ($device_data['type'] ?? '') == 'database' ? 'selected' : '' ?>>Database</option>
                        <option value="firewall" <?= ($device_data['type'] ?? '') == 'firewall' ? 'selected' : '' ?>>Firewall</option>
                        <option value="ipphone" <?= ($device_data['type'] ?? '') == 'ipphone' ? 'selected' : '' ?>>IP Phone</option>
                        <option value="laptop" <?= ($device_data['type'] ?? '') == 'laptop' ? 'selected' : '' ?>>Laptop/PC</option>
                        <option value="mobile" <?= ($device_data['type'] ?? '') == 'mobile' ? 'selected' : '' ?>>Mobile Phone</option>
                        <option value="nas" <?= ($device_data['type'] ?? '') == 'nas' ? 'selected' : '' ?>>NAS</option>
                        <option value="rack" <?= ($device_data['type'] ?? '') == 'rack' ? 'selected' : '' ?>>Networking Rack</option>
                        <option value="printer" <?= ($device_data['type'] ?? '') == 'printer' ? 'selected' : '' ?>>Printer</option>
                        <option value="punchdevice" <?= ($device_data['type'] ?? '') == 'punchdevice' ? 'selected' : '' ?>>Punch Device</option>
                        <option value="radio-tower" <?= ($device_data['type'] ?? '') == 'radio-tower' ? 'selected' : '' ?>>Radio Tower</option>
                        <option value="router" <?= ($device_data['type'] ?? '') == 'router' ? 'selected' : '' ?>>Router</option>
                        <option value="server" <?= ($device_data['type'] ?? '') == 'server' ? 'selected' : '' ?>>Server</option>
                        <option value="switch" <?= ($device_data['type'] ?? '') == 'switch' ? 'selected' : '' ?>>Switch</option>
                        <option value="tablet" <?= ($device_data['type'] ?? '') == 'tablet' ? 'selected' : '' ?>>Tablet</option>
                        <option value="wifi-router" <?= ($device_data['type'] ?? '') == 'wifi-router' ? 'selected' : '' ?>>WiFi Router</option>
                        <option value="other" <?= ($device_data['type'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label for="deviceMap" class="block text-sm font-medium text-slate-400 mb-1">Map Assignment</label>
                    <select id="deviceMap" name="map_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <!-- Populated by JS -->
                    </select>
                </div>
                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Custom Icon</legend>
                    <div class="space-y-3">
                        <div>
                            <label for="icon_url" class="block text-sm font-medium text-slate-400 mb-1">Icon URL</label>
                            <input type="text" id="icon_url" name="icon_url" placeholder="Leave blank to use default icon" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($device_data['icon_url'] ?? '') ?>">
                        </div>
                        <div class="text-center text-slate-500 text-sm">OR</div>
                        <div>
                            <label for="icon_upload" class="block text-sm font-medium text-slate-400 mb-1">Upload Icon</label>
                            <input type="file" id="icon_upload" name="icon_upload" accept="image/*" class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-cyan-600/20 file:text-cyan-300 hover:file:bg-cyan-600/40">
                            <div id="icon_upload_loader" class="hidden mt-2"><div class="loader inline-block w-4 h-4"></div><span class="ml-2 text-sm">Uploading...</span></div>
                            <p class="text-xs text-slate-500 mt-1">Upload requires the item to be saved first.</p>
                        </div>
                        <div id="icon_preview_wrapper" class="hidden mt-2 text-center <?= $device_data['icon_url'] ? '' : 'hidden' ?>">
                            <img id="icon_preview" src="<?= htmlspecialchars($device_data['icon_url'] ?? '') ?>" alt="Icon Preview" class="max-w-full h-16 mx-auto bg-slate-700 p-1 rounded">
                        </div>
                    </div>
                </fieldset>
                <div id="pingIntervalWrapper">
                    <label for="pingInterval" class="block text-sm font-medium text-slate-400 mb-1">Ping Interval (seconds)</label>
                    <input type="number" id="pingInterval" name="ping_interval" placeholder="e.g., 60 (optional)" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($device_data['ping_interval'] ?? '') ?>">
                </div>
                <fieldset id="thresholdsWrapper" class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Status Thresholds (optional)</legend>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="warning_latency_threshold" class="block text-xs text-slate-400 mb-1">Warn Latency (ms)</label>
                            <input type="number" id="warning_latency_threshold" name="warning_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($device_data['warning_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="warning_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Warn Packet Loss (%)</label>
                            <input type="number" id="warning_packetloss_threshold" name="warning_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($device_data['warning_packetloss_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_latency_threshold" class="block text-xs text-slate-400 mb-1">Critical Latency (ms)</label>
                            <input type="number" id="critical_latency_threshold" name="critical_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($device_data['critical_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Critical Packet Loss (%)</label>
                            <input type="number" id="critical_packetloss_threshold" name="critical_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($device_data['critical_packetloss_threshold'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>
                <div>
                    <label id="iconSizeLabel" for="iconSize" class="block text-sm font-medium text-slate-400 mb-1">Icon Size</label>
                    <input type="number" id="iconSize" name="icon_size" placeholder="e.g., 50" value="<?= htmlspecialchars($device_data['icon_size'] ?? '50') ?>" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label id="nameTextSizeLabel" for="nameTextSize" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size</label>
                    <input type="number" id="nameTextSize" name="name_text_size" placeholder="e.g., 14" value="<?= htmlspecialchars($device_data['name_text_size'] ?? '14') ?>" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="showLivePing" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="showLivePing" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500" <?= ($device_data['show_live_ping'] ?? false) ? 'checked' : '' ?>>
                        <span class="ml-2">Show live ping status on map</span>
                    </label>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="submit" id="saveBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const API_URL = 'api.php';
        const deviceForm = document.getElementById('deviceForm');
        const deviceTypeSelect = document.getElementById('deviceType');
        const deviceMapSelect = document.getElementById('deviceMap');
        const iconUrlInput = document.getElementById('icon_url');
        const iconUploadInput = document.getElementById('icon_upload');
        const iconPreviewWrapper = document.getElementById('icon_preview_wrapper');
        const iconPreview = document.getElementById('icon_preview');

        const api = {
            get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
            post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
        };

        const populateMapSelector = async (selectElement, selectedMapId = null) => {
            try {
                const maps = await api.get('get_maps');
                selectElement.innerHTML = `
                    <option value="">Unassigned</option>
                    ${maps.map(map => `<option value="${map.id}" ${map.id == selectedMapId ? 'selected' : ''}>${map.name}</option>`).join('')}
                `;
            } catch (e) {
                console.error("Could not fetch maps for selector", e);
                window.notyf.error('Failed to load maps for assignment.');
            }
        };

        const toggleDeviceModalFields = (type) => {
            const isAnnotation = type === 'box';
            const isPingable = !isAnnotation;
            document.getElementById('deviceIpWrapper').style.display = isPingable ? 'block' : 'none';
            document.getElementById('devicePortWrapper').style.display = isPingable ? 'block' : 'none';
            document.getElementById('pingIntervalWrapper').style.display = isPingable ? 'block' : 'none';
            document.getElementById('thresholdsWrapper').style.display = isPingable ? 'block' : 'none';
            document.getElementById('deviceIp').required = isPingable;
            document.getElementById('iconSizeLabel').textContent = isAnnotation ? 'Width' : 'Icon Size';
            document.getElementById('nameTextSizeLabel').textContent = isAnnotation ? 'Height' : 'Name Text Size';
        };

        deviceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(deviceForm);
            const data = Object.fromEntries(formData.entries());
            data.show_live_ping = document.getElementById('showLivePing').checked;

            // Convert empty strings to null for numeric/optional fields
            const numericFields = ['ping_interval', 'icon_size', 'name_text_size', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold', 'check_port'];
            for (const key in data) {
                if (numericFields.includes(key) && data[key] === '') data[key] = null;
            }
            if (data.ip === '') data.ip = null;
            if (data.map_id === '') data.map_id = null;

            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

            try {
                await api.post('update_device', { id: data.id, updates: data });
                window.notyf.success('Device updated successfully.');
                // No redirect, stay on page to allow further edits or icon upload
            } catch (error) {
                console.error("Failed to update device:", error);
                window.notyf.error(error.message || "An error occurred while updating the device.");
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Changes';
            }
        });

        iconUploadInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            const deviceId = document.getElementById('deviceId').value;
            if (!file) return;
            if (!deviceId) {
                window.notyf.error('Please save the device details first before uploading an icon.');
                e.target.value = '';
                return;
            }
        
            const loader = document.getElementById('icon_upload_loader');
            loader.classList.remove('hidden');
        
            const formData = new FormData();
            formData.append('id', deviceId);
            formData.append('iconFile', file);
        
            try {
                const res = await fetch(`${API_URL}?action=upload_device_icon`, {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    iconUrlInput.value = result.url;
                    iconPreview.src = result.url;
                    iconPreviewWrapper.classList.remove('hidden');
                    window.notyf.success('Icon uploaded. Click "Save Changes" to apply permanently.');
                } else {
                    throw new Error(result.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Icon upload failed:', error);
                window.notyf.error(error.message);
            } finally {
                loader.classList.add('hidden');
                e.target.value = '';
            }
        });

        deviceTypeSelect.addEventListener('change', (e) => toggleDeviceModalFields(e.target.value));

        // Initial setup
        populateMapSelector(deviceMapSelect, <?= json_encode($device_data['map_id'] ?? null) ?>);
        toggleDeviceModalFields(deviceTypeSelect.value);
    });
</script>

<?php include 'footer.php'; ?>