<?php
// This file serves as the public-facing page for shared network maps.
// It fetches map data based on a share_id and renders the React NetworkMap component in read-only mode.

// No authentication required for this page, but we need the database connection.
require_once 'includes/bootstrap.php'; // For getDbConnection() and session_start()
require_once 'includes/functions.php'; // For generateUuid() if needed, and other helpers

// Get the share_id from the URL
$share_id = $_GET['share_id'] ?? null;

$mapDetails = null;
$devicesData = [];
$edgesData = [];
$error_message = null;

if (!$share_id) {
    $error_message = "Share ID is missing.";
} else {
    try {
        $pdo = getDbConnection();

        // Fetch map details using the share_id
        $stmt = $pdo->prepare("SELECT id, name, background_color, background_image_url, share_id, is_public FROM maps WHERE share_id = ? AND is_public = TRUE");
        $stmt->execute([$share_id]);
        $mapDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mapDetails) {
            $error_message = "Public map not found or share link is invalid/disabled.";
        } else {
            // Fetch devices for this map (publicly accessible)
            $sqlDevices = "
                SELECT 
                    d.id, d.name, d.ip as ip_address, d.x as position_x, d.y as position_y, d.type as icon, d.status,
                    d.ping_interval, d.icon_size, d.name_text_size, d.last_seen as last_ping, d.status = 'online' as last_ping_result
                FROM 
                    devices d
                WHERE 
                    d.map_id = ?
                ORDER BY d.created_at ASC
            ";
            $stmtDevices = $pdo->prepare($sqlDevices);
            $stmtDevices->execute([$mapDetails['id']]);
            $devicesData = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

            // Fetch edges for this map (publicly accessible)
            $sqlEdges = "SELECT id, source_id as source, target_id as target, connection_type FROM device_edges WHERE map_id = ?";
            $stmtEdges = $pdo->prepare($sqlEdges);
            $stmtEdges->execute([$mapDetails['id']]);
            $edgesData = $stmtEdges->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        error_log("Error fetching shared map data: " . $e->getMessage());
        $error_message = "An internal server error occurred.";
    }
}

// Prepare data for React component
$react_props = [
    'mapDetails' => $mapDetails,
    'devices' => $devicesData,
    'edges' => $edgesData,
    'isReadOnly' => true,
    'error' => $error_message,
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mapDetails ? htmlspecialchars($mapDetails['name']) . ' (Shared View)' : 'Shared Map'; ?> - AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script type="text/javascript">
        // Pass PHP data to JavaScript
        window.SHARED_MAP_PROPS = <?php echo json_encode($react_props); ?>;
    </script>
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <div id="root" class="min-h-screen">
        <?php if ($error_message): ?>
            <div class="min-h-screen flex items-center justify-center bg-slate-900 p-4">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-8 w-full max-w-md text-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-6xl mb-4"></i>
                    <h1 class="text-2xl font-bold text-white mb-2">Error Loading Map</h1>
                    <p class="text-slate-400 mb-4"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="index.php" class="px-6 py-3 bg-cyan-600 text-white font-semibold rounded-lg hover:bg-cyan-700">Go to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            <div class="container mx-auto p-4">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-globe text-cyan-400 text-2xl"></i>
                        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($mapDetails['name']); ?> (Shared View)</h1>
                    </div>
                </div>
                <div id="network-map-container">
                    <!-- React NetworkMap component will be rendered here -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Include React and ReactFlow dependencies -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/reactflow@11/dist/umd/reactflow.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/reactflow@11/dist/style.css" />

    <!-- Include your compiled React app bundle -->
    <script src="dist/assets/index.js"></script> 
    <script>
        // This script will initialize the React component
        document.addEventListener('DOMContentLoaded', function() {
            if (window.SHARED_MAP_PROPS && !window.SHARED_MAP_PROPS.error) {
                const { mapDetails, devices, edges, isReadOnly } = window.SHARED_MAP_PROPS;
                const container = document.getElementById('network-map-container');
                
                // Assuming your React app exposes NetworkMap globally or you have a way to import it
                // For a simple setup, you might need to adjust your vite.config.ts to expose NetworkMap
                // or create a dedicated entry point for this shared view.
                // For now, we'll assume 'NetworkMap' is available via the main bundle.
                
                // This is a placeholder. You'll need to adjust your React build process
                // to make the NetworkMap component directly accessible or create a specific
                // entry point for this shared view.
                // Example: ReactDOM.createRoot(container).render(
                //   React.createElement(NetworkMap, {
                //     devices: devices,
                //     onMapUpdate: () => {}, // Read-only, no updates
                //     currentMapId: mapDetails.id,
                //     mapDetails: mapDetails,
                //     isReadOnly: isReadOnly,
                //   })
                // );
                
                // For now, we'll just log the data to confirm it's passed.
                console.log("Shared Map Data:", { mapDetails, devices, edges, isReadOnly });
                container.innerHTML = '<p class="text-center text-slate-400 py-8">React component rendering placeholder. Check console for data.</p>';
            }
        });
    </script>
</body>
</html>
<?php
// No footer needed as this is a standalone page
?>