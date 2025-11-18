<?php
require_once 'includes/functions.php'; // For getDbConnection and generateFaSvgDataUrl

$map_id = $_GET['map_id'] ?? null;

if (!$map_id) {
    die("Error: Map ID is required.");
}

// Construct the full HTTP URL for the API endpoint
// Using 127.0.0.1 for internal server-side calls within the Docker container for reliability
$api_url = "http://127.0.0.1:2266/api.php?action=get_public_map_data&map_id=" . urlencode($map_id);

// Fetch map data using the public API endpoint
$response = @file_get_contents($api_url); // Suppress warnings for file_get_contents

if ($response === false) {
    $error = error_get_last();
    die("Error loading map data from API: " . ($error['message'] ?? "Unknown error. Ensure the Docker app is running and accessible at http://127.0.0.1:2266 from within the container."));
}

$data = json_decode($response, true);

if (!$data || isset($data['error'])) {
    die("Error loading map: " . ($data['error'] ?? "Unknown error."));
}

$map = $data['map'];
$devices = $data['devices'];
$edges = $data['edges'];

// Prepare data for vis.js
$vis_nodes = [];
foreach ($devices as $d) {
    $status_color_map = [
        'online' => '#22c55e',
        'warning' => '#f59e0b',
        'critical' => '#ef4444',
        'offline' => '#64748b',
        'unknown' => '#94a3b8'
    ];
    $icon_map = [
        'server' => '\uf233', 'router' => '\uf4d7', 'switch' => '\uf796', 'printer' => '\uf02f', 'nas' => '\uf0a0',
        'camera' => '\uf030', 'other' => '\uf108', 'firewall' => '\uf3ed', 'ipphone' => '\uf87d',
        'punchdevice' => '\uf2c2', 'wifi-router' => '\uf1eb', 'radio-tower' => '\uf519',
        'rack' => '\uf1b3', 'laptop' => '\uf109', 'tablet' => '\uf3fa', 'mobile' => '\uf3cd',
        'cloud' => '\uf0c2', 'database' => '\uf1c0', 'box' => '\uf49e'
    ];

    $status = $d['status'] ?? 'unknown';
    $icon_code = $icon_map[$d['type']] ?? $icon_map['other'];
    $icon_size = $d['icon_size'] ?? 50;
    $name_text_size = $d['name_text_size'] ?? 14;
    $node_color = $status_color_map[$status] ?? $status_color_map['unknown'];

    // Build initial title for PHP render
    $title = "{$d['name']}<br>{$d['ip']}<br>Status: {$status}";
    if ($status === 'offline' && $d['last_ping_output']) {
        $lines = explode("\n", $d['last_ping_output']);
        $reason = 'No response';
        foreach ($lines as $line) {
            if (stripos($line, 'unreachable') !== false || stripos($line, 'timed out') !== false || stripos($line, 'could not find host') !== false) {
                $reason = trim($line);
                break;
            }
        }
        $title .= "<br><small style='color: #fca5a5; font-family: monospace;'>{$reason}</small>";
    }

    $label = $d['name'];
    if (($d['show_live_ping'] ?? false) && $status === 'online' && ($d['last_avg_time'] ?? null) !== null) {
        $label .= "\n{$d['last_avg_time']}ms | TTL:{$d['last_ttl']}";
    }

    $node = [
        'id' => $d['id'],
        'label' => $label,
        'title' => $title,
        'x' => $d['x'],
        'y' => $d['y'],
        'font' => ['color' => 'white', 'size' => (int)$name_text_size, 'multi' => true],
        'deviceData' => $d // Store original device data for tooltip/future use
    ];

    if ($d['icon_url']) {
        $node['shape'] = 'image';
        $node['image'] = $d['icon_url'];
        $node['size'] = (int)$icon_size; // vis.js size is diameter for image shape
        $node['color'] = ['border' => $node_color, 'background' => 'transparent'];
        $node['borderWidth'] = 3;
    } elseif ($d['type'] === 'box') {
        $node['shape'] = 'box';
        $node['color'] = ['background' => 'rgba(49, 65, 85, 0.5)', 'border' => '#475569'];
        $node['margin'] = 20;
        $node['level'] = -1;
    } else {
        // For Font Awesome icons, generate an SVG data URL and use shape 'image'
        $svg_data_url = generateFaSvgDataUrl($icon_code, (int)$icon_size, $node_color);
        $node['shape'] = 'image';
        $node['image'] = $svg_data_url;
        $node['size'] = (int)$icon_size; // Use the icon_size as the diameter for the image
        $node['color'] = ['border' => $node_color, 'background' => 'transparent'];
        $node['borderWidth'] = 3;
    }
    $vis_nodes[] = $node;
}

$vis_edges = [];
foreach ($edges as $e) {
    $edge_color_map = [
        'cat5' => '#a78bfa',
        'fiber' => '#f97316',
        'wifi' => '#38bdf8',
        'radio' => '#84cc16',
        'lan' => '#60a5fa', // New LAN color (blue)
        'logical-tunneling' => '#c084fc' // New Logical Tunneling color (purple)
    ];
    $connection_type = $e['connection_type'] ?? 'cat5';
    $edge_color = $edge_color_map[$connection_type] ?? $edge_color_map['cat5'];

    $vis_edges[] = [
        'id' => $e['id'],
        'from' => $e['source_id'],
        'to' => $e['target_id'],
        'color' => $edge_color,
        'label' => $connection_type,
        'font' => ['color' => '#ffffff', 'size' => 12, 'align' => 'top', 'strokeWidth' => 0],
        'smooth' => true,
        'width' => 2,
        'dashes' => ($connection_type === 'wifi' || $connection_type === 'radio' || $connection_type === 'logical-tunneling') ? [5, 5] : false,
    ];
}

$background_style = '';
if ($map['background_image_url']) {
    $background_style = "background-image: url('{$map['background_image_url']}'); background-size: cover; background-position: center;";
} elseif ($map['background_color']) {
    $background_style = "background-color: {$map['background_color']};";
} else {
    $background_style = "background-color: #1e293b;"; // Default if nothing set
}

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
        body {
            background-color: #0f172a; /* slate-900 */
            color: #cbd5e1;
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        #network-map-container {
            flex-grow: 1;
            height: calc(100vh - 64px); /* Adjust for header height */
            width: 100%;
            border: 1px solid #334155;
            border-radius: 0.5rem;
            overflow: hidden;
            position: relative;
            <?= $background_style ?>
        }
        .vis-tooltip {
            position: absolute; visibility: hidden; padding: 5px; white-space: nowrap;
            font-family: 'Inter', sans-serif; font-size: 14px; color: #ffffff;
            background-color: #0f172a; border: 1px solid #334155; border-radius: 3px; z-index: 10;
        }
        #status-legend {
            position: absolute; bottom: 1.25rem; right: 1.25rem; background-color: rgba(15, 23, 42, 0.8);
            border: 1px solid #334155; border-radius: 0.5rem; padding: 0.75rem;
            display: flex; flex-direction: column; gap: 0.5rem; z-index: 5;
        }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; }

        /* Explicitly ensure Font Awesome font is used for vis.js icons */
        /* These styles are now less critical for vis.js icons as we use SVG, but keep for general FA usage */
        .vis-network .vis-label {
            font-family: 'Inter', sans-serif !important; /* Keep Inter for labels */
        }
        /* The .vis-icon class will no longer be used for our custom SVG icons */
    </style>
</head>
<body>
    <header class="bg-slate-800/50 backdrop-blur-lg shadow-lg py-3 px-4">
        <div class="container mx-auto flex items-center justify-between">
            <h1 class="text-xl font-bold text-white">Shared Map: <?= htmlspecialchars($map['name']) ?></h1>
            <a href="index.php" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm">
                <i class="fas fa-home mr-2"></i>Go to Dashboard
            </a>
        </div>
    </header>

    <div id="network-map-container"></div>

    <div id="status-legend">
        <div class="legend-item"><div class="legend-dot bg-green-500"></div><span>Online</span></div>
        <div class="legend-item"><div class="legend-dot bg-yellow-500"></div><span>Warning</span></div>
        <div class="legend-item"><div class="legend-dot bg-red-500"></div><span>Critical</span></div>
        <div class="legend-item"><div class="legend-dot bg-slate-500"></div><span>Offline</span></div>
        <div class="legend-item"><div class="legend-dot bg-slate-400"></div><span>Unknown</span></div>
    </div>

    <script>
        // Define color and icon maps (copy from MapApp.config)
        const statusColorMap = {
            online: '#22c55e', warning: '#f59e0b', critical: '#ef4444',
            offline: '#64748b', unknown: '#94a3b8'
        };
        const iconMap = {
            server: '\uf233', router: '\uf4d7', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0',
            camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf87d',
            punchdevice: '\uf2c2', 'wifi-router': '\uf1eb', 'radio-tower': '\uf519',
            rack: '\uf1b3', laptop: '\uf109', tablet: '\uf3fa', mobile: '\uf3cd',
            cloud: '\uf0c2', database: '\uf1c0', box: '\uf49e'
        };
        const edgeColorMap = {
            cat5: '#a78bfa', fiber: '#f97316', wifi: '#38bdf8', radio: '#84cc16',
            lan: '#60a5fa', // New LAN color (blue)
            'logical-tunneling': '#c084fc' // New Logical Tunneling color (purple)
        };

        // Utility to build node title (copy from MapApp.utils.buildNodeTitle)
        function buildNodeTitle(deviceData) {
            let title = `${deviceData.name}<br>${deviceData.ip}<br>Status: ${deviceData.status}`;
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
                title += `<br><small style='color: #fca5a5; font-family: monospace;'>${sanitizedReason}</small>`;
            }
            return title;
        }

        let animationFrameId = null;
        let tick = 0;

        function updateAndAnimateEdges(nodesDataSet, edgesDataSet) {
            tick++;
            const animatedDashes = [4 - (tick % 12), 8, tick % 12];
            const updates = [];
            const allEdges = edgesDataSet.get();
            if (nodesDataSet.length > 0 && allEdges.length > 0) {
                const deviceStatusMap = new Map(nodesDataSet.get({ fields: ['id', 'deviceData'] }).map(d => [d.id, d.deviceData.status]));
                allEdges.forEach(edge => {
                    const sourceStatus = deviceStatusMap.get(edge.from);
                    const targetStatus = deviceStatusMap.get(edge.to);
                    const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                    const isActive = sourceStatus === 'online' && targetStatus === 'online';
                    
                    const color = isOffline ? statusColorMap.offline : (edgeColorMap[edge.label] || edgeColorMap.cat5);
                    
                    let dashes = false;
                    if (isActive) { dashes = animatedDashes; } 
                    else if (edge.label === 'wifi' || edge.label === 'radio' || edge.label === 'logical-tunneling') { dashes = [5, 5]; }
                    
                    updates.push({ id: edge.id, color: { color: color }, dashes });
                });
            }
            if (updates.length > 0) edgesDataSet.update(updates);
            animationFrameId = requestAnimationFrame(() => updateAndAnimateEdges(nodesDataSet, edgesDataSet));
        }

        async function updateMapLive(nodesDataSet, edgesDataSet) {
            console.log("Fetching live map data...");
            const mapId = <?= json_encode($map_id) ?>;
            const apiUrl = `http://localhost:2266/api.php?action=get_public_map_data&map_id=${mapId}`; // Changed to localhost

            try {
                const response = await fetch(apiUrl);
                if (!response.ok) {
                    console.error(`HTTP error! status: ${response.status}`);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                console.log("Live map data fetched successfully:", data);

                if (data && data.devices) {
                    const nodeUpdates = [];
                    data.devices.forEach(d => {
                        const oldNode = nodesDataSet.get(d.id);
                        if (!oldNode) return; // Skip if node doesn't exist

                        const status = d.status ?? 'unknown';
                        const icon_size = d.icon_size ?? 50;
                        const name_text_size = d.name_text_size ?? 14;
                        const node_color = statusColorMap[status] ?? statusColorMap.unknown;

                        let label = d.name;
                        if ((d.show_live_ping ?? false) && status === 'online' && (d.last_avg_time ?? null) !== null) {
                            label += `\n${d.last_avg_time}ms | TTL:${d.last_ttl || 'N/A'}`;
                        }

                        const updatedNode = {
                            id: d.id,
                            label: label,
                            title: buildNodeTitle(d),
                            font: { color: 'white', size: parseInt(name_text_size), multi: true },
                            deviceData: d // Update deviceData for tooltips
                        };

                        if (d.icon_url) {
                            Object.assign(updatedNode, {
                                image: d.icon_url,
                                size: parseInt(icon_size),
                                color: { border: node_color, background: 'transparent' },
                                borderWidth: 3
                            });
                        } else if (d.type === 'box') {
                            Object.assign(updatedNode, {
                                color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' },
                            });
                        } else {
                            // For Font Awesome icons, generate an SVG data URL and use shape 'image'
                            const svg_data_url = generateFaSvgDataUrl(iconMap[d.type] || iconMap.other, parseInt(icon_size), node_color);
                            Object.assign(updatedNode, {
                                shape: 'image',
                                image: svg_data_url,
                                size: parseInt(icon_size),
                                color: { border: node_color, background: 'transparent' },
                                borderWidth: 3
                            });
                        }
                        nodeUpdates.push(updatedNode);
                    });
                    nodesDataSet.update(nodeUpdates);
                }
            } catch (error) {
                console.error("Error updating map live:", error);
            }
        }

        // PHP function to generate SVG data URL (copied from includes/functions.php)
        function generateFaSvgDataUrl(iconCode, size, color) {
            const escapedIconCode = iconCode; // Already escaped in PHP
            const fontFamily = 'Font Awesome 6 Free';
            const fontWeight = '900'; // Solid icons
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}"><text x="50%" y="50%" style="font-family: '${fontFamily}'; font-weight: ${fontWeight}; font-size: ${size}px; fill: ${color}; text-anchor: middle; dominant-baseline: central;">${escapedIconCode}</text></svg>`;
            return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
        }


        document.addEventListener('DOMContentLoaded', function() {
            const nodes = new vis.DataSet(<?= json_encode($vis_nodes) ?>);
            const edges = new vis.DataSet(<?= json_encode($vis_edges) ?>);

            const container = document.getElementById('network-map-container');
            const data = { nodes: nodes, edges: edges };
            const options = {
                physics: {
                    enabled: true,
                    barnesHut: {
                        gravitationalConstant: -2000,
                        centralGravity: 0.3,
                        springLength: 95,
                        springConstant: 0.04,
                        damping: 0.09,
                        avoidOverlap: 0.5
                    },
                    solver: 'barnesHut',
                    stabilization: {
                        enabled: true,
                        iterations: 1000,
                        updateInterval: 25
                    }
                },
                interaction: {
                    dragNodes: false, // Disable dragging nodes
                    dragView: true,
                    zoomView: true,
                    hover: true,
                    selectable: false, // Nodes/edges not selectable
                    tooltipDelay: 300
                },
                edges: {
                    smooth: true,
                    width: 2,
                    font: { color: '#ffffff', size: 12, align: 'top', strokeWidth: 0 },
                    color: { inherit: 'from' } // Use color defined in edge data
                },
                nodes: {
                    shape: 'image', // Default to image shape
                    size: 50, // Default size, will be overridden by node data
                    color: {
                        border: '#22c55e',
                        background: '#1e293b',
                        highlight: { border: '#38bdf8', background: '#1e293b' },
                        hover: { border: '#38bdf8', background: '#1e293b' }
                    },
                    font: { color: 'white', size: 14, multi: true },
                    borderWidth: 3,
                    shadow: true
                },
                manipulation: {
                    enabled: false // Disable all manipulation (add/edit/delete)
                }
            };

            const network = new vis.Network(container, data, options);

            // Disable physics after stabilization for a static view
            network.on("stabilizationIterationsDone", function () {
                network.setOptions( { physics: false } );
            });

            // Start live updates
            updateMapLive(nodes, edges); // Initial call
            setInterval(() => updateMapLive(nodes, edges), 1000); // Update every 1 second
            console.log("Public map live refresh interval set to 1 second.");

            // Start edge animation
            updateAndAnimateEdges(nodes, edges);
        });
    </script>
</body>
</html>