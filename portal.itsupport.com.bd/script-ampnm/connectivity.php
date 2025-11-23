<?php
require_once __DIR__ . '/includes/auth.php';
ampnm_require_auth();
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Connectivity', 'connectivity');
?>
<div class="grid two">
    <section class="card">
        <h2>Manual ICMP</h2>
        <form id="connectivityPing" class="grid two">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="label" for="connHost">Host</label>
                <input class="input" id="connHost" value="1.1.1.1">
            </div>
            <button type="submit" class="btn">Ping</button>
            <div id="connStatus" class="pill" style="display:none;"></div>
        </form>
        <pre id="connOutput" class="canvas-shell" style="margin-top:12px; white-space: pre-wrap;"></pre>
    </section>
    <section class="card">
        <h2>Port check (placeholder)</h2>
        <p style="color: var(--muted);">Reuse your device handler to run port probes on demand. Wire an endpoint here similar to the Docker <code>check_device</code> action.</p>
        <div class="chip-row">
            <span class="chip">HTTPS 443</span>
            <span class="chip">SSH 22</span>
            <span class="chip">DB 3306</span>
        </div>
    </section>
</div>
<script>
const connForm = document.getElementById('connectivityPing');
const connStatus = document.getElementById('connStatus');
const connOutput = document.getElementById('connOutput');
connForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    connStatus.style.display = 'inline-flex';
    connStatus.textContent = 'Running...';
    connStatus.style.color = '#cbd5e1';
    connOutput.textContent = '';
    const host = document.getElementById('connHost').value;
    try {
        const response = await fetch('<?php echo htmlspecialchars($baseUrl); ?>/api/ping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ host })
        });
        const data = await response.json();
        if (data.error) {
            connStatus.textContent = data.error;
            connStatus.style.color = '#fca5a5';
            return;
        }
        connStatus.textContent = data.success ? 'Host reachable' : 'Host unreachable';
        connStatus.style.color = data.success ? '#22c55e' : '#fca5a5';
        connOutput.textContent = data.output || 'No output captured.';
    } catch (error) {
        connStatus.textContent = 'Request failed: ' + error.message;
        connStatus.style.color = '#fca5a5';
    }
});
</script>
<?php renderPageEnd(); ?>
