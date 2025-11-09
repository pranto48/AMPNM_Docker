<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    // --- Public Actions (NO AUTH REQUIRED) ---
    if ($action === 'get_public_map_data') {
        $pdo = getDbConnection();
        $map_id = $_GET['map_id'] ?? null;

        if (!$map_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Map ID is required.']);
            exit;
        }

        // Fetch map details
        $stmt_map = $pdo->prepare("SELECT id, name, background_color, background_image_url FROM maps WHERE id = ?");
        $stmt_map->execute([$map_id]);
        $map = $stmt_map->fetch(PDO::FETCH_ASSOC);

        if (!$map) {
            http_response_code(404);
            echo json_encode(['error' => 'Map not found.']);
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
    require_once 'includes/auth_check.php'; // This will now only run if the above public action didn't exit.

    $pdo = getDbConnection();
    $current_user_id = $_SESSION['user_id'];

    switch ($action) {
        case 'manual_ping':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $host = $input['host'] ?? '';
                $count = $input['count'] ?? 4; // Use count from input, default to 4
                if (empty($host)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Host is required']);
                    exit;
                }
                $result = executePing($host, $count);
                savePingResult($pdo, $host, $result);
                echo json_encode($result);
            }
            break;

        case 'check_all_devices_globally':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $stmt = $pdo->prepare("SELECT * FROM devices WHERE enabled = TRUE AND user_id = ? AND ip IS NOT NULL AND ip != ''");
                $stmt->execute([$current_user_id]);
                $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $checked_count = 0;
                $status_changes = 0;

                foreach ($devices as $device) {
                    $old_status = $device['status'];
                    $new_status = 'unknown';
                    $last_avg_time = null;
                    $last_ttl = null;
                    $last_seen = $device['last_seen'];
                    $details = '';

                    if (!empty($device['check_port']) && is_numeric($device['check_port'])) {
                        $portCheckResult = checkPortStatus($device['ip'], $device['check_port']);
                        $new_status = $portCheckResult['success'] ? 'online' : 'offline';
                        $last_avg_time = $portCheckResult['time'];
                        $details = $portCheckResult['success'] ? "Port {$device['check_port']} is open." : "Port {$device['check_port']} is closed.";
                    } else {
                        $pingResult = executePing($device['ip'], 1);
                        savePingResult($pdo, $device['ip'], $pingResult);
                        $parsedResult = parsePingOutput($pingResult['output']);
                        $new_status = getStatusFromPingResult($device, $pingResult, $parsedResult, $details);
                        $last_avg_time = $parsedResult['avg_time'] ?? null;
                        $last_ttl = $parsedResult['ttl'] ?? null;
                    }
                    
                    if ($new_status !== 'offline') { $last_seen = date('Y-m-d H:i:s'); }
                    
                    logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
                    // sendEmailNotification($pdo, $device, $old_status, $new_status, $details); // Removed email notification for now
                    $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
                    $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id'], $current_user_id]);
                    $checked_count++;
                    if ($old_status !== $new_status) {
                        $status_changes++;
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Checked {$checked_count} devices.",
                    'checked_count' => $checked_count,
                    'status_changes' => $status_changes
                ]);
            }
            break;

        case 'get_dashboard_data':
            // Get detailed stats for each status
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
                    SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning,
                    SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
                FROM devices WHERE user_id = ?
            ");
            $stmt->execute([$current_user_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Ensure counts are integers, not null
            $stats['online'] = $stats['online'] ?? 0;
            $stats['warning'] = $stats['warning'] ?? 0;
            $stats['critical'] = $stats['critical'] ?? 0;
            $stats['offline'] = $stats['offline'] ?? 0;

            // Get recent status logs for the map's devices
            $stmt = $pdo->prepare("
                SELECT 
                    dsl.created_at, 
                    dsl.status, 
                    dsl.details, 
                    d.name as device_name, 
                    d.ip as device_ip
                FROM 
                    device_status_logs dsl
                JOIN 
                    devices d ON dsl.device_id = d.id
                WHERE 
                    d.user_id = ?
                ORDER BY 
                    dsl.created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$current_user_id]);
            $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'stats' => $stats,
                'recent_activity' => $recent_activity
            ]);
            break;

        case 'get_users':
            $stmt = $pdo->query("SELECT id, username, created_at FROM users ORDER BY username ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($users);
            break;

        case 'create_user':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $input['username'] ?? '';
                $password = $input['password'] ?? '';

                if (empty($username) || empty($password)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Username and password are required.']);
                    exit;
                }

                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Username already exists.']);
                    exit;
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashed_password]);
                
                echo json_encode(['success' => true, 'message' => 'User created successfully.']);
            }
            break;

        case 'delete_user':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = $input['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID is required.']);
                    exit;
                }

                // Prevent admin from deleting themselves
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && $user['username'] === 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot delete the admin user.']);
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
            }
            break;

        case 'health':
            echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Invalid API action.']);
            break;
    }

} catch (Exception $e) {
    error_log("API Error for action '{$action}': " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal server error occurred: ' . $e->getMessage()]);
}
?>