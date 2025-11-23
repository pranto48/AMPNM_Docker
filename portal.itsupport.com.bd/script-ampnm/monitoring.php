<?php
require_once __DIR__ . '/includes/auth.php';
ampnm_require_auth();
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Monitoring Graphs', 'monitoring');
?>
<div class="grid two">
    <section class="card">
        <h2>Latency over time</h2>
        <div class="canvas-shell">Connect your RRD/prometheus data to chart.js here.</div>
        <p style="color: var(--muted);">Use the Docker poller outputs or your own cron jobs to populate time-series data.</p>
    </section>
    <section class="card">
        <h2>Port availability</h2>
        <div class="canvas-shell">Stacked bar chart for port successes vs failures.</div>
        <p style="color: var(--muted);">Render per device or per group using your <code>status_logs</code> table.</p>
    </section>
</div>
<div class="card" style="margin-top:16px;">
    <h2>Uptime leaderboard</h2>
    <table class="table">
        <thead><tr><th>Device</th><th>Method</th><th>30d uptime</th><th>Notes</th></tr></thead>
        <tbody>
            <tr><td>Core Switch</td><td>Ping</td><td>99.99%</td><td>Baseline from ICMP history</td></tr>
            <tr><td>Firewall</td><td>Port 443</td><td>99.95%</td><td>SSL monitor</td></tr>
            <tr><td>Database</td><td>Port 3306</td><td>99.90%</td><td>App health probe</td></tr>
        </tbody>
    </table>
</div>
<?php renderPageEnd(); ?>
