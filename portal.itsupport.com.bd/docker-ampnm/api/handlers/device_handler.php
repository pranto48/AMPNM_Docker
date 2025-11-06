<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'] ?? null; // Ensure user_id is null if not set

// Placeholder for email notification function
function sendEmailNotification($pdo, $device, $oldStatus, $newStatus, $details) {
    // In a real application, this would fetch SMTP settings and subscriptions,
    // then use a mailer library (e.g., PHPMailer) to send emails.
    // For now, we'll just log that a notification *would* be sent.
    error_log("DEBUG: Notification triggered for device '{$device['name']}' (ID: {$device['id']}). Status changed from {$oldStatus} to {$newStatus}. Details: {$details}");

    // Fetch SMTP settings for the current user
    $stmtSmtp = $pdo->prepare("SELECT * FROM smtp_settings WHERE user_id = ?");
    $stmtSmtp->execute([$_SESSION['user_id']]);
    $smtpSettings = $stmtSmtp->fetch(PDO::FETCH_ASSOC);

    if (!$smtpSettings) {
        error_log("DEBUG: No SMTP settings found for user {$_SESSION['user_id']}. Cannot send email notification.");
        return;
    }

    // Fetch subscriptions for this device and status change
    $sqlSubscriptions = "SELECT recipient_email FROM device_email_subscriptions WHERE device_id = ? AND user_id = ?";
    $paramsSubscriptions = [$device['id'], $_SESSION['user_id']];

    if ($newStatus === 'online') {
        $sqlSubscriptions .= " AND notify_on_online = TRUE";
    } elseif ($newStatus === 'offline') {
        $sqlSubscriptions .= " AND notify_on_offline = TRUE";
    } elseif ($newStatus === 'warning') {
        $sqlSubscriptions .= " AND notify_on_warning = TRUE";
    } elseif ($newStatus === 'critical') {
        $sqlSubscriptions .= " AND notify_on_critical = TRUE";
    } else {
        // No specific notification for 'unknown' status changes
        return;
    }

    $stmtSubscriptions = $pdo->prepare($sqlSubscriptions);
    $stmtSubscriptions->execute($paramsSubscriptions);
    $recipients = $stmtSubscriptions->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipients)) {
        error_log("DEBUG: No active subscriptions for device '{$device['name']}' on status '{$newStatus}'.");
        return;
    }

    // Simulate sending email
    foreach ($recipients as $recipient) {
        error_log("DEBUG: Simulating email to {$recipient} for device '{$device['name']}' status change to '{$newStatus}'.");
        // In a real scenario, you'd use a mailer library here:
        // $mailer = new PHPMailer(true);
        // Configure $mailer with $smtpSettings
        // Set recipient, subject, body
        // $mailer->send();
    }
}


function getStatusFromPingResult($device, $pingResult, $parsedResult, &$details) {
    if (!$pingResult['success']) {
        $details = 'Device offline or unreachable.';
        return 'offline';
    }

    $status = 'online';
    $details = "Online with {$parsedResult['avg_time']}ms latency.";

    if ($device['critical_latency_threshold'] && $parsedResult['avg_time'] > $device['critical_latency_threshold']) {
        $status = 'critical';
        $details = "Critical latency: {$parsedResult['avg_time']}ms (>{$device['critical_latency_threshold']}ms).";
    } elseif ($device['critical_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['critical_packetloss_threshold']) {
        $status = 'critical';
        $details = "Critical packet loss: {$parsedResult['packet_loss']}% (>{$device['critical_packetloss_threshold']}%).";
    } elseif ($device['warning_latency_threshold'] && $parsedResult['avg_time'] > $device['warning_latency_threshold']) {
        $status = 'warning';
        $details = "Warning latency: {$parsedResult['avg_time']}ms (>{$device['warning_latency_threshold']}ms).";
    } elseif ($device['warning_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['warning_packetloss_threshold']) {
        $status = 'warning';
        $details = "Warning packet loss: {$parsedResult['packet_loss']}% (>{$device['warning_packetloss_threshold']}%).";
    }
    return $status;
}

function logStatusChange($pdo, $deviceId, $oldStatus, $newStatus, $details) {
    if ($oldStatus !== $newStatus) {
        $stmt = $pdo->prepare("INSERT INTO device_status_logs (device_id, status, details) VALUES (?, ?, ?)");
        $stmt->execute([$deviceId, $newStatus, $details]);
    }
}

error_log("DEBUG: device_handler.php - Action: {$action}, Current User ID: {$current_user_id}");

switch ($action) {
    case 'import_devices':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - import_devices received. Input: " . print_r($input, true));
            $devices = $input['devices'] ?? [];
            if (empty($devices) || !is_array($devices)) {
                http_response_code(400);
                echo json_encode(['error' => 'No devices provided or invalid format.']);
                exit;
            }

            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;
            $devices_to_add_count = count($devices);

            if ($max_devices > 0 && ($current_devices + $devices_to_add_count) > $max_devices) {
                http_response_code(403);
                echo json_encode(['error' => "License limit reached. You can only add " . ($max_devices - $current_devices) . " more devices. Total allowed: {$max_devices}."]);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $sql = "INSERT INTO devices (
                    user_id, name, ip, check_port, type, description,
                    ping_interval, icon_size, name_text_size, icon_url, 
                    warning_latency_threshold, warning_packetloss_threshold, 
                    critical_latency_threshold, critical_packetloss_threshold, 
                    show_live_ping, map_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"; // map_id is NULL

                $stmt = $pdo->prepare($sql);
                $imported_count = 0;

                foreach ($devices as $device) {
                    $stmt->execute([
                        $current_user_id,
                        ($device['name'] ?? 'Imported Device'),
                        $device['ip'] ?? null,
                        $device['check_port'] ?? null,
                        $device['type'] ?? 'other',
                        $device['description'] ?? null,
                        $device['ping_interval'] ?? null,
                        $device['icon_size'] ?? 50,
                        $device['name_text_size'] ?? 14,
                        $device['icon_url'] ?? null,
                        $device['warning_latency_threshold'] ?? null,
                        $device['warning_packetloss_threshold'] ?? null,
                        $device['critical_latency_threshold'] ?? null,
                        $device['critical_packetloss_threshold'] ?? null,
                        ($device['show_live_ping'] ?? false) ? 1 : 0
                    ]);
                    $imported_count++;
                }

                $pdo->commit();
                error_log("DEBUG: device_handler.php - import_devices successful. Imported {$imported_count} devices.");
                echo json_encode(['success' => true, 'message' => "Successfully imported {$imported_count} devices."]);

            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                error_log("ERROR: device_handler.php - import_devices failed: " . $e->getMessage());
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;

    case 'check_all_devices_globally':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - check_all_devices_globally received.");
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE enabled = TRUE AND user_id = ? AND ip IS NOT NULL AND ip != '' AND type != 'box'");
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
                
                if ($old_status !== $new_status) {
                    logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
                    sendEmailNotification($pdo, $device, $old_status, $new_status, $details); // Trigger email notification
                    $status_changes++;
                }
                
                $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
                $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id'], $current_user_id]);
                $checked_count++;
            }
            
            error_log("DEBUG: device_handler.php - check_all_devices_globally successful. Checked {$checked_count} devices, {$status_changes} status changes.");
            echo json_encode([
                'success' => true, 
                'message' => "Checked {$checked_count} devices.",
                'checked_count' => $checked_count,
                'status_changes' => $status_changes
            ]);
        }
        break;

    case 'ping_all_devices':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - ping_all_devices received. Input: " . print_r($input, true));
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            $stmt = $pdo->prepare("SELECT * FROM devices WHERE enabled = TRUE AND map_id = ? AND user_id = ? AND ip IS NOT NULL AND type != 'box'");
            $stmt->execute([$map_id, $current_user_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated_devices = [];

            foreach ($devices as $device) {
                $old_status = $device['status'];
                $new_status = 'unknown';
                $last_avg_time = null;
                $last_ttl = null;
                $last_seen = $device['last_seen'];
                $check_output = 'Device has no IP configured for checking.';
                $details = '';

                if (!empty($device['ip'])) {
                    if (!empty($device['check_port']) && is_numeric($device['check_port'])) {
                        $portCheckResult = checkPortStatus($device['ip'], $device['check_port']);
                        $new_status = $portCheckResult['success'] ? 'online' : 'offline';
                        $last_avg_time = $portCheckResult['time'];
                        $check_output = $portCheckResult['output'];
                        $details = $portCheckResult['success'] ? "Port {$device['check_port']} is open." : "Port {$device['check_port']} is closed.";
                    } else {
                        $pingResult = executePing($device['ip'], 1);
                        savePingResult($pdo, $device['ip'], $pingResult);
                        $parsedResult = parsePingOutput($pingResult['output']);
                        $new_status = getStatusFromPingResult($device, $pingResult, $parsedResult, $details);
                        $last_avg_time = $parsedResult['avg_time'] ?? null;
                        $last_ttl = $parsedResult['ttl'] ?? null;
                        $check_output = $pingResult['output'];
                    }
                }
                
                if ($new_status !== 'offline') { $last_seen = date('Y-m-d H:i:s'); }
                
                logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
                sendEmailNotification($pdo, $device, $old_status, $new_status, $details); // Trigger email notification
                $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
                $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id'], $current_user_id]);

                $updated_devices[] = [
                    'id' => $device['id'],
                    'name' => $device['name'],
                    'old_status' => $old_status,
                    'status' => $new_status,
                    'last_seen' => $last_seen,
                    'last_avg_time' => $last_avg_time,
                    'last_ttl' => $last_ttl,
                    'last_ping_output' => $check_output
                ];
            }
            
            error_log("DEBUG: device_handler.php - ping_all_devices successful. Updated " . count($updated_devices) . " devices.");
            echo json_encode(['success' => true, 'updated_devices' => $updated_devices]);
        }
        break;

    case 'check_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - check_device received. Input: " . print_r($input, true));
            $deviceId = $input['id'] ?? 0;
            if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$deviceId, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) { http_response_code(404); error_log("ERROR: device_handler.php - check_device: Device ID {$deviceId} not found for user {$current_user_id}."); echo json_encode(['error' => 'Device not found']); exit; }

            $old_status = $device['status'];
            $status = 'unknown';
            $last_seen = $device['last_seen'];
            $last_avg_time = null;
            $last_ttl = null;
            $check_output = 'Device has no IP configured for checking.';
            $details = '';

            if (!empty($device['ip'])) {
                if (!empty($device['check_port']) && is_numeric($device['check_port'])) {
                    $portCheckResult = checkPortStatus($device['ip'], $device['check_port']);
                    $status = $portCheckResult['success'] ? 'online' : 'offline';
                    $last_avg_time = $portCheckResult['time'];
                    $check_output = $portCheckResult['output'];
                    $details = $portCheckResult['success'] ? "Port {$device['check_port']} is open." : "Port {$device['check_port']} is closed.";
                } else {
                    $pingResult = executePing($device['ip'], 1);
                    savePingResult($pdo, $device['ip'], $pingResult);
                    $parsedResult = parsePingOutput($pingResult['output']);
                    $status = getStatusFromPingResult($device, $pingResult, $parsedResult, $details);
                    $last_avg_time = $parsedResult['avg_time'] ?? null;
                    $last_ttl = $parsedResult['ttl'] ?? null;
                    $check_output = $pingResult['output'];
                }
            }
            
            if ($status !== 'offline') { $last_seen = date('Y-m-d H:i:s'); }
            
            logStatusChange($pdo, $deviceId, $old_status, $status, $details);
            sendEmailNotification($pdo, $device, $old_status, $status, $details); // Trigger email notification
            $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$status, $last_seen, $last_avg_time, $last_ttl, $deviceId, $current_user_id]);
            
            error_log("DEBUG: device_handler.php - check_device successful. Device ID {$deviceId}, New Status: {$status}.");
            echo json_encode(['id' => $deviceId, 'status' => $status, 'last_seen' => $last_seen, 'last_avg_time' => $last_avg_time, 'last_ttl' => $last_ttl, 'last_ping_output' => $check_output]);
        }
        break;

    case 'get_device_uptime':
        error_log("DEBUG: device_handler.php - get_device_uptime received. Device ID: {$_GET['id'] ?? 'N/A'}");
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        
        $stmt = $pdo->prepare("SELECT ip FROM devices WHERE id = ? AND user_id = ?");
        $stmt->execute([$deviceId, $current_user_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device || !$device['ip']) {
            error_log("DEBUG: device_handler.php - get_device_uptime: Device ID {$deviceId} not found or no IP.");
            echo json_encode(['uptime_24h' => null, 'uptime_7d' => null, 'outages_24h' => null]);
            exit;
        }
        $host = $device['ip'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(success) as successful FROM ping_results WHERE host = ? AND created_at >= NOW() - INTERVAL 24 HOUR");
        $stmt->execute([$host]);
        $stats24h = $stmt->fetch(PDO::FETCH_ASSOC);
        $uptime24h = ($stats24h['total'] > 0) ? round(($stats24h['successful'] / $stats24h['total']) * 100, 2) : null;
        $outages24h = $stats24h['total'] - $stats24h['successful'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(success) as successful FROM ping_results WHERE host = ? AND created_at >= NOW() - INTERVAL 7 DAY");
        $stmt->execute([$host]);
        $stats7d = $stmt->fetch(PDO::FETCH_ASSOC);
        $uptime7d = ($stats7d['total'] > 0) ? round(($stats7d['successful'] / $stats7d['total']) * 100, 2) : null;

        error_log("DEBUG: device_handler.php - get_device_uptime successful for Device ID {$deviceId}. Uptime 24h: {$uptime24h}.");
        echo json_encode(['uptime_24h' => $uptime24h, 'uptime_7d' => $uptime7d, 'outages_24h' => $outages24h]);
        break;

    case 'get_device_details':
        error_log("DEBUG: device_handler.php - get_device_details received. Device ID: {$_GET['id'] ?? 'N/A'}");
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        $stmt = $pdo->prepare("SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ? AND d.user_id = ?");
        $stmt->execute([$deviceId, $current_user_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) { http_response_code(404); error_log("ERROR: device_handler.php - get_device_details: Device ID {$deviceId} not found for user {$current_user_id}."); echo json_encode(['error' => 'Device not found']); exit; }
        $history = [];
        if ($device['ip']) {
            $stmt = $pdo->prepare("SELECT * FROM ping_results WHERE host = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$device['ip']]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        error_log("DEBUG: device_handler.php - get_device_details successful for Device ID {$deviceId}.");
        echo json_encode(['device' => $device, 'history' => $history]);
        break;

    case 'get_devices':
        error_log("DEBUG: device_handler.php - get_devices received. Map ID: {$_GET['map_id'] ?? 'N/A'}, Share ID: {$_GET['share_id'] ?? 'N/A'}, Unmapped: {$_GET['unmapped'] ?? 'N/A'}. Current User ID: {$current_user_id}");
        $map_id = $_GET['map_id'] ?? null;
        $share_id = $_GET['share_id'] ?? null; // NEW: Allow fetching by share_id
        $unmapped = isset($_GET['unmapped']);

        $sql = "
            SELECT 
                d.*, 
                m.name as map_name,
                p.output as last_ping_output
            FROM 
                devices d
            LEFT JOIN 
                maps m ON d.map_id = m.id
            LEFT JOIN 
                ping_results p ON p.id = (
                    SELECT id 
                    FROM ping_results 
                    WHERE host = d.ip 
                    ORDER BY created_at DESC 
                    LIMIT 1
                )
            WHERE 1=1
        ";
        $params = [];

        if ($map_id) {
            // If map_id is used, user must be authenticated
            if (!$current_user_id) {
                http_response_code(403);
                error_log("ERROR: device_handler.php - get_devices: Unauthorized access for map_id {$map_id}.");
                echo json_encode(['error' => 'Unauthorized access.']);
                exit;
            }
            $sql .= " AND d.map_id = ? AND d.user_id = ?";
            $params = [$map_id, $current_user_id];
        } elseif ($share_id) {
            // For shared maps, ensure the map is public
            $stmt_map = $pdo->prepare("SELECT id FROM maps WHERE share_id = ? AND is_public = TRUE");
            $stmt_map->execute([$share_id]);
            $shared_map = $stmt_map->fetch(PDO::FETCH_ASSOC);
            if (!$shared_map) {
                http_response_code(404);
                error_log("ERROR: device_handler.php - get_devices: Public map not found or invalid share_id {$share_id}.");
                echo json_encode(['error' => 'Public map not found or share link is invalid/disabled.']);
                exit;
            }
            $sql .= " AND d.map_id = ?";
            $params = [$shared_map['id']];
        } else if ($unmapped) {
            // If unmapped is used, user must be authenticated
            if (!$current_user_id) {
                http_response_code(403);
                error_log("ERROR: device_handler.php - get_devices: Unauthorized access for unmapped devices.");
                echo json_encode(['error' => 'Unauthorized access.']);
                exit;
            }
            $sql .= " AND d.map_id IS NULL AND d.user_id = ?";
            $params = [$current_user_id];
        } else {
            // Default to fetching all devices for the authenticated user if no specific map/share is requested
            if (!$current_user_id) {
                http_response_code(403);
                error_log("ERROR: device_handler.php - get_devices: Unauthorized access for all devices.");
                echo json_encode(['error' => 'Unauthorized access.']);
                exit;
            }
            $sql .= " AND d.user_id = ?";
            $params = [$current_user_id];
        }

        $sql .= " ORDER BY d.created_at ASC";
        
        error_log("DEBUG: device_handler.php - get_devices executing SQL: {$sql} with params: " . print_r($params, true));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("DEBUG: device_handler.php - get_devices successful. Returned " . count($devices) . " devices.");
        echo json_encode($devices);
        break;

    case 'create_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - create_device received. Input: " . print_r($input, true));
            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;

            if ($max_devices > 0 && $current_devices >= $max_devices) {
                http_response_code(403);
                error_log("ERROR: device_handler.php - create_device: License limit reached for user {$current_user_id}.");
                echo json_encode(['error' => "License limit reached. You cannot add more than {$max_devices} devices."]);
                exit;
            }

            $sql = "INSERT INTO devices (user_id, name, ip, check_port, type, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $current_user_id, $input['name'], $input['ip'] ?? null, $input['check_port'] ?? null, $input['type'], $input['description'] ?? null, $input['map_id'] ?? null,
                $input['x'] ?? null, $input['y'] ?? null,
                $input['ping_interval'] ?? null, $input['icon_size'] ?? 50, $input['name_text_size'] ?? 14, $input['icon_url'] ?? null,
                $input['warning_latency_threshold'] ?? null,
                $input['warning_packetloss_threshold'] ?? null,
                $input['critical_latency_threshold'] ?? null,
                $input['critical_packetloss_threshold'] ?? null,
                ($input['show_live_ping'] ?? false) ? 1 : 0
            ]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$lastId, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: device_handler.php - create_device successful. New Device ID: {$lastId}.");
            echo json_encode($device);
        }
        break;

    case 'update_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - update_device received. Input: " . print_r($input, true));
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Device ID and updates are required']); exit; }
            $allowed_fields = ['name', 'ip', 'check_port', 'type', 'description', 'x', 'y', 'map_id', 'ping_interval', 'icon_size', 'name_text_size', 'icon_url', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold', 'show_live_ping'];
            $fields = []; $params = [];
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    if ($key === 'show_live_ping') {
                        $params[] = $value ? 1 : 0;
                    } else {
                        $params[] = ($value === '' || is_null($value)) ? null : $value;
                    }
                }
            }
            if (empty($fields)) { http_response_code(400); error_log("ERROR: device_handler.php - update_device: No valid fields to update for Device ID {$id}."); echo json_encode(['error' => 'No valid fields to update']); exit; }
            $params[] = $id; $params[] = $current_user_id;
            $sql = "UPDATE devices SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
            error_log("DEBUG: device_handler.php - update_device executing SQL: {$sql} with params: " . print_r($params, true));
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $stmt = $pdo->prepare("SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ? AND d.user_id = ?"); $stmt->execute([$id, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: device_handler.php - update_device successful. Device ID: {$id}.");
            echo json_encode($device);
        }
        break;

    case 'delete_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - delete_device received. Input: " . print_r($input, true));
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            $sql = "DELETE FROM devices WHERE id = ? AND user_id = ?";
            error_log("DEBUG: device_handler.php - delete_device executing SQL: {$sql} with params: " . print_r([$id, $current_user_id], true));
            $stmt = $pdo->prepare($sql); $stmt->execute([$id, $current_user_id]);
            error_log("DEBUG: device_handler.php - delete_device successful. Device ID: {$id}.");
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        }
        break;

    case 'upload_device_icon':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: device_handler.php - upload_device_icon received. Device ID: {$_POST['id'] ?? 'N/A'}.");
            $deviceId = $_POST['id'] ?? null;
            if (!$deviceId || !isset($_FILES['iconFile'])) {
                http_response_code(400);
                error_log("ERROR: device_handler.php - upload_device_icon: Missing Device ID or icon file.");
                echo json_encode(['error' => 'Device ID and icon file are required.']);
                exit;
            }
    
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$deviceId, $current_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                error_log("ERROR: device_handler.php - upload_device_icon: Device ID {$deviceId} not found for user {$current_user_id}.");
                echo json_encode(['error' => 'Device not found or access denied.']);
                exit;
            }
    
            $uploadDir = __DIR__ . '/../../uploads/icons/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    error_log("ERROR: device_handler.php - upload_device_icon: Failed to create upload directory: {$uploadDir}.");
                    echo json_encode(['error' => 'Failed to create upload directory.']);
                    exit;
                }
            }
    
            $file = $_FILES['iconFile'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                error_log("ERROR: device_handler.php - upload_device_icon: File upload error code: " . $file['error'] . " for Device ID {$deviceId}.");
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
    
            $fileInfo = new SplFileInfo($file['name']);
            $extension = strtolower($fileInfo->getExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                error_log("ERROR: device_handler.php - upload_device_icon: Invalid file type '{$extension}' for Device ID {$deviceId}.");
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }

            $newFileName = 'device_' . $deviceId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;
            $urlPath = 'uploads/icons/' . $newFileName;
    
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE devices SET icon_url = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$urlPath, $deviceId, $current_user_id]);
                error_log("DEBUG: device_handler.php - upload_device_icon successful. Device ID {$deviceId}, URL: {$urlPath}.");
                echo json_encode(['success' => true, 'url' => $urlPath]);
            } else {
                http_response_code(500);
                error_log("ERROR: device_handler.php - upload_device_icon: Failed to save uploaded file to {$uploadPath} for Device ID {$deviceId}.");
                echo json_encode(['error' => 'Failed to save uploaded file.']);
            }
        }
        break;
}