<?php
require_once __DIR__ . '/includes/auth.php';
ampnm_require_auth();
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Devices', 'devices');
?>
<div class="grid two">
    <section class="card">
        <h2>Add / Edit device</h2>
        <form class="grid two">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="label" for="name">Name</label>
                <input class="input" id="name" name="name" placeholder="Core Switch">
            </div>
            <div class="form-group">
                <label class="label" for="ip">IP / Host</label>
                <input class="input" id="ip" name="ip" placeholder="192.168.0.10">
            </div>
            <div class="form-group">
                <label class="label" for="group">Group</label>
                <input class="input" id="group" name="group" placeholder="Datacenter">
            </div>
            <div class="form-group">
                <label class="label" for="method">Monitoring method</label>
                <select id="method" class="input">
                    <option value="ping">ICMP ping</option>
                    <option value="port">Port check</option>
                </select>
            </div>
            <div class="form-group">
                <label class="label" for="port">Port (for port checks)</label>
                <input class="input" id="port" name="port" placeholder="80">
            </div>
            <div class="form-group">
                <label class="label" for="interval">Interval (seconds)</label>
                <input class="input" id="interval" name="interval" placeholder="60">
            </div>
            <button type="button" class="btn">Save device</button>
            <button type="button" class="btn secondary" id="copyBtn">Copy selected</button>
        </form>
        <p style="margin-top:10px; color: var(--muted);">Hook this form to <code>/api/device</code> endpoints for create/update like the Docker build.</p>
    </section>
    <section class="card">
        <h2>Device list</h2>
        <table class="table" id="deviceTable">
            <thead>
                <tr><th></th><th>Name</th><th>IP</th><th>Method</th><th>Status</th></tr>
            </thead>
            <tbody>
                <tr data-name="Firewall" data-ip="10.0.0.1" data-method="port" data-port="443">
                    <td><input type="radio" name="selectedDevice"></td>
                    <td>Firewall</td><td>10.0.0.1</td><td>Port 443</td><td><span class="badge">Online</span></td>
                </tr>
                <tr data-name="Core Switch" data-ip="10.0.0.2" data-method="ping">
                    <td><input type="radio" name="selectedDevice"></td>
                    <td>Core Switch</td><td>10.0.0.2</td><td>Ping</td><td><span class="badge">Degraded</span></td>
                </tr>
                <tr data-name="Server" data-ip="10.0.0.5" data-method="ping">
                    <td><input type="radio" name="selectedDevice"></td>
                    <td>Server</td><td>10.0.0.5</td><td>Ping</td><td><span class="badge">Online</span></td>
                </tr>
            </tbody>
        </table>
        <p style="color: var(--muted);">Select a device then press "Copy selected" to create a quick duplicate (e.g., <code>Firewall_copy</code>).</p>
    </section>
</div>
<script>
const copyBtn = document.getElementById('copyBtn');
const table = document.getElementById('deviceTable');
copyBtn.addEventListener('click', () => {
    const selected = table.querySelector('input[name="selectedDevice"]:checked');
    if (!selected) return alert('Select a device first');
    const row = selected.closest('tr');
    const name = row.dataset.name + '_copy';
    const ip = row.dataset.ip;
    const method = row.dataset.method;
    const port = row.dataset.port;
    const tbody = table.querySelector('tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `<td></td><td>${name}</td><td>${ip}</td><td>${method === 'port' ? 'Port ' + (port || '') : 'Ping'}</td><td><span class="badge">Draft</span></td>`;
    tbody.appendChild(tr);
});
</script>
<?php renderPageEnd(); ?>
