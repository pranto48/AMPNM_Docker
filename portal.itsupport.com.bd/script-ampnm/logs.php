<?php
require_once __DIR__ . '/includes/auth.php';
ampnm_require_auth();
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Event Logs', 'logs');
?>
<div class="card">
    <h2>Status history</h2>
    <table class="table">
        <thead><tr><th>Time</th><th>Device</th><th>Method</th><th>Message</th></tr></thead>
        <tbody>
            <tr><td>Just now</td><td>Core Switch</td><td>Ping</td><td>Latency 12ms</td></tr>
            <tr><td>5m ago</td><td>Firewall</td><td>Port 443</td><td>Recovered</td></tr>
            <tr><td>20m ago</td><td>DB Server</td><td>Port 3306</td><td>Timeout</td></tr>
        </tbody>
    </table>
    <p style="color: var(--muted);">Bind this table to your <code>status_logs</code> or <code>history</code> endpoints for live data.</p>
</div>
<?php renderPageEnd(); ?>
