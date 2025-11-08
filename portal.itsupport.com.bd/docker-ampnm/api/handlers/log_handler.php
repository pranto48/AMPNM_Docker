<?php
// This file is included by api.php and assumes $pdo is available.
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'basic'; // Get user role

// Helper to get admin user IDs
function getAdminUserIds($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM `users` WHERE role = 'admin'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($action === 'get_status_logs') {
    $map_id = $_GET['map_id'] ?? null;
    $device_id = $_GET['device_id'] ?? null;
    $period = $_GET['period'] ?? '24h';

    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID is required.']);
        exit;
    }

    // Check if the user has access to this map
    $sql_check_map_access = "SELECT user_id FROM maps WHERE id = ?";
    $params_check_map_access = [$map_id];
    $stmt_check_map_access = $pdo->prepare($sql_check_map_access);
    $stmt_check_map_access->execute($params_check_map_access);
    $map_owner_id = $stmt_check_map_access->fetchColumn();

    $has_access = false;
    if ($map_owner_id == $current_user_id || $user_role === 'admin') {
        $has_access = true;
    } elseif ($user_role === 'basic') {
        $admin_ids = getAdminUserIds($pdo);
        if (in_array($map_owner_id, $admin_ids)) {
            $has_access = true;
        }
    }

    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: You do not have access to this map.']);
        exit;
    }

    // Determine time interval and date format based on period
    switch ($period) {
        case 'live':
            $interval = 'INTERVAL 1 HOUR';
            $dateFormat = '%Y-%m-%d %H:%i:00'; // Group by minute
            break;
        case '7d':
            $interval = 'INTERVAL 7 DAY';
            $dateFormat = '%Y-%m-%d'; // Group by day
            break;
        case '30d':
            $interval = 'INTERVAL 30 DAY';
            $dateFormat = '%Y-%m-%d'; // Group by day
            break;
        case '24h':
        default:
            $interval = 'INTERVAL 24 HOUR';
            $dateFormat = '%Y-%m-%d %H:00:00'; // Group by hour
            break;
    }

    $sql = "
        SELECT
            DATE_FORMAT(l.created_at, ?) as time_group,
            SUM(CASE WHEN l.status = 'warning' THEN 1 ELSE 0 END) as warning_count,
            SUM(CASE WHEN l.status = 'critical' THEN 1 ELSE 0 END) as critical_count,
            SUM(CASE WHEN l.status = 'offline' THEN 1 ELSE 0 END) as offline_count
        FROM device_status_logs l
        JOIN devices d ON l.device_id = d.id
        WHERE d.user_id = ? AND d.map_id = ? AND l.created_at >= NOW() - $interval
    ";
    
    $params = [$dateFormat, $map_owner_id, $map_id]; // Use map_owner_id for filtering logs

    if ($device_id) {
        $sql .= " AND l.device_id = ?";
        $params[] = $device_id;
    }

    $sql .= " GROUP BY time_group ORDER BY time_group ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($logs);
}
?>