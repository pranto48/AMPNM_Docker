<?php
// Database configuration using environment variables for Docker compatibility
// Forcing 127.0.0.1 as the host to resolve connection issues in the Docker environment.
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'network_monitor');

// License System Configuration
// IMPORTANT: Changed fallback to the correct public HTTPS URL.
define('LICENSE_API_URL', getenv('LICENSE_API_URL') ?: 'https://portal.itsupport.com.bd/verify_license.php'); 
define('APP_LICENSE_KEY_ENV', getenv('APP_LICENSE_KEY') ?: ''); // This is the key from docker-compose.yml, might be empty

// Create database connection
function getDbConnection() {
    static $pdo = null;

    // If a connection exists, check if it's still alive.
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
        } catch (PDOException $e) {
            // Error code 2006 is "MySQL server has gone away".
            // If that's the case, nullify the connection to force a reconnect.
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 2006) {
                $pdo = null;
            } else {
                // For other errors, we can re-throw them.
                throw $e;
            }
        }
    }

    // If no connection exists (or it was lost), create a new one.
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch(PDOException $e) {
            // For a real application, you would log this error and show a generic message.
            // For this local tool, dying is acceptable to immediately see the problem.
            die("ERROR: Could not connect to the database. " . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Retrieves a setting from the app_settings table.
 * @param string $key The key of the setting to retrieve.
 * @return string|null The setting value, or null if not found.
 */
function getAppSetting($key) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        error_log("Error getting app setting '$key': " . $e->getMessage());
        return null;
    }
}

/**
 * Updates or inserts a setting into the app_settings table.
 * @param string $key The key of the setting.
 * @param string $value The new value for the setting.
 * @return bool True on success, false on failure.
 */
function updateAppSetting($key, $value) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating app setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to get the application's license key.
 * @return string|null The license key, or null if not set.
 */
function getAppLicenseKey() {
    return getAppSetting('app_license_key');
}

/**
 * Helper function to set the application's license key.
 * @param string $key The license key to set.
 * @return bool True on success, false on failure.
 */
function setAppLicenseKey($key) {
    return updateAppSetting('app_license_key', $key);
}

/**
 * Helper function to get the application's unique installation ID.
 * @return string|null The installation ID, or null if not set.
 */
function getInstallationId() {
    return getAppSetting('installation_id');
}