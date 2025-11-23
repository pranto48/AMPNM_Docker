<?php
require_once __DIR__ . '/includes/auth.php';
ampnm_require_auth();
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Dashboard', 'dashboard');
?>
<div class="grid three">
    <div class="card">
        <div class="stat"><span>Online devices</span><strong>42</strong></div>
        <p>Sample metric. Bind to your <code>devices</code> table uptime field.</p>
    </div>
    <div class="card">
        <div class="stat"><span>Alerts today</span><strong>7</strong></div>
        <p>Wire to notifications history for real-time counts.</p>
    </div>
    <div class="card">
        <div class="stat"><span>Unlicensed nodes</span><strong>0</strong></div>
        <p>License enforcement status from <code>license</code> table.</p>
    </div>
</div>

<div class="grid two" style="margin-top:16px;">
    <section class="card">
        <h2>Health trends</h2>
        <div class="canvas-shell">Connect chart.js or ApexCharts to plot poller history.</div>
        <div class="chip-row" style="margin-top:10px;">
            <span class="chip">Ping success</span>
            <span class="chip">Port availability</span>
            <span class="chip">Response time</span>
        </div>
    </section>
    <section class="card">
        <h2>Latest incidents</h2>
        <div class="timeline">
            <div class="timeline-item"><strong>Core Switch</strong> recovered from outage (ping) 5m ago.</div>
            <div class="timeline-item"><strong>VPN Gateway</strong> port 443 latency high 30m ago.</div>
            <div class="timeline-item"><strong>Firewall</strong> config drift detected 2h ago.</div>
        </div>
    </section>
</div>
<?php renderPageEnd(); ?>
