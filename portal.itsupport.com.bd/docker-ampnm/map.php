<?php
require_once 'includes/auth_check.php';
include 'header.php';

// Get user role from session for conditional rendering
$user_role = $_SESSION['user_role'] ?? 'viewer';
$is_admin = ($user_role === 'admin');
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <!-- The React NetworkMap component will now render all map-related UI and controls -->
        <div id="network-map-root"></div>
    </div>
</main>

<?php include 'footer.php'; ?>