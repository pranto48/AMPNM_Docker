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

switch ($action) {
    case 'get_smtp_settings':
        // Only admin or owner can view/edit SMTP settings
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin users can manage SMTP settings.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT host, port, username, password, encryption, from_email, from_name FROM smtp_settings WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        // Mask password for security, or don't send it at all if not needed by frontend
        if ($settings && isset($settings['password'])) {
            $settings['password'] = '********'; // Mask password
        }
        echo json_encode($settings ?: []);
        break;

    case 'save_smtp_settings':
        // Only admin or owner can view/edit SMTP settings
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin users can manage SMTP settings.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = $input['host'] ?? '';
            $port = $input['port'] ?? '';
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? ''; // This might be masked, handle carefully
            $encryption = $input['encryption'] ?? 'tls';
            $from_email = $input['from_email'] ?? '';
            $from_name = $input['from_name'] ?? null;

            if (empty($host) || empty($port) || empty($username) || empty($from_email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Host, Port, Username, and From Email are required.']);
                exit;
            }

            // Check if settings already exist for this user
            $stmt = $pdo->prepare("SELECT id, password FROM smtp_settings WHERE user_id = ?");
            $stmt->execute([$current_user_id]);
            $existingSettings = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingSettings) {
                // If password is '********', it means it wasn't changed, so keep the old one
                if ($password === '********') {
                    $password = $existingSettings['password'];
                }
                $sql = "UPDATE smtp_settings SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$host, $port, $username, $password, $encryption, $from_email, $from_name, $current_user_id]);
            } else {
                $sql = "INSERT INTO smtp_settings (user_id, host, port, username, password, encryption, from_email, from_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$current_user_id, $host, $port, $username, $password, $encryption, $from_email, $from_name]);
            }
            echo json_encode(['success' => true, 'message' => 'SMTP settings saved successfully.']);
        }
        break;

    case 'get_all_devices_for_subscriptions':
        // Get all devices for the current user, including their map name
        $sql = "SELECT d.id, d.name, d.ip, m.name as map_name, u.role as owner_role FROM devices d LEFT JOIN maps m ON d.map_id = m.id JOIN users u ON d.user_id = u.id WHERE d.user_id = ?";
        $params = [$current_user_id];

        if ($user_role === 'basic') {
            $admin_ids = getAdminUserIds($pdo);
            if (!empty($admin_ids)) {
                $admin_id_placeholders = implode(',', array_fill(0, count($admin_ids), '?'));
                $sql .= " OR d.user_id IN ({$admin_id_placeholders})";
                $params = array_merge($params, $admin_ids);
            }
        }
        $sql .= " ORDER BY d.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'get_device_subscriptions':
        $device_id = $_GET['device_id'] ?? null;
        if (!$device_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID is required.']);
            exit;
        }
        // Check if the user has access to this device
        $sql_check_device_access = "SELECT user_id FROM devices WHERE id = ?";
        $params_check_device_access = [$device_id];
        $stmt_check_device_access = $pdo->prepare($sql_check_device_access);
        $stmt_check_device_access->execute($params_check_device_access);
        $device_owner_id = $stmt_check_device_access->fetchColumn();

        $has_access = false;
        if ($device_owner_id == $current_user_id || $user_role === 'admin') {
            $has_access = true;
        } elseif ($user_role === 'basic') {
            $admin_ids = getAdminUserIds($pdo);
            if (in_array($device_owner_id, $admin_ids)) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: You do not have access to manage subscriptions for this device.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, recipient_email, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical FROM device_email_subscriptions WHERE user_id = ? AND device_id = ? ORDER BY recipient_email ASC");
        $stmt->execute([$current_user_id, $device_id]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($subscriptions);
        break;

    case 'save_device_subscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null; // For updating existing subscription
            $device_id = $input['device_id'] ?? null;
            $recipient_email = $input['recipient_email'] ?? '';
            $notify_on_online = $input['notify_on_online'] ?? false;
            $notify_on_offline = $input['notify_on_offline'] ?? false;
            $notify_on_warning = $input['notify_on_warning'] ?? false;
            $notify_on_critical = $input['notify_on_critical'] ?? false;

            if (!$device_id || empty($recipient_email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and Recipient Email are required.']);
                exit;
            }

            // Check if the user has access to this device
            $sql_check_device_access = "SELECT user_id FROM devices WHERE id = ?";
            $params_check_device_access = [$device_id];
            $stmt_check_device_access = $pdo->prepare($sql_check_device_access);
            $stmt_check_device_access->execute($params_check_device_access);
            $device_owner_id = $stmt_check_device_access->fetchColumn();

            $has_access = false;
            if ($device_owner_id == $current_user_id || $user_role === 'admin') {
                $has_access = true;
            } elseif ($user_role === 'basic') {
                $admin_ids = getAdminUserIds($pdo);
                if (in_array($device_owner_id, $admin_ids)) {
                    $has_access = true;
                }
            }

            if (!$has_access) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You do not have access to manage subscriptions for this device.']);
                exit;
            }

            if ($id) {
                // Update existing subscription
                $sql = "UPDATE device_email_subscriptions SET recipient_email = ?, notify_on_online = ?, notify_on_offline = ?, notify_on_warning = ?, notify_on_critical = ? WHERE id = ? AND user_id = ? AND device_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$recipient_email, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical, $id, $current_user_id, $device_id]);
                echo json_encode(['success' => true, 'message' => 'Subscription updated successfully.']);
            } else {
                // Create new subscription
                $sql = "INSERT INTO device_email_subscriptions (user_id, device_id, recipient_email, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$current_user_id, $device_id, $recipient_email, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical]);
                echo json_encode(['success' => true, 'message' => 'Subscription created successfully.', 'id' => $pdo->lastInsertId()]);
            }
        }
        break;

    case 'delete_device_subscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Subscription ID is required.']);
                exit;
            }
            // Check if the user owns this subscription
            $stmt_check_owner = $pdo->prepare("SELECT user_id FROM device_email_subscriptions WHERE id = ?");
            $stmt_check_owner->execute([$id]);
            $subscription_owner_id = $stmt_check_owner->fetchColumn();

            if ($subscription_owner_id != $current_user_id && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You can only delete your own subscriptions.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM device_email_subscriptions WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $current_user_id]);
            echo json_encode(['success' => true, 'message' => 'Subscription deleted successfully.']);
        }
        break;
}
?>