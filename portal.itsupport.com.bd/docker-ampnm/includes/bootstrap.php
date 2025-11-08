<?php
// This is the new central bootstrap file.
// It handles basic setup like loading functions and checking database integrity.

require_once __DIR__ . '/functions.php';

// This script should not run on the setup page itself to avoid a redirect loop.
if (basename($_SERVER['PHP_SELF']) !== 'database_setup.php') {
    try {
        $pdo = getDbConnection();
        // A simple query to check if the main 'users' table exists.
        // If this fails, we assume the database has not been initialized.
        $pdo->query("SELECT 1 FROM `users` LIMIT 1");
    } catch (PDOException $e) {
        // Check for the specific "table not found" error.
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            // The database is connected, but tables are missing. Redirect to setup.
            header('Location: database_setup.php');
            exit;
        } else {
            // A different, more serious database error occurred.
            die("A critical database error occurred: " . $e->getMessage());
        }
    }
}

// Start session management after DB check.
// This ensures sessions are available on all pages that include this bootstrap.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch user role if logged in
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT role FROM `users` WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_role'] = $user_data['role'] ?? 'basic'; // Default to basic if not found
    } catch (PDOException $e) {
        error_log("Error fetching user role: " . $e->getMessage());
        $_SESSION['user_role'] = 'basic'; // Fallback
    }
} else {
    $_SESSION['user_role'] = 'guest'; // Not logged in
}
?>