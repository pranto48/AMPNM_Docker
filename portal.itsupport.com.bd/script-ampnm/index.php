<?php require_once __DIR__ . '/includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['app_name'] ?? 'AMPNM PHP'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        header { background: #0ea5e9; padding: 16px 24px; color: #0b1724; font-weight: bold; }
        main { padding: 24px; max-width: 1100px; margin: 0 auto; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 20px; box-shadow: 0 12px 30px rgba(0,0,0,0.25); }
        .grid { display: grid; gap: 16px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #334155; background: #0b1220; color: #e2e8f0; }
        button { padding: 10px 16px; border: none; border-radius: 8px; background: #0ea5e9; color: #0b1724; font-weight: 700; cursor: pointer; }
        pre { background: #0b1220; padding: 12px; border-radius: 8px; border: 1px solid #1f2937; overflow-x: auto; }
        a { color: #7dd3fc; }
    </style>
</head>
<body>
<header>
    <div style="display:flex; justify-content: space-between; align-items: center;">
        <span>AMPNM PHP (XAMPP ready)</span>
        <span>Base URL: <?php echo htmlspecialchars($baseUrl); ?></span>
    </div>
</header>
<main>
    <div class="grid grid-2">
        <section class="card">
            <h2>Manual Ping Test</h2>
            <p>Use this endpoint-ready form to verify ICMP connectivity without Docker. The API is available at <code><?php echo htmlspecialchars($baseUrl); ?>/api/ping.php</code>.</p>
            <form id="pingForm">
                <label for="host">Hostname or IP</label>
                <input type="text" id="host" name="host" value="8.8.8.8" placeholder="192.168.0.1">
                <div style="margin-top: 12px; display:flex; gap: 12px; align-items:center;">
                    <button type="submit">Run ping</button>
                    <span id="pingStatus"></span>
                </div>
            </form>
            <div id="pingOutput" style="margin-top: 12px; display:none;">
                <pre id="pingPre"></pre>
            </div>
        </section>

        <section class="card">
            <h2>Deployment notes</h2>
            <ul>
                <li>Copy this folder to your XAMPP <code>htdocs</code> directory (e.g., <code>C:/xampp/htdocs/script-ampnm</code>).</li>
                <li>Duplicate <code>config.sample.php</code> to <code>config.php</code> and adjust DB + SMTP credentials.</li>
                <li>Place your license, map images, and notification sounds under <code>assets/</code> as needed.</li>
                <li>Use Apache with PHP 8.1+; the scripts avoid Docker-only dependencies.</li>
            </ul>
            <p style="margin-top: 12px;">This scaffold mirrors the Docker-AMPNM features (map view, device monitoring, email alerts) while remaining portable for shared hosting.</p>
        </section>
    </div>

    <section class="card" style="margin-top: 16px;">
        <h2>Roadmap hooks</h2>
        <p>Extend this starter by wiring routes to your MySQL database and SMTP provider. Recommended entry points:</p>
        <ul>
            <li><strong>Devices &amp; topology:</strong> build controllers in <code>/api</code> that mirror <code>docker-ampnm/api</code> handlers.</li>
            <li><strong>Authentication:</strong> reuse your portal session logic or add a lightweight JWT middleware.</li>
            <li><strong>Email:</strong> connect SMTP settings from <code>config.php</code> to send password resets and product updates.</li>
        </ul>
    </section>
</main>
<script>
const form = document.getElementById('pingForm');
const statusEl = document.getElementById('pingStatus');
const outputWrap = document.getElementById('pingOutput');
const pre = document.getElementById('pingPre');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    statusEl.textContent = 'Running...';
    outputWrap.style.display = 'none';
    pre.textContent = '';

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
