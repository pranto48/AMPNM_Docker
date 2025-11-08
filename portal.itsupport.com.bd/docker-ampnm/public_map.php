<?php
require_once 'includes/functions.php'; // For getDbConnection

$map_id = $_GET['map_id'] ?? null;

if (!$map_id) {
    die("Error: Map ID is required.");
}

// Fetch map data using the public API endpoint
$api_url = "api.php?action=get_public_map_data&map_id=" . urlencode($map_id);
$response = file_get_contents($api_url);
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
        $node['size'] = (int)$icon_size / 2; // vis.js size is radius
        $node['color'] = ['border' => $node_color, 'background' => 'transparent'];
        $node['borderWidth'] = 3;
    } elseif ($d['type'] === 'box') {
        $node['shape'] = 'box';
        $node['color'] = ['background' => 'rgba(49, 65, 85, 0.5)', 'border' => '#475569'];
        $node['margin'] = 20;
        $node['level'] = -1;
    } else {
        $node['shape'] = 'icon';
        $node['icon'] = [
            'face' => "'Font Awesome 6 Free'",
            'weight' => "900",
            'code' => $icon_code,
            'size' => (int)$icon_size,
            'color' => $node_color
        ];
    }
    $vis_nodes[] = $node;
}

$vis_edges = [];
foreach ($edges as $e) {
    $edge_color_map = [
        'cat5' => '#a78bfa',
        'fiber' => '#f97316',
        'wifi' => '#38bdf8',
        'radio' => '#84cc16'
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
        'dashes' => ($connection_type === 'wifi' || $connection_type === 'radio') ? [5, 5] : false,
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
                    shape: 'icon',
                    icon: {
                        face: "'Font Awesome 6 Free'",
                        weight: "900",
                        size: 50,
                        color: '#22c55e'
                    },
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
        });
    </script>
</body>
</html>