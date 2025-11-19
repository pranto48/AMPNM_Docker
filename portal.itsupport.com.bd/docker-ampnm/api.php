<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    // --- Public Actions (NO AUTH REQUIRED) ---
    // These actions must be handled and exit BEFORE any authentication checks.
    if ($action === 'get_public_map_data') {
        $pdo = getDbConnection();
        $map_id = $_GET['map_id'] ?? null;

        if (!$map_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Map ID is required.']);
            exit;
        }

        // Fetch map details, ENSURING public_view_enabled is TRUE
        $stmt_map = $pdo->prepare("SELECT id, name, background_color, background_image_url, public_view_enabled FROM maps WHERE id = ? AND public_view_enabled = TRUE");
        $stmt_map->execute([$map_id]);
        $map = $stmt_map->fetch(PDO::FETCH_ASSOC);

        if (!$map) {
            http_response_code(404);
            echo json_encode(['error' => 'Map not found or not enabled for public view.']);
            exit;
        }

        // Fetch devices for the map (no user_id filter for public view)
        $stmt_devices = $pdo->prepare("
            SELECT 
                d.id, d.name, d.ip, d.check_port, d.type, d.description, d.x, d.y, 
                d.ping_interval, d.icon_size, d.name_text_size, d.icon_url, 
                d.warning_latency_threshold, d.warning_packetloss_threshold, 
                d.critical_latency_threshold, d.critical_packetloss_threshold, 
                d.show_live_ping, d.status, d.last_seen, d.last_avg_time, d.last_ttl,
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

        // Fetch edges for the map (no user_id filter for public view)
        $stmt_edges = $pdo->prepare("SELECT id, source_id, target_id, connection_type FROM device_edges WHERE map_id = ?");
        $stmt_edges->execute([$map_id]);
        $edges = $stmt_edges->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'map' => $map,
            'devices' => $devices,
            'edges' => $edges
        ]);
        exit; // IMPORTANT: Exit after public action to prevent auth_check from running
    }

    // --- Authenticated Actions (AUTH REQUIRED) ---
    // All code below this line requires authentication.
    require_once 'includes/auth_check.php'; // This will now only run if the above public action didn't exit.

    // Define actions that 'viewer' role can perform (mostly GET requests for viewing)
    $viewer_allowed_get_actions = [
        'get_maps', 'get_devices', 'get_edges', 'get_dashboard_data', 'get_ping_history',
        'get_status_logs', 'get_device_details', 'get_device_uptime',
        'get_smtp_settings', 'get_all_devices_for_subscriptions', 'get_device_subscriptions',
        'health', 'get_current_license_info', // Added for license management
    ];

    // Define specific POST actions that 'viewer' role can perform
    $viewer_allowed_post_actions = [
        'ping_all_devices',
        'check_device',
        'update_device_status_by_ip',
    ];

    // If user is a 'viewer', restrict actions
    if ($_SESSION['user_role'] === 'viewer') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!in_array($action, $viewer_allowed_post_actions)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Your role does not permit this write action.']);
                exit;
            }
        } else { // GET request
            if (!in_array($action, $viewer_allowed_get_actions)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Your role does not permit this read action.']);
                exit;
            }
            // Further restrict 'get_devices' and 'get_edges' to only allow mapped devices/edges if map_id is provided
            if (($action === 'get_devices' || $action === 'get_edges') && !isset($_GET['map_id'])) {
                 http_response_code(403);
                 echo json_encode(['error' => 'Forbidden: Viewers can only access devices/edges within a specific map.']);
                 exit;
            }
        }
    }

    // Group actions by handler
    $pingActions = ['manual_ping', 'scan_network', 'ping_device', 'get_ping_history'];
    $deviceActions = ['get_devices', 'create_device', 'update_device', 'delete_device', 'get_device_details', 'check_device', 'check_all_devices_globally', 'ping_all_devices', 'get_device_uptime', 'upload_device_icon', 'import_devices', 'update_device_status_by_ip'];
    $mapActions = ['get_maps', 'create_map', 'delete_map', 'get_edges', 'create_edge', 'update_edge', 'delete_edge', 'import_map', 'update_map', 'upload_map_background'];
    $dashboardActions = ['get_dashboard_data'];
    $userActions = ['get_users', 'create_user', 'delete_user', 'update_user_role', 'update_user_password'];
    $logActions = ['get_status_logs'];
    $notificationActions = ['get_smtp_settings', 'save_smtp_settings', 'get_device_subscriptions', 'save_device_subscription', 'delete_device_subscription', 'get_all_devices_for_subscriptions'];
    $licenseActions = ['get_current_license_info', 'update_app_license_key', 'force_license_recheck']; // Added license actions

    if (in_array($action, $pingActions)) {
        require __DIR__ . '/api/handlers/ping_handler.php';
    } elseif (in_array($action, $deviceActions)) {
        require __DIR__ . '/api/handlers/device_handler.php';
    } elseif (in_array($action, $mapActions)) {
        require __DIR__ . '/api/handlers/map_handler.php';
    } elseif (in_array($action, $dashboardActions)) {
        require __DIR__ . '/api/handlers/dashboard_handler.php';
    } elseif (in_array($action, $userActions)) {
        require __DIR__ . '/api/handlers/user_handler.php';
    } elseif (in_array($action, $logActions)) {
        require __DIR__ . '/api/handlers/log_handler.php';
    } elseif (in_array($action, $notificationActions)) {
        require __DIR__ . '/api/handlers/notification_handler.php';
    } elseif (in_array($action, $licenseActions)) { // Handle new license actions
        require __DIR__ . '/api/handlers/license_handler.php';
    } elseif ($action === 'health') {
        echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("API Error for action '{$action}': " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal server error occurred: ' . $e->getMessage()]);
}
?>