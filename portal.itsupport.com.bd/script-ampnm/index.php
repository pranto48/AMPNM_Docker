<?php
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Portal Overview', 'dashboard');
?>
<div class="grid two">
    <section class="hero card">
        <p class="badge">Standalone PHP build</p>
        <h2>AMPNM without Docker</h2>
        <p>Deploy this script to XAMPP, LAMP, or shared hosting and keep the familiar AMPNM workflows—maps, monitoring graphs, device onboarding, licensing, and user management.</p>
        <div class="chip-row" style="margin-top:12px;">
            <span class="chip">Map + Topology</span>
            <span class="chip">Device health</span>
            <span class="chip">Ping / Port monitors</span>
            <span class="chip">Email alerts</span>
            <span class="chip">License enforcement</span>
        </div>
    </section>
    <section class="card">
        <h2>Quick start</h2>
        <ol style="color: var(--muted); padding-left: 18px; margin-top: 0;">
            <li>Copy this folder to <code>htdocs/script-ampnm</code> on your XAMPP host.</li>
            <li>Duplicate <code>config.sample.php</code> to <code>config.php</code> and set DB + SMTP.</li>
            <li>Wire the API endpoints in <code>/api</code> to your MySQL tables (devices, maps, logs).</li>
            <li>Browse each page from the sidebar to configure devices, users, licensing, and alerts.</li>
        </ol>
        <div class="chip-row">
            <span class="chip">Base URL: <?php echo htmlspecialchars($baseUrl); ?></span>
            <span class="chip">Ping API: <?php echo htmlspecialchars(rtrim($baseUrl, '/')); ?>/api/ping.php</span>
        </div>
    </section>
</div>

<div class="grid two" style="margin-top:16px;">
    <section class="card">
        <h2>Manual Ping Test</h2>
        <p class="muted">Validate connectivity without Docker. Results flow through <code>api/ping.php</code>.</p>
        <form id="pingForm" class="grid two">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="label" for="host">Hostname or IP</label>
                <input class="input" type="text" id="host" name="host" value="8.8.8.8" placeholder="192.168.0.1">
            </div>
            <button class="btn" type="submit">Run ping</button>
            <div id="pingStatus" class="pill" style="display:none;"></div>
        </form>
        <pre id="pingOutput" class="canvas-shell" style="margin-top:12px; white-space: pre-wrap;"></pre>
    </section>
    <section class="card">
        <h2>Deployment checklist</h2>
        <div class="timeline">
            <div class="timeline-item">Import your schema (devices, notifications, maps, history) into MySQL.</div>
            <div class="timeline-item">Point cron/queue workers to your pollers for uptime and port checks.</div>
            <div class="timeline-item">Configure SMTP for alerts and client emails from <strong>License</strong> &amp; <strong>Users</strong> pages.</div>
            <div class="timeline-item">Update <code>assets/sounds</code> with your notification tones (no binaries committed).</div>
        </div>
    </section>
</div>
<script>
const form = document.getElementById('pingForm');
const statusEl = document.getElementById('pingStatus');
const outputEl = document.getElementById('pingOutput');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    statusEl.style.display = 'inline-flex';
    statusEl.textContent = 'Running...';
    statusEl.style.color = '#cbd5e1';
    outputEl.textContent = '';

    const host = document.getElementById('host').value;
    try {
        const response = await fetch('<?php echo htmlspecialchars($baseUrl); ?>/api/ping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ host })
        });

        const data = await response.json();
        if (data.error) {
            statusEl.textContent = data.error;
            statusEl.style.color = '#fca5a5';
            return;
        }

        statusEl.textContent = data.success ? 'Host reachable' : 'Host unreachable';
        statusEl.style.color = data.success ? '#bbf7d0' : '#fca5a5';
        pre.textContent = data.output || 'No output captured.';
        outputWrap.style.display = 'block';
    } catch (error) {
        statusEl.textContent = 'Request failed: ' + error.message;
        statusEl.style.color = '#fca5a5';
    }
});
</script>
</body>
</html>
