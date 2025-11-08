<?php
// This file is included by api.php for publicly accessible actions.
// It assumes $pdo, $action, and $input are available.
// NO AUTHENTICATION REQUIRED HERE.

// Ensure functions for pinging are available
require_once __DIR__ . '/../../includes/functions.php';

// Re-define helper functions from device_handler.php that are needed here
// (These are not in functions.php because they use $_SESSION or specific logic)
function getStatusFromPingResultPublic($device, $pingResult, $parsedResult, &$details) {
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

function logStatusChangePublic($pdo, $deviceId, $oldStatus, $newStatus, $details) {
    if ($oldStatus !== $newStatus) {
        $stmt = $pdo->prepare("INSERT INTO device_status_logs (device_id, status, details) VALUES (?, ?, ?)");
        $stmt->execute([$deviceId, $newStatus, $details]);
    }
}

switch ($action) {
    case 'check_device_status':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        
        // Fetch device details (no user_id check needed for public status)
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found']); exit; }

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
                savePingResult($pdo, $device['ip'], $pingResult); // Save to ping_results table
                $parsedResult = parsePingOutput($pingResult['output']);
                $status = getStatusFromPingResultPublic($device, $pingResult, $parsedResult, $details);
                $last_avg_time = $parsedResult['avg_time'] ?? null;
                $last_ttl = $parsedResult['ttl'] ?? null;
                $check_output = $pingResult['output'];
            }
        }
        
        if ($status !== 'offline') { $last_seen = date('Y-m-d H:i:s'); }
        
        logStatusChangePublic($pdo, $deviceId, $old_status, $status, $details);
        // Update device status in DB (important for persistent status)
        $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?");
        $stmt->execute([$status, $last_seen, $last_avg_time, $last_ttl, $deviceId]);
        
        echo json_encode(['id' => $deviceId, 'status' => $status, 'last_seen' => $last_seen, 'last_avg_time' => $last_avg_time, 'last_ttl' => $last_ttl, 'last_ping_output' => $check_output]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid public API action.']);
        break;
}