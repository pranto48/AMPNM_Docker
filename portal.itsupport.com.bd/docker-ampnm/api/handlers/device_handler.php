<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'basic'; // Get user role

// Helper to get admin user IDs
function getAdminUserIds($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM `users` WHERE role = 'admin'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

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

switch ($action) {
    case 'import_devices':
        if ($user_role !== 'admin') { // Only admin can import devices
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin users can import devices.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                echo json_encode(['success' => true, 'message' => "Successfully imported {$imported_count} devices."]);

            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;

    case 'check_all_devices_globally':
        // Basic users can trigger checks, but only for devices they own or admin devices
        $sql = "SELECT d.*, u.role as owner_role FROM devices d JOIN users u ON d.user_id = u.id WHERE d.enabled = TRUE AND d.ip IS NOT NULL AND d.ip != '' AND d.type != 'box'";
        $params = [];

        if ($user_role === 'basic') {
            $admin_ids = getAdminUserIds($pdo);
            if (!empty($admin_ids)) {
                $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
                $sql .= " AND (d.user_id = ? OR d.user_id IN ({$admin_id_placeholders}))";
                $params[] = $current_user_id;
                $params = array_merge($params, $admin_ids);
            } else {
                $sql .= " AND d.user_id = ?";
                $params[] = $current_user_id;
            }
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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
            
            $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?");
            $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id']]);
            $checked_count++;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Checked {$checked_count} devices.",
            'checked_count' => $checked_count,
            'status_changes' => $status_changes
        ]);
        break;

    case 'ping_all_devices':
        // Basic users can trigger checks, but only for devices they own or admin devices
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            $sql = "SELECT d.*, u.role as owner_role FROM devices d JOIN users u ON d.user_id = u.id WHERE d.enabled = TRUE AND d.map_id = ? AND d.ip IS NOT NULL AND d.type != 'box'";
            $params = [$map_id];

            if ($user_role === 'basic') {
                $admin_ids = getAdminUserIds($pdo);
                if (!empty($admin_ids)) {
                    $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
                    $sql .= " AND (d.user_id = ? OR d.user_id IN ({$admin_id_placeholders}))";
                    $params[] = $current_user_id;
                    $params = array_merge($params, $admin_ids);
                } else {
                    $sql .= " AND d.user_id = ?";
                    $params[] = $current_user_id;
                }
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
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
                $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?");
                $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id']]);

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
            
            echo json_encode(['success' => true, 'updated_devices' => $updated_devices]);
        }
        break;

    case 'check_device':
        // Basic users can trigger checks, but only for devices they own or admin devices
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $deviceId = $input['id'] ?? 0;
            if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            
            $sql = "SELECT d.*, u.role as owner_role FROM devices d JOIN users u ON d.user_id = u.id WHERE d.id = ?";
            $params = [$deviceId];

            if ($user_role === 'basic') {
                $admin_ids = getAdminUserIds($pdo);
                if (!empty($admin_ids)) {
                    $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
                    $sql .= " AND (d.user_id = ? OR d.user_id IN ({$admin_id_placeholders}))";
                    $params[] = $current_user_id;
                    $params = array_merge($params, $admin_ids);
                } else {
                    $sql .= " AND d.user_id = ?";
                    $params[] = $current_user_id;
                }
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found or access denied']); exit; }

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
            $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?");
            $stmt->execute([$status, $last_seen, $last_avg_time, $last_ttl, $deviceId]);
            
            echo json_encode(['id' => $deviceId, 'status' => $status, 'last_seen' => $last_seen, 'last_avg_time' => $last_avg_time, 'last_ttl' => $last_ttl, 'last_ping_output' => $check_output]);
        }
        break;

    case 'get_device_uptime':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        
        $sql = "SELECT d.ip, u.role as owner_role FROM devices d JOIN users u ON d.user_id = u.id WHERE d.id = ?";
        $params = [$deviceId];

        if ($user_role === 'basic') {
            $admin_ids = getAdminUserIds($pdo);
            if (!empty($admin_ids)) {
                $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
                $sql .= " AND (d.user_id = ? OR d.user_id IN ({$admin_id_placeholders}))";
                $params[] = $current_user_id;
                $params = array_merge($params, $admin_ids);
            } else {
                $sql .= " AND d.user_id = ?";
                $params[] = $current_user_id;
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device || !$device['ip']) {
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

        echo json_encode(['uptime_24h' => $uptime24h, 'uptime_7d' => $uptime7d, 'outages_24h' => $outages24h]);
        break;

    case 'get_device_details':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        
        $sql = "SELECT d.*, m.name as map_name, u.role as owner_role FROM devices d LEFT JOIN maps m ON d.map_id = m.id JOIN users u ON d.user_id = u.id WHERE d.id = ?";
        $params = [$deviceId];

        if ($user_role === 'basic') {
            $admin_ids = getAdminUserIds($pdo);
            if (!empty($admin_ids)) {
                $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
                $sql .= " AND (d.user_id = ? OR d.user_id IN ({$admin_id_placeholders}))";
                $params[] = $current_user_id;
                $params = array_merge($params, $admin_ids);
            } else {
                $sql .= " AND d.user_id = ?";
                $params[] = $current_user_id;
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found or access denied']); exit; }
        $history = [];
        if ($device['ip']) {
            $stmt = $pdo->prepare("SELECT * FROM ping_results WHERE host = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$device['ip']]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['device' => $device, 'history' => $history]);
        break;

    case 'get_devices':
        $map_id = $_GET['map_id'] ?? null;
        $unmapped = isset($_GET['unmapped']);

        $sql = "
            SELECT 
                d.*, 
                m.name as map_name,
                p.output as last_ping_output,
                u.role as owner_role
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
            JOIN users u ON d.user_id = u.id
        ";
        $params = [];
        $where_clauses = [];

        if ($user_role === 'basic') {
            $admin_ids = getAdminUserIds($pdo);
            if (!empty($admin_ids)) {
                $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
                $where_clauses[] = "(d.user_id = ? OR d.user_id IN ({$admin_id_placeholders}))";
                $params[] = $current_user_id;
                $params = array_merge($params, $admin_ids);
            } else {
                $where_clauses[] = "d.user_id = ?";
                $params[] = $current_user_id;
            }
        } else { // Admin user can see all devices
            // No user_id restriction for admin
        }

        if ($map_id) { 
            $where_clauses[] = "d.map_id = ?"; 
            $params[] = $map_id; 
        }
        if ($unmapped) {
            $where_clauses[] = "d.map_id IS NULL";
        }

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $sql .= " ORDER BY d.created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'create_device':
        if ($user_role !== 'admin') { // Only admin can create devices
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin users can create devices.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;

            if ($max_devices > 0 && $current_devices >= $max_devices) {
                http_response_code(403);
                echo json_encode(['error' => "License limit reached. You cannot add more than {$max_devices} devices."]);
                exit;
            }

            $sql = "INSERT INTO devices (user_id, name, ip, check_port, type, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $current_user_id, $input['name'], $input['ip'] ?? null, $input['check_port'] ?? null, $input['type'], $input['description'] ?? null, $input['map_id'] ?? null,
                $input['x'] ?? null, $input['y'] ?? null,
                $input['ping_interval'] ?? null, $input['icon_size'] ?? 50, $input['name_text_size'] ?? 14, $input['icon_url'] ?? null,
                $input['warning_latency_threshold'] ?? null, $input['warning_packetloss_threshold'] ?? null,
                $input['critical_latency_threshold'] ?? null, $input['critical_packetloss_threshold'] ?? null,
                ($input['show_live_ping'] ?? false) ? 1 : 0
            ]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT d.*, m.name as map_name, u.role as owner_role FROM devices d LEFT JOIN maps m ON d.map_id = m.id JOIN users u ON d.user_id = u.id WHERE d.id = ?"); 
            $stmt->execute([$lastId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($device);
        }
        break;

    case 'update_device':
        if ($user_role !== 'admin') { // Only admin can update devices
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin users can update devices.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Device ID and updates are required']); exit; }
            
            // Ensure the user owns the device or is an admin
            $stmt_check_owner = $pdo->prepare("SELECT user_id FROM devices WHERE id = ?");
            $stmt_check_owner->execute([$id]);
            $device_owner_id = $stmt_check_owner->fetchColumn();

            if ($device_owner_id != $current_user_id && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You can only update your own devices.']);
                exit;
            }

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
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'No valid fields to update']); exit; }
            $params[] = $id; 
            $sql = "UPDATE devices SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $stmt = $pdo->prepare("SELECT d.*, m.name as map_name, u.role as owner_role FROM devices d LEFT JOIN maps m ON d.map_id = m.id JOIN users u ON d.user_id = u.id WHERE d.id = ?"); 
            $stmt->execute([$id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($device);
        }
        break;

    case 'delete_device':
        if ($user_role !== 'admin') { // Only admin can delete devices
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin users can delete devices.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            
            // Ensure the user owns the device or is an admin
            $stmt_check_owner = $pdo->prepare("SELECT user_id FROM devices WHERE id = ?");
            $stmt_check_owner->execute([$id]);
            $device_owner_id = $stmt_check_owner->fetchColumn();

            if ($device_owner_id != $current_user_id && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You can only delete your own devices.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?"); $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        }
        break;

    case 'upload_device_icon':
        if ($user_role !== 'admin') { // Only admin can upload device icons
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin users can upload device icons.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $deviceId = $_POST['id'] ?? null;
            if (!$deviceId || !isset($_FILES['iconFile'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and icon file are required.']);
                exit;
            }
    
            $stmt = $pdo->prepare("SELECT id, user_id FROM devices WHERE id = ?");
            $stmt->execute([$deviceId]);
            $device_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device_data || ($device_data['user_id'] != $current_user_id && $user_role !== 'admin')) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found or access denied.']);
                exit;
            }
    
            $uploadDir = __DIR__ . '/../../uploads/icons/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create upload directory.']);
                    exit;
                }
            }
    
            $file = $_FILES['iconFile'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
    
            $fileInfo = new SplFileInfo($file['name']);
            $extension = strtolower($fileInfo->getExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }

            $newFileName = 'device_' . $deviceId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;
            $urlPath = 'uploads/icons/' . $newFileName;
    
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE devices SET icon_url = ? WHERE id = ?");
                $stmt->execute([$urlPath, $deviceId]);
                echo json_encode(['success' => true, 'url' => $urlPath]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save uploaded file.']);
            }
        }
        break;
}
?>