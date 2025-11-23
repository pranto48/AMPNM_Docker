<?php
require_once __DIR__ . '/includes/layout.php';
renderPageStart('Topology Map', 'map');
?>
<div class="card">
    <h2>Shareable map</h2>
    <p>Drop in your vis-network or Leaflet renderer here. The PHP build mirrors the Docker map endpoints—pull device nodes from <code>/api/map</code> (implement in <code>api</code>) and render them in this panel.</p>
    <div class="map-panel">
        <div>
            <p style="margin:0 0 8px 0;">Static preview</p>
            <div class="chip-row" style="justify-content:center;">
                <span class="chip">Switch → Core</span>
                <span class="chip">Servers</span>
                <span class="chip">IoT</span>
            </div>
        </div>
    </div>
    <div class="chip-row" style="margin-top:12px;">
        <span class="chip">Public map: <?php echo htmlspecialchars(rtrim($baseUrl, '/')); ?>/public_map.php?map_id=1</span>
        <span class="chip">Admin map: <?php echo htmlspecialchars(rtrim($baseUrl, '/')); ?>/map.php</span>
    </div>
</div>
<?php renderPageEnd(); ?>
