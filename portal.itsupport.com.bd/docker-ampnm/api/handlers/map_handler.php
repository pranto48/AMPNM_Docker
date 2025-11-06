<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
// For public map access, user_id might not be set, so we check for it.
$current_user_id = $_SESSION['user_id'] ?? null;

error_log("DEBUG: map_handler.php - Action: {$action}, User ID: {$current_user_id}");

switch ($action) {
    case 'get_maps':
        // Only authenticated users can get their list of maps
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - get_maps: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT m.id, m.name, m.type, m.background_color, m.background_image_url, m.updated_at as lastModified, m.share_id, m.is_public FROM maps m WHERE m.user_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$current_user_id]);
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("DEBUG: map_handler.php - get_maps successful. Returned " . count($maps) . " maps.");
        echo json_encode($maps);
        break;

    case 'create_map':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - create_map: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - create_map received. Input: " . print_r($input, true));
            $name = $input['name'] ?? ''; $type = $input['type'] ?? 'lan';
            if (empty($name)) { http_response_code(400); error_log("ERROR: map_handler.php - create_map: Name is required."); echo json_encode(['error' => 'Name is required']); exit; }
            $stmt = $pdo->prepare("INSERT INTO maps (user_id, name, type) VALUES (?, ?, ?)"); $stmt->execute([$current_user_id, $name, $type]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT id, name, type, background_color, background_image_url, updated_at as lastModified, share_id, is_public FROM maps WHERE id = ? AND user_id = ?"); $stmt->execute([$lastId, $current_user_id]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: map_handler.php - create_map successful. New Map ID: {$lastId}.");
            echo json_encode($map);
        }
        break;

    case 'update_map':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - update_map: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - update_map received. Input: " . print_r($input, true));
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); error_log("ERROR: map_handler.php - update_map: Map ID or updates are missing."); echo json_encode(['error' => 'Map ID and updates are required']); exit; }
            
            $allowed_fields = ['name', 'background_color', 'background_image_url'];
            $fields = []; $params = [];
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    $params[] = ($value === '') ? null : $value;
                }
            }

            if (empty($fields)) { http_response_code(400); error_log("ERROR: map_handler.php - update_map: No valid fields to update for Map ID {$id}."); echo json_encode(['error' => 'No valid fields to update']); exit; }
            
            $params[] = $id; $params[] = $current_user_id;
            $sql = "UPDATE maps SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            
            error_log("DEBUG: map_handler.php - update_map successful. Map ID: {$id}.");
            echo json_encode(['success' => true, 'message' => 'Map updated successfully.']);
        }
        break;

    case 'delete_map':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - delete_map: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - delete_map received. Input: " . print_r($input, true));
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); error_log("ERROR: map_handler.php - delete_map: Map ID is required."); echo json_encode(['error' => 'Map ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM maps WHERE id = ? AND user_id = ?"); $stmt->execute([$id, $current_user_id]);
            error_log("DEBUG: map_handler.php - delete_map successful. Map ID: {$id}.");
            echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
        }
        break;
        
    case 'get_edges':
        error_log("DEBUG: map_handler.php - get_edges received. Map ID: {$_GET['map_id'] ?? 'N/A'}, Share ID: {$_GET['share_id'] ?? 'N/A'}. User ID: {$current_user_id}");
        $map_id = $_GET['map_id'] ?? null;
        $share_id = $_GET['share_id'] ?? null; // NEW: Allow fetching by share_id

        if (!$map_id && !$share_id) { http_response_code(400); error_log("ERROR: map_handler.php - get_edges: Map ID or Share ID is required."); echo json_encode(['error' => 'Map ID or Share ID is required']); exit; }

        $sql = "SELECT de.id, de.source_id, de.target_id, de.connection_type FROM device_edges de JOIN maps m ON de.map_id = m.id WHERE 1=1";
        $params = [];

        if ($map_id) {
            if (!$current_user_id) { // If map_id is used, user must be authenticated
                http_response_code(403);
                error_log("ERROR: map_handler.php - get_edges: Unauthorized access for map_id {$map_id}.");
                echo json_encode(['error' => 'Unauthorized access.']);
                exit;
            }
            $sql .= " AND de.map_id = ? AND de.user_id = ?";
            $params = [$map_id, $current_user_id];
        } elseif ($share_id) {
            $sql .= " AND m.share_id = ? AND m.is_public = TRUE";
            $params = [$share_id];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("DEBUG: map_handler.php - get_edges successful. Returned " . count($edges) . " edges.");
        echo json_encode($edges);
        break;

    case 'create_edge':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - create_edge: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - create_edge received. Input: " . print_r($input, true));
            $sql = "INSERT INTO device_edges (user_id, source_id, target_id, map_id, connection_type) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_user_id, $input['source_id'], $input['target_id'], $input['map_id'], $input['connection_type'] ?? 'cat5']);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT id, source_id, target_id, connection_type FROM device_edges WHERE id = ? AND user_id = ?");
            $stmt->execute([$lastId, $current_user_id]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: map_handler.php - create_edge successful. New Edge ID: {$lastId}.");
            echo json_encode($edge);
        }
        break;

    case 'update_edge':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - update_edge: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - update_edge received. Input: " . print_r($input, true));
            $id = $input['id'] ?? null;
            $connection_type = $input['updates']['connection_type'] ?? 'cat5'; // Adjusted for frontend structure
            if (!$id) { http_response_code(400); error_log("ERROR: map_handler.php - update_edge: Edge ID is required."); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("UPDATE device_edges SET connection_type = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$connection_type, $id, $current_user_id]);
            $stmt = $pdo->prepare("SELECT id, source_id, target_id, connection_type FROM device_edges WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $current_user_id]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: map_handler.php - update_edge successful. Edge ID: {$id}.");
            echo json_encode($edge);
        }
        break;

    case 'delete_edge':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - delete_edge: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - delete_edge received. Input: " . print_r($input, true));
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); error_log("ERROR: map_handler.php - delete_edge: Edge ID is required."); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM device_edges WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $current_user_id]);
            error_log("DEBUG: map_handler.php - delete_edge successful. Edge ID: {$id}.");
            echo json_encode(['success' => true]);
        }
        break;
    
    case 'import_map':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - import_map: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - import_map received. Input: " . print_r($input, true));
            $map_id = $input['map_id'] ?? null;
            $devices_data = $input['devices'] ?? [];
            $edges_data = $input['edges'] ?? [];
            if (!$map_id) { http_response_code(400); error_log("ERROR: map_handler.php - import_map: Map ID is required."); echo json_encode(['error' => 'Map ID is required']); exit; }

            try {
                $pdo->beginTransaction();
                // Delete old data for this user and map
                $stmt = $pdo->prepare("DELETE FROM device_edges WHERE map_id = ? AND user_id = ?"); $stmt->execute([$map_id, $current_user_id]);
                $stmt = $pdo->prepare("DELETE FROM devices WHERE map_id = ? AND user_id = ?"); $stmt->execute([$map_id, $current_user_id]);

                // Insert new devices
                $device_id_map = [];
                $sql = "INSERT INTO devices (
                    user_id, name, ip, check_port, type, x, y, map_id, 
                    ping_interval, icon_size, name_text_size, icon_url, 
                    warning_latency_threshold, warning_packetloss_threshold, 
                    critical_latency_threshold, critical_packetloss_threshold, 
                    show_live_ping
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($devices_data as $device) { // Changed from $devices to $devices_data
                    $stmt->execute([
                        $current_user_id,
                        $device['name'] ?? 'Unnamed Device',
                        $device['ip_address'] ?? null, // Changed from 'ip' to 'ip_address'
                        $device['check_port'] ?? null,
                        $device['icon'] ?? 'other', // Changed from 'type' to 'icon'
                        $device['position_x'] ?? null, // Changed from 'x' to 'position_x'
                        $device['position_y'] ?? null, // Changed from 'y' to 'position_y'
                        $map_id,
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
                    $new_id = $pdo->lastInsertId();
                    $device_id_map[$device['id']] = $new_id;
                }

                // Insert new edges
                $sql = "INSERT INTO device_edges (user_id, source_id, target_id, map_id, connection_type) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($edges_data as $edge) { // Changed from $edges to $edges_data
                    $new_source_id = $device_id_map[$edge['source']] ?? null; // Changed from 'from' to 'source'
                    $new_target_id = $device_id_map[$edge['target']] ?? null; // Changed from 'to' to 'target'
                    if ($new_source_id && $new_target_id) {
                        $stmt->execute([$current_user_id, $new_source_id, $new_target_id, $map_id, $edge['connection_type'] ?? 'cat5']);
                    }
                }
                $pdo->commit();
                error_log("DEBUG: map_handler.php - import_map successful. Map ID: {$map_id}.");
                echo json_encode(['success' => true, 'message' => 'Map imported successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                error_log("ERROR: map_handler.php - import_map failed: " . $e->getMessage());
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;
    
    case 'upload_map_background':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - upload_map_background: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - upload_map_background received. Map ID: {$_POST['map_id'] ?? 'N/A'}.");
            $mapId = $_POST['map_id'] ?? null;
            if (!$mapId || !isset($_FILES['backgroundFile'])) {
                http_response_code(400);
                error_log("ERROR: map_handler.php - upload_map_background: Missing Map ID or background file.");
                echo json_encode(['error' => 'Map ID and background file are required.']);
                exit;
            }
    
            $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id = ?");
            $stmt->execute([$mapId, $current_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                error_log("ERROR: map_handler.php - upload_map_background: Map ID {$mapId} not found for user {$current_user_id}.");
                echo json_encode(['error' => 'Map not found or access denied.']);
                exit;
            }
    
            $uploadDir = __DIR__ . '/../../uploads/map_backgrounds/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    error_log("ERROR: map_handler.php - upload_map_background: Failed to create upload directory: {$uploadDir}.");
                    echo json_encode(['error' => 'Failed to create upload directory.']);
                    exit;
                }
            }
    
            $file = $_FILES['backgroundFile'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                error_log("ERROR: map_handler.php - upload_map_background: File upload error code: " . $file['error'] . " for Map ID {$mapId}.");
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
    
            $fileInfo = new SplFileInfo($file['name']);
            $extension = strtolower($fileInfo->getExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                error_log("ERROR: map_handler.php - upload_map_background: Invalid file type '{$extension}' for Map ID {$mapId}.");
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }

            $newFileName = 'map_' . $mapId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;
            $urlPath = 'uploads/map_backgrounds/' . $newFileName;
    
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE maps SET background_image_url = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$urlPath, $mapId, $current_user_id]);
                error_log("DEBUG: map_handler.php - upload_map_background successful. Map ID {$mapId}, URL: {$urlPath}.");
                echo json_encode(['success' => true, 'url' => $urlPath]);
            } else {
                http_response_code(500);
                error_log("ERROR: map_handler.php - upload_map_background: Failed to save uploaded file to {$uploadPath} for Map ID {$mapId}.");
                echo json_encode(['error' => 'Failed to save uploaded file.']);
            }
        }
        break;

    case 'generate_share_link':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - generate_share_link: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - generate_share_link received. Input: " . print_r($input, true));
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); error_log("ERROR: map_handler.php - generate_share_link: Map ID is required."); echo json_encode(['error' => 'Map ID is required']); exit; }

            // Check if map belongs to user
            $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id = ?");
            $stmt->execute([$map_id, $current_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                error_log("ERROR: map_handler.php - generate_share_link: Map ID {$map_id} not found for user {$current_user_id}.");
                echo json_encode(['error' => 'Map not found or access denied.']);
                exit;
            }

            // Generate a UUID for the share_id
            // Assuming generateUuid() is available from includes/functions.php or similar
            require_once __DIR__ . '/../includes/functions.php'; // Ensure generateUuid is available
            $share_id = generateUuid(); 
            $stmt = $pdo->prepare("UPDATE maps SET share_id = ?, is_public = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([$share_id, $map_id, $current_user_id]);
            error_log("DEBUG: map_handler.php - generate_share_link successful. Map ID {$map_id}, Share ID: {$share_id}.");
            echo json_encode(['success' => true, 'share_id' => $share_id, 'message' => 'Share link generated.']);
        }
        break;

    case 'disable_share_link':
        if (!$current_user_id) {
            http_response_code(403);
            error_log("ERROR: map_handler.php - disable_share_link: Unauthorized access.");
            echo json_encode(['error' => 'Unauthorized access.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: map_handler.php - disable_share_link received. Input: " . print_r($input, true));
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); error_log("ERROR: map_handler.php - disable_share_link: Map ID is required."); echo json_encode(['error' => 'Map ID is required']); exit; }

            // Check if map belongs to user
            $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id = ?");
            $stmt->execute([$map_id, $current_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                error_log("ERROR: map_handler.php - disable_share_link: Map ID {$map_id} not found for user {$current_user_id}.");
                echo json_encode(['error' => 'Map not found or access denied.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE maps SET share_id = NULL, is_public = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([$map_id, $current_user_id]);
            error_log("DEBUG: map_handler.php - disable_share_link successful. Map ID: {$map_id}.");
            echo json_encode(['success' => true, 'message' => 'Share link disabled.']);
        }
        break;

    case 'get_map_by_share_id':
        error_log("DEBUG: map_handler.php - get_map_by_share_id received. Share ID: {$_GET['share_id'] ?? 'N/A'}.");
        $share_id = $_GET['share_id'] ?? null;
        if (empty($share_id)) {
            http_response_code(400);
            error_log("ERROR: map_handler.php - get_map_by_share_id: Share ID is required.");
            echo json_encode(['error' => 'Share ID is required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, background_color, background_image_url, share_id, is_public FROM maps WHERE share_id = ? AND is_public = TRUE");
        $stmt->execute([$share_id]);
        $map = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$map) {
            http_response_code(404);
            error_log("ERROR: map_handler.php - get_map_by_share_id: Public map not found or invalid share_id {$share_id}.");
            echo json_encode(['error' => 'Public map not found or share link is invalid/disabled.']);
            exit;
        }
        error_log("DEBUG: map_handler.php - get_map_by_share_id successful. Map ID: {$map['id']}.");
        echo json_encode($map);
        break;
}