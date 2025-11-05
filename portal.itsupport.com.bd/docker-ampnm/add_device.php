<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Add New Device</h1>
            <a href="devices.php" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-500">
                <i class="fas fa-arrow-left mr-2"></i>Back to Devices
            </a>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 w-full max-w-md mx-auto">
            <form id="addDeviceForm" class="space-y-4">
                <div>
                    <label for="deviceName" class="block text-sm font-medium text-slate-400 mb-1">Name</label>
                    <input type="text" id="deviceName" name="name" placeholder="Device Name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                </div>
                <div id="deviceIpWrapper">
                    <label for="deviceIp" class="block text-sm font-medium text-slate-400 mb-1">IP Address</label>
                    <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="deviceDescription" class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                    <textarea id="deviceDescription" name="description" rows="2" placeholder="Optional notes about the device" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"></textarea>
                </div>
                <div id="devicePortWrapper">
                    <label for="checkPort" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                    <input type="number" id="checkPort" name="check_port" placeholder="e.g., 80 for HTTP" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    <p class="text-xs text-slate-500 mt-1">If set, status is based on this port. If empty, it will use ICMP (ping).</p>
                </div>
                <div>
                    <label for="deviceType" class="block text-sm font-medium text-slate-400 mb-1">Type (Default Icon)</label>
                    <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="box">Box (Group)</option>
                        <option value="camera">CC Camera</option>
                        <option value="cloud">Cloud</option>
                        <option value="database">Database</option>
                        <option value="firewall">Firewall</option>
                        <option value="ipphone">IP Phone</option>
                        <option value="laptop">Laptop/PC</option>
                        <option value="mobile">Mobile Phone</option>
                        <option value="nas">NAS</option>
                        <option value="rack">Networking Rack</option>
                        <option value="printer">Printer</option>
                        <option value="punchdevice">Punch Device</option>
                        <option value="radio-tower">Radio Tower</option>
                        <option value="router">Router</option>
                        <option value="server">Server</option>
                        <option value="switch">Switch</option>
                        <option value="tablet">Tablet</option>
                        <option value="wifi-router">WiFi Router</option>
                        <option value="other">Other</option>
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
                            <input type="text" id="icon_url" name="icon_url" placeholder="Leave blank to use default icon" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                    </div>
                </fieldset>
                <div id="pingIntervalWrapper">
                    <label for="pingInterval" class="block text-sm font-medium text-slate-400 mb-1">Ping Interval (seconds)</label>
                    <input type="number" id="pingInterval" name="ping_interval" placeholder="e.g., 60 (optional)" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <fieldset id="thresholdsWrapper" class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Status Thresholds (optional)</legend>
                    <div class="grid grid-cols-2 gap-4">
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
                    <label id="iconSizeLabel" for="iconSize" class="block text-sm font-medium text-slate-400 mb-1">Icon Size</label>
                    <input type="number" id="iconSize" name="icon_size" placeholder="e.g., 50" value="50" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label id="nameTextSizeLabel" for="nameTextSize" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size</label>
                    <input type="number" id="nameTextSize" name="name_text_size" placeholder="e.g., 14" value="14" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="showLivePing" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="showLivePing" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                        <span class="ml-2">Show live ping status on map</span>
                    </label>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="submit" id="saveBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                        <i class="fas fa-plus mr-2"></i>Add Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const API_URL = 'api.php';
        const addDeviceForm = document.getElementById('addDeviceForm');
        const deviceTypeSelect = document.getElementById('deviceType');
        const deviceMapSelect = document.getElementById('deviceMap');

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

        addDeviceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(addDeviceForm);
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
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';

            try {
                await api.post('create_device', data);
                window.notyf.success('Device added successfully.');
                addDeviceForm.reset();
                toggleDeviceModalFields(deviceTypeSelect.value); // Reset fields visibility
                populateMapSelector(deviceMapSelect); // Re-populate map selector
                window.location.href = 'devices.php'; // Redirect to devices list
            } catch (error) {
                console.error("Failed to add device:", error);
                window.notyf.error(error.message || "An error occurred while adding the device.");
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>Add Device';
            }
        });

        deviceTypeSelect.addEventListener('change', (e) => toggleDeviceModalFields(e.target.value));

        // Initial setup
        populateMapSelector(deviceMapSelect);
        toggleDeviceModalFields(deviceTypeSelect.value);
    });
</script>

<?php include 'footer.php'; ?>