<?php
require_once __DIR__ . '/includes/auth.php';
ampnm_require_auth();
require_once __DIR__ . '/includes/layout.php';
renderPageStart('License', 'license');
?>
<div class="grid two">
    <section class="card">
        <h2>License key</h2>
        <form class="grid two">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="label" for="license">Key</label>
                <input class="input" id="license" placeholder="AMP-XXXX-XXXX">
            </div>
            <div class="form-group">
                <label class="label" for="customer">Customer</label>
                <input class="input" id="customer" placeholder="ACME Inc">
            </div>
            <div class="form-group">
                <label class="label" for="expiry">Expiry</label>
                <input class="input" id="expiry" placeholder="2025-12-31">
            </div>
            <button type="button" class="btn">Validate</button>
            <button type="button" class="btn secondary">Sync with server</button>
        </form>
        <p style="color: var(--muted);">Connect this form to your hardened license endpoints for AES-encrypted keys and tamper detection (mirroring Docker build).</p>
    </section>
    <section class="card">
        <h2>Server fingerprint</h2>
        <div class="grid two">
            <div>
                <p class="muted">Hostname</p>
                <strong>web01</strong>
            </div>
            <div>
                <p class="muted">PHP</p>
                <strong><?php echo phpversion(); ?></strong>
            </div>
            <div>
                <p class="muted">Hash</p>
                <strong>bind-to-host</strong>
            </div>
            <div>
                <p class="muted">Status</p>
                <strong class="badge">Compliant</strong>
            </div>
        </div>
    </section>
</div>
<?php renderPageEnd(); ?>
