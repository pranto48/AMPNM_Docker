<?php
// This file is included by auth_check.php and assumes session is started and config.php is loaded.

// Define how often to re-verify the license with the portal (in seconds)
define('LICENSE_VERIFICATION_INTERVAL', 300); // 5 minutes
define('LICENSE_GRACE_PERIOD_DAYS', 7); // 7 days grace period after expiry

// --- Encryption/Decryption Configuration ---
// NOTE: This key MUST match the key used in the portal's verify_license.php
define('ENCRYPTION_KEY', 'ITSupportBD_SecureKey_2024');
define('CIPHER_METHOD', 'aes-256-cbc');

function decryptLicenseData(string $encrypted_data) {
    $data = base64_decode($encrypted_data);
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);

    if (strlen($data) < $iv_length) {
        error_log("DECRYPT_ERROR: Encrypted data too short.");
        return false;
    }

    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    $decrypted = openssl_decrypt($encrypted, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);

    if ($decrypted === false) {
        error_log("DECRYPT_ERROR: Decryption failed. Key mismatch or corrupted data.");
        return false;
    }

    $result = json_decode($decrypted, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("DECRYPT_ERROR: JSON decoding failed after decryption: " . json_last_error_msg());
        return false;
    }
    return $result;
}
// --- End Encryption/Decryption Configuration ---

// Function to generate a UUID (Universally Unique Identifier)
function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord(ord($data[8]) & 0x3f | 0x80)); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Performs the actual license verification with the portal API using file_get_contents.
 * Caches results in session.
 */
function verifyLicenseWithPortal() {
    // Initialize session variables if they don't exist
    if (!isset($_SESSION['license_status_code'])) $_SESSION['license_status_code'] = 'unknown';
    if (!isset($_SESSION['license_message'])) $_SESSION['license_message'] = 'License status unknown.';
    if (!isset($_SESSION['license_max_devices'])) $_SESSION['license_max_devices'] = 0;
    if (!isset($_SESSION['license_expires_at'])) $_SESSION['license_expires_at'] = null;
    if (!isset($_SESSION['current_device_count'])) $_SESSION['current_device_count'] = 0;
    if (!isset($_SESSION['license_grace_period_end'])) $_SESSION['license_grace_period_end'] = null;


    if (isset($_SESSION['license_last_verified']) && (time() - $_SESSION['license_last_verified'] < LICENSE_VERIFICATION_INTERVAL)) {
        return; // Use cached data
    }

    $app_license_key = getAppSetting('app_license_key');
    $installation_id = getAppSetting('installation_id');
    $user_id = $_SESSION['user_id'] ?? 'anonymous'; // Use 'anonymous' if not logged in for initial checks

    if (empty($app_license_key)) {
        $_SESSION['license_status_code'] = 'unconfigured';
        $_SESSION['license_message'] = 'Application license key is missing.';
        $_SESSION['license_max_devices'] = 0;
        $_SESSION['license_expires_at'] = null;
        $_SESSION['license_last_verified'] = time();
        return;
    }
    if (empty($installation_id)) {
        $_SESSION['license_status_code'] = 'unconfigured'; // Or a more specific 'installation_id_missing'
        $_SESSION['license_message'] = 'Application installation ID is missing. Please re-run database setup.';
        $_SESSION['license_max_devices'] = 0;
        $_SESSION['license_expires_at'] = null;
        $_SESSION['license_last_verified'] = time();
        return;
    }

    // Get current device count for the logged-in user
    $current_device_count = 0;
    if (isset($_SESSION['user_id'])) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `devices` WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_device_count = $stmt->fetchColumn();
    }
    $_SESSION['current_device_count'] = $current_device_count;


    $post_data = [
        'app_license_key' => $app_license_key,
        'user_id' => $user_id,
        'current_device_count' => $current_device_count,
        'installation_id' => $installation_id
    ];

    $license_api_url = LICENSE_API_URL;

    // --- Use stream context for POST request with file_get_contents ---
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($post_data),
            'timeout' => 10, // 10 second timeout
            // NOTE: We rely on the Docker environment's PHP configuration for SSL/TLS.
            // If this fails, it's a fundamental network/DNS issue.
        ],
        // Temporarily disable SSL verification for stream context if needed for debugging
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);

    $encrypted_response = @file_get_contents($license_api_url, false, $context);

    if ($encrypted_response === false) {
        $error = error_get_last();
        $error_message = $error['message'] ?? 'Unknown connection error.';
        error_log("LICENSE_ERROR: License server unreachable via file_get_contents. Error: {$error_message}");
        $_SESSION['license_status_code'] = 'portal_unreachable';
        $_SESSION['license_message'] = "Could not connect to license server. Network/DNS error: {$error_message}.";
        $_SESSION['license_max_devices'] = 0;
        $_SESSION['license_expires_at'] = null;
        $_SESSION['license_last_verified'] = time();
        return;
    }

    $result = decryptLicenseData($encrypted_response);

    if ($result === false) {
        error_log("LICENSE_ERROR: Failed to decrypt or parse license response.");
        $_SESSION['license_status_code'] = 'error';
        $_SESSION['license_message'] = 'Failed to decrypt license response. Key mismatch or corrupted data.';
        $_SESSION['license_max_devices'] = 0;
        $_SESSION['license_expires_at'] = null;
        $_SESSION['license_last_verified'] = time();
        return;
    }

    // Update session with actual license data
    $_SESSION['license_status_code'] = $result['actual_status'] ?? 'invalid';
    $_SESSION['license_message'] = $result['message'] ?? 'License is invalid.';
    $_SESSION['license_max_devices'] = $result['max_devices'] ?? 0;
    $_SESSION['license_expires_at'] = $result['expires_at'] ?? null;

    // Handle grace period for expired licenses
    if ($_SESSION['license_status_code'] === 'expired' && $_SESSION['license_expires_at']) {
        $expiry_timestamp = strtotime($_SESSION['license_expires_at']);
        $grace_period_end = $expiry_timestamp + (LICENSE_GRACE_PERIOD_DAYS * 24 * 60 * 60);
        $_SESSION['license_grace_period_end'] = $grace_period_end;

        if (time() < $grace_period_end) {
            $_SESSION['license_status_code'] = 'grace_period';
            $_SESSION['license_message'] = 'Your license has expired. You are in a grace period until ' . date('Y-m-d H:i', $grace_period_end) . '. Please renew your license.';
        } else {
            // Grace period over, mark as disabled
            $_SESSION['license_status_code'] = 'disabled';
            $_SESSION['license_message'] = 'Your license has expired and the grace period has ended. The application is now disabled.';
        }
    } else {
        $_SESSION['license_grace_period_end'] = null; // Clear grace period if not expired
    }

    error_log("LICENSE_INFO: License verification completed. Status: {$_SESSION['license_status_code']}. Message: {$_SESSION['license_message']}. Max Devices: {$_SESSION['license_max_devices']}. Expires: {$_SESSION['license_expires_at']}");
    $_SESSION['license_last_verified'] = time();
}

// --- Main License Manager Logic ---

// 1. Ensure installation_id exists
$installation_id = getAppSetting('installation_id');
if (empty($installation_id)) {
    $new_uuid = generateUuid();
    updateAppSetting('installation_id', $new_uuid);
    // Reload to ensure it's set for this request
    $installation_id = $new_uuid;
}

// 2. Check if license key is configured
$app_license_key = getAppSetting('app_license_key');

// If license key is not set, verifyLicenseWithPortal will set status to 'unconfigured'
// If license key is set, verify it
verifyLicenseWithPortal();

// Store current device count in session for easy access
if (isset($_SESSION['user_id'])) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `devices` WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['current_device_count'] = $stmt->fetchColumn();
} else {
    $_SESSION['current_device_count'] = 0;
}

?>