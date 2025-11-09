<?php
require_once __DIR__ . '/../config.php';

// Function to check a TCP port on a host
function checkPortStatus($host, $port, $timeout = 1) {
    $startTime = microtime(true);
    // The '@' suppresses warnings on connection failure, which we handle ourselves.
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $endTime = microtime(true);

    if ($socket) {
        fclose($socket);
        return [
            'success' => true,
            'time' => round(($endTime - $startTime) * 1000, 2), // time in ms
            'output' => "Successfully connected to $host on port $port."
        ];
    } else {
        return [
            'success' => false,
            'time' => 0,
            'output' => "Connection failed: $errstr (Error no: $errno)"
        ];
    }
}

// Function to execute ping command more efficiently
function executePing($host, $count = 4) {
    // Basic validation and sanitization for the host
    if (empty($host) || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $host)) {
        return ['output' => 'Invalid host provided.', 'return_code' => -1, 'success' => false];
    }
    
    // Escape the host to prevent command injection
    $escaped_host = escapeshellarg($host);
    
    // Determine the correct ping command based on the OS, with timeouts
    if (stristr(PHP_OS, 'WIN')) {
        // Windows: -n for count, -w for timeout in ms
        $command = "ping -n $count -w 1000 $escaped_host";
    } else {
        // Linux/Mac: -c for count, -W for timeout in seconds
        $command = "ping -c $count -W 1 $escaped_host";
    }
    
    $output_array = [];
    $return_code = -1;
    
    // Use exec to get both output and return code in one call
    @exec($command . ' 2>&1', $output_array, $return_code);
    
    $output = implode("\n", $output_array);
    
    // Determine success more reliably. Return code 0 is good, but we also check for 100% packet loss.
    $success = ($return_code === 0 && strpos($output, '100% packet loss') === false && strpos($output, 'Lost = ' . $count) === false);

    return [
        'output' => $output,
        'return_code' => $return_code,
        'success' => $success
    ];
}

// Function to parse ping output from different OS
function parsePingOutput($output) {
    $packetLoss = 100;
    $avgTime = 0;
    $minTime = 0;
    $maxTime = 0;
    $ttl = null;
    
    // Regex for Windows
    if (preg_match('/Lost = \d+ \((\d+)% loss\)/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $maxTime = (float)$matches[2];
        $avgTime = (float)$matches[3];
    }
    if (preg_match('/TTL=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    // Regex for Linux/Mac
    if (preg_match('/(\d+)% packet loss/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $avgTime = (float)$matches[2];
        $maxTime = (float)$matches[3];
    }
    if (preg_match('/ttl=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    return [
        'packet_loss' => $packetLoss,
        'avg_time' => $avgTime,
        'min_time' => $minTime,
        'max_time' => $maxTime,
        'ttl' => $ttl
    ];
}

// Function to save a ping result to the database
function savePingResult($pdo, $host, $pingResult) {
    $parsed = parsePingOutput($pingResult['output']);
    $success = $pingResult['success'];

    $sql = "INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $host,
        $parsed['packet_loss'],
        $parsed['avg_time'],
        $parsed['min_time'],
        $parsed['max_time'],
        $success,
        $pingResult['output']
    ]);
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