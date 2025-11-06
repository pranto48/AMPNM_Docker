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
                <div>
                    <label for="deviceIp" class="block text-sm font-medium text-slate-400 mb-1">IP Address (Optional)</label>
                    <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="deviceType" class="block text-sm font-medium text-slate-400 mb-1">Type</label>
                    <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="server">Server</option>
                        <option value="router">Router</option>
                        <option value="switch">Switch</option>
                        <option value="printer">Printer</option>
                        <option value="laptop">Laptop/PC</option>
                        <option value="wifi-router">WiFi Router</option>
                        <option value="database">Database</option>
                        <option value="firewall">Firewall</option>
                        <option value="box">Box (Group)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="deviceMap" class="block text-sm font-medium text-slate-400 mb-1">Map Assignment (Optional)</label>
                    <select id="deviceMap" name="map_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <!-- Populated by JS -->
                    </select>
                </div>
                <button type="submit" id="saveBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                    <i class="fas fa-plus mr-2"></i>Add Device
                </button>
                <p class="text-xs text-slate-500 mt-2">For advanced settings (ping interval, thresholds, custom icon), add the device first, then edit it from the <a href="devices.php" class="text-cyan-400 hover:underline">Device Inventory</a>.</p>
            </form>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const API_URL = 'api.php';
        const addDeviceForm = document.getElementById('addDeviceForm');
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

        addDeviceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(addDeviceForm);
            const data = Object.fromEntries(formData.entries());
            
            // Convert empty strings to null for optional fields
            if (data.ip === '') data.ip = null;
            if (data.map_id === '') data.map_id = null;

            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';

            try {
                await api.post('create_device', data);
                window.notyf.success('Device added successfully.');
                addDeviceForm.reset();
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

        // Initial setup
        populateMapSelector(deviceMapSelect);
    });
</script>

<?php include 'footer.php'; ?>