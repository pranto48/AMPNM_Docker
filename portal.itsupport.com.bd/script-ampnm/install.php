<?php
require_once __DIR__ . '/includes/state.php';
require_once __DIR__ . '/includes/db.php';

if (ampnm_setup_complete()) {
    header('Location: ' . ampnm_base_url() . '/license_setup.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = $_POST['admin_pass'] ?? '';
    $appUrl = trim($_POST['app_url'] ?? '/script-ampnm');

    if (!$dbHost || !$dbName || !$dbUser || !$adminName || !$adminEmail || !$adminPass) {
        $error = 'All fields are required.';
    } else {
        try {
            $config = [
                'app_name' => 'AMPNM PHP',
                'app_url' => $appUrl ?: '/script-ampnm',
                'db' => [
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'password' => $dbPass,
                    'charset' => 'utf8mb4',
                ],
                'license' => [
                    'status' => 'missing',
                ],
                'setup_complete' => false,
            ];

            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
            $pdo->exec('USE `' . str_replace('`', '``', $dbName) . '`;');

            ampnm_bootstrap_schema($pdo);

            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)');
            $stmt->execute([
                ':name' => $adminName,
                ':email' => $adminEmail,
                ':password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
                ':role' => 'admin',
            ]);

            $config['setup_complete'] = true;
            ampnm_config_save($config);
            $success = 'Database configured and admin user created. Continue to license activation.';
            header('Refresh: 2; URL=' . ampnm_base_url() . '/license_setup.php');
        } catch (Throwable $e) {
            $error = 'Setup failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM Setup</title>
    <link rel="stylesheet" href="./assets/style.css">
</head>
<body class="setup-shell">
    <div class="setup-card">
        <p class="badge">Step 1 of 2</p>
        <h1>Install AMPNM PHP</h1>
        <p class="muted">Provide your MySQL and admin details. We will create the database, tables, and first administrator.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post" class="grid two" style="gap:12px;">
            <div class="form-group">
                <label class="label">Base URL</label>
                <input class="input" name="app_url" value="<?php echo htmlspecialchars($_POST['app_url'] ?? '/script-ampnm'); ?>" placeholder="/script-ampnm">
            </div>
            <div class="form-group">
                <label class="label">DB Host</label>
                <input class="input" name="db_host" required value="<?php echo htmlspecialchars($_POST['db_host'] ?? '127.0.0.1'); ?>">
            </div>
            <div class="form-group">
                <label class="label">DB Port</label>
                <input class="input" name="db_port" type="number" required value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>">
            </div>
            <div class="form-group">
                <label class="label">DB Name</label>
                <input class="input" name="db_name" required value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'ampnm'); ?>">
            </div>
            <div class="form-group">
                <label class="label">DB User</label>
                <input class="input" name="db_user" required value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>">
            </div>
            <div class="form-group">
                <label class="label">DB Password</label>
                <input class="input" name="db_pass" type="password" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
            </div>
            <div class="form-group" style="grid-column:1 / -1; margin-top:8px;">
                <p class="eyebrow">Admin user</p>
            </div>
            <div class="form-group">
                <label class="label">Name</label>
                <input class="input" name="admin_name" required value="<?php echo htmlspecialchars($_POST['admin_name'] ?? 'Portal Admin'); ?>">
            </div>
            <div class="form-group">
                <label class="label">Email</label>
                <input class="input" name="admin_email" type="email" required value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@example.com'); ?>">
            </div>
            <div class="form-group" style="grid-column:1 / -1;">
                <label class="label">Password</label>
                <input class="input" name="admin_pass" type="password" required>
            </div>
            <button class="btn" type="submit">Create database &amp; admin</button>
        </form>
    </div>
</body>
</html>
