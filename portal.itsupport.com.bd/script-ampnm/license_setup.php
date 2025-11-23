<?php
require_once __DIR__ . '/includes/state.php';
require_once __DIR__ . '/includes/db.php';

if (!ampnm_setup_complete()) {
    header('Location: ' . ampnm_base_url() . '/install.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $licenseKey = trim($_POST['license_key'] ?? '');
    $expiresAt = trim($_POST['expires_at'] ?? '');
    $host = ampnm_normalize_host();

    if (!ampnm_validate_license_key($licenseKey)) {
        $error = 'License format is invalid. Use AMP-XXXX-XXXX-XXXX.';
    } else {
        $config['license'] = [
            'key' => $licenseKey,
            'status' => 'active',
            'bound_host' => $host,
            'expires_at' => $expiresAt ?: null,
            'last_verified' => date('c'),
        ];
        try {
            ampnm_config_save($config);
            $pdo = ampnm_pdo();
            $stmt = $pdo->prepare('INSERT INTO licenses (license_key, status, expires_at, bound_host) VALUES (:key, :status, :expires, :host)');
            $stmt->execute([
                ':key' => $licenseKey,
                ':status' => 'active',
                ':expires' => $expiresAt ?: null,
                ':host' => $host,
            ]);
            $success = 'License activated. You can now log in.';
            header('Refresh: 2; URL=' . ampnm_base_url() . '/login.php');
        } catch (Throwable $e) {
            $error = 'Could not persist license: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate License</title>
    <link rel="stylesheet" href="./assets/style.css">
</head>
<body class="setup-shell">
    <div class="setup-card">
        <p class="badge">Step 2 of 2</p>
        <h1>License required</h1>
        <p class="muted">Enter your AMPNM license to unlock the portal. Keys are bound to host <strong><?php echo htmlspecialchars(ampnm_normalize_host()); ?></strong>.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post" class="grid two" style="gap:12px;">
            <div class="form-group" style="grid-column:1 / -1;">
                <label class="label">License key</label>
                <input class="input" name="license_key" required placeholder="AMP-1234-ABCD-5678" value="<?php echo htmlspecialchars($_POST['license_key'] ?? ''); ?>">
            </div>
            <div class="form-group" style="grid-column:1 / -1;">
                <label class="label">Expiration (optional)</label>
                <input class="input" name="expires_at" type="date" value="<?php echo htmlspecialchars($_POST['expires_at'] ?? ''); ?>">
            </div>
            <button class="btn" type="submit">Activate license</button>
        </form>
    </div>
</body>
</html>
