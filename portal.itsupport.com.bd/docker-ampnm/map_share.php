<?php
require_once 'config.php';
require_once 'includes/functions.php'; // For getDbConnection and other utilities

// No authentication required for shared map view

$map_id = $_GET['map_id'] ?? null;

if (!$map_id) {
    die("Map ID is required.");
}

$pdo = getDbConnection();

// Fetch map details
$stmt_map = $pdo->prepare("SELECT id, name, description, background_color, background_image_url FROM maps WHERE id = ?");
$stmt_map->execute([$map_id]);
$map = $stmt_map->fetch(PDO::FETCH_ASSOC);

if (!$map) {
    die("Map not found.");
}

// Fetch devices for this map
$stmt_devices = $pdo->prepare("
    SELECT 
        d.id, d.name, d.ip, d.check_port, d.type, d.description, d.x, d.y, 
        d.ping_interval, d.icon_size, d.name_text_size, d.icon_url, d.status,
        d.warning_latency_threshold, d.warning_packetloss_threshold, 
        d.critical_latency_threshold, d.critical_packetloss_threshold, 
        d.show_live_ping, d.last_avg_time, d.last_ttl,
        p.output as last_ping_output
    FROM 
        devices d
    LEFT JOIN 
        ping_results p ON p.id = (
            SELECT id 
            FROM ping_results 
            WHERE host = d.ip 
            ORDER BY created_at DESC 
            LIMIT 1
        )
    WHERE d.map_id = ?
");
$stmt_devices->execute([$map_id]);
$devices = $stmt_devices->fetchAll(PDO::FETCH_ASSOC);

// Fetch edges for this map
$stmt_edges = $pdo->prepare("SELECT id, source_id, target_id, connection_type FROM device_edges WHERE map_id = ?");
$stmt_edges->execute([$map_id]);
$edges = $stmt_edges->fetchAll(PDO::FETCH_ASSOC);

// Convert PHP data to JSON for JavaScript
$map_json = json_encode($map);
$devices_json = json_encode($devices);
$edges_json = json_encode($edges);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Map: <?= htmlspecialchars($map['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { overflow: hidden; } /* Prevent scrolling on map page */
        #network-map { height: 100vh; width: 100vw; position: fixed; top: 0; left: 0; }
        #map-title {
            position: absolute; top: 1rem; left: 1rem; z-index: 10;
            background-color: rgba(15, 23, 42, 0.8);
            border: 1px solid #334155; border-radius: 0.5rem; padding: 0.75rem 1rem;
            color: white; font-size: 1.5rem; font-weight: bold;
        }
        #status-legend {
            position: absolute; bottom: 1.25rem; right: 1.25rem; background-color: rgba(15, 23, 42, 0.8);
            border: 1px solid #334155; border-radius: 0.5rem; padding: 0.75rem;
            display: flex; flex-direction: column; gap: 0.5rem; z-index: 5;
        }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; }
        .vis-tooltip {
            position: absolute; visibility: hidden; padding: 5px; white-space: nowrap;
            font-family: 'Inter', sans-serif; font-size: 14px; color: #ffffff;
            background-color: #0f172a; border: 1px solid #334155; border-radius: 3px; z-index: 10;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-300">
    <div id="map-title"><?= htmlspecialchars($map['name']) ?></div>
    <div id="network-map"></div>
    <div id="status-legend"></div>

    <script>
        // MapApp configuration (simplified for shared view)
        const MapApp = {
            config: {
                API_URL: 'api.php', // Still points to local API for live status
                iconMap: {
                    server: '\uf233', router: '\uf4d7', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0',
                    camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf87d',
                    punchdevice: '\uf2c2', 'wifi-router': '\uf1eb', 'radio-tower': '\uf519',
                    rack: '\uf1b3', laptop: '\uf109', tablet: '\uf3fa', mobile: '\uf3cd',
                    cloud: '\uf0c2', database: '\uf1c0', box: '\uf49e'
                },
                statusColorMap: {
                    online: '#22c55e', warning: '#f59e0b', critical: '#ef4444',
                    offline: '#64748b', unknown: '#94a3b8'
                },
                edgeColorMap: {
                    cat5: '#a78bfa', fiber: '#f97316', wifi: '#38bdf8', radio: '#84cc16'
                }
            },
            state: {
                network: null,
                nodes: new vis.DataSet([]),
                edges: new vis.DataSet([]),
                currentMapId: <?= json_encode($map_id) ?>,
                pingIntervals: {},
                animationFrameId: null,
                tick: 0,
            },
            utils: {
                buildNodeTitle: (deviceData) => {
                    let title = `${deviceData.name}<br>${deviceData.ip || 'No IP'}<br>Status: ${deviceData.status}`;
                    if (deviceData.status === 'offline' && deviceData.last_ping_output) {
                        const lines = deviceData.last_ping_output.split('\n');
                        let reason = 'No response';
                        for (const line of lines) {
                            if (line.toLowerCase().includes('unreachable') || line.toLowerCase().includes('timed out') || line.toLowerCase().includes('could not find host')) {
                                reason = line.trim();
                                break;
                            }
                        }
                        const sanitizedReason = reason.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                        title += `<br><small style="color: #fca5a5; font-family: monospace;">${sanitizedReason}</small>`;
                    }
                    return title;
                },
                populateLegend: () => {
                    const legendContainer = document.getElementById('status-legend');
                    if (!legendContainer) return;
                    const statusOrder = ['online', 'warning', 'critical', 'offline', 'unknown'];
                    legendContainer.innerHTML = statusOrder.map(status => {
                        const color = MapApp.config.statusColorMap[status];
                        const label = status.charAt(0).toUpperCase() + status.slice(1);
                        return `<div class="legend-item"><div class="legend-dot" style="background-color: ${color};"></div><span>${label}</span></div>`;
                    }).join('');
                },
                updateAndAnimateEdges: () => {
                    MapApp.state.tick++;
                    const animatedDashes = [4 - (MapApp.state.tick % 12), 8, MapApp.state.tick % 12];
                    const updates = [];
                    const allEdges = MapApp.state.edges.get();
                    if (MapApp.state.nodes.length > 0 && allEdges.length > 0) {
                        const deviceStatusMap = new Map(MapApp.state.nodes.get({ fields: ['id', 'deviceData'] }).map(d => [d.id, d.deviceData.status]));
                        allEdges.forEach(edge => {
                            const sourceStatus = deviceStatusMap.get(edge.from);
                            const targetStatus = deviceStatusMap.get(edge.to);
                            const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                            const isActive = sourceStatus === 'online' && targetStatus === 'online';
                            const color = isOffline ? MapApp.config.statusColorMap.offline : (MapApp.config.edgeColorMap[edge.connection_type] || MapApp.config.edgeColorMap.cat5);
                            let dashes = false;
                            if (isActive) { dashes = animatedDashes; } 
                            else if (edge.connection_type === 'wifi' || edge.connection_type === 'radio') { dashes = [5, 5]; }
                            updates.push({ id: edge.id, color, dashes });
                        });
                    }
                    if (updates.length > 0) MapApp.state.edges.update(updates);
                    MapApp.state.animationFrameId = requestAnimationFrame(MapApp.utils.updateAndAnimateEdges);
                }
            },
            api: {
                // Simplified API for shared view - only get_device_status is needed for live updates
                get_device_status: async (deviceId) => {
                    const res = await fetch(`${MapApp.config.API_URL}?action=check_device_status&id=${deviceId}`);
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.json();
                }
            },
            deviceManager: {
                pingSingleDevice: async (deviceId) => {
                    const node = MapApp.state.nodes.get(deviceId);
                    if (!node || node.deviceData.type === 'box') return;
                    
                    const oldStatus = node.deviceData.status;
                    // Temporarily set color to cyan to indicate checking
                    MapApp.state.nodes.update({ id: deviceId, icon: { ...node.icon, color: '#06b6d4' } });
                    
                    const result = await MapApp.api.get_device_status(deviceId);
                    const newStatus = result.status;

                    const updatedDeviceData = { ...node.deviceData, status: newStatus, last_avg_time: result.last_avg_time, last_ttl: result.last_ttl, last_ping_output: result.last_ping_output };
                    let label = updatedDeviceData.name;
                    if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
                        label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
                    }
                    MapApp.state.nodes.update({ id: deviceId, deviceData: updatedDeviceData, icon: { ...node.icon, color: MapApp.config.statusColorMap[newStatus] || MapApp.config.statusColorMap.unknown }, title: MapApp.utils.buildNodeTitle(updatedDeviceData), label: label });
                },
                setupAutoPing: (devices) => {
                    Object.values(MapApp.state.pingIntervals).forEach(clearInterval);
                    MapApp.state.pingIntervals = {};
                    devices.forEach(device => {
                        if (device.ping_interval > 0 && device.ip) {
                            MapApp.state.pingIntervals[device.id] = setInterval(() => MapApp.deviceManager.pingSingleDevice(device.id), device.ping_interval * 1000);
                        }
                    });
                }
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            MapApp.utils.populateLegend();

            const mapContainer = document.getElementById('network-map');
            mapContainer.style.backgroundColor = <?= json_encode($map['background_color'] ?: '#1e293b') ?>;
            mapContainer.style.backgroundImage = <?= json_encode($map['background_image_url'] ? 'url(' . $map['background_image_url'] . ')' : '') ?>;
            mapContainer.style.backgroundSize = 'cover';
            mapContainer.style.backgroundPosition = 'center';

            const devicesData = <?= $devices_json ?>;
            const edgesData = <?= $edges_json ?>;

            const visNodes = devicesData.map(d => {
                let label = d.name;
                if (d.show_live_ping && d.status === 'online' && d.last_avg_time !== null) {
                    label += `\n${d.last_avg_time}ms | TTL:${d.last_ttl || 'N/A'}`;
                }

                const baseNode = {
                    id: d.id, label: label, title: MapApp.utils.buildNodeTitle(d),
                    x: d.x, y: d.y,
                    font: { color: 'white', size: parseInt(d.name_text_size) || 14, multi: true },
                    deviceData: d, // Store full device data for status updates
                    fixed: true, // Nodes are fixed in shared view
                    physics: false,
                };

                if (d.icon_url) {
                    return {
                        ...baseNode,
                        shape: 'image',
                        image: d.icon_url,
                        size: (parseInt(d.icon_size) || 50) / 2,
                        color: { border: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' },
                        borderWidth: 3
                    };
                }
                
                if (d.type === 'box') {
                    return { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
                }

                return { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[d.type] || MapApp.config.iconMap.other, size: parseInt(d.icon_size) || 50, color: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown } };
            });
            MapApp.state.nodes.add(visNodes);

            const visEdges = edgesData.map(e => ({ id: e.id, from: e.source_id, to: e.target_id, connection_type: e.connection_type, label: e.connection_type }));
            MapApp.state.edges.add(visEdges);

            const data = { nodes: MapApp.state.nodes, edges: MapApp.state.edges };
            const options = {
                physics: false, // Disable physics for fixed layout
                interaction: { hover: true, dragNodes: false, zoomView: true, dragView: true }, // Disable dragNodes
                edges: { smooth: true, width: 2, font: { color: '#ffffff', size: 12, align: 'top', strokeWidth: 0 } },
                manipulation: { enabled: false } // Disable all manipulation
            };
            MapApp.state.network = new vis.Network(mapContainer, data, options);

            // Fit the map to view
            MapApp.state.network.fit();

            // Setup auto-ping for live status updates
            MapApp.deviceManager.setupAutoPing(devicesData);
            MapApp.utils.updateAndAnimateEdges(); // Start edge animation
        });
    </script>
</body>
</html>