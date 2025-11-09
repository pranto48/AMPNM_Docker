<?php
require_once 'includes/auth_check.php';
include 'header.php';

$message = '';
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

$pdo = getDbConnection();
$current_user_id = $_SESSION['user_id'];

// Handle delete device action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_device'])) {
    $device_id = (int)$_POST['device_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ? AND user_id = ?");
        $stmt->execute([$device_id, $current_user_id]);
        $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center mb-4">Device deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Error deleting device: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all devices for the current user
$stmt = $pdo->prepare("SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.user_id = ? ORDER BY d.name ASC");
$stmt->execute([$current_user_id]);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Device Inventory</h1>
            <div class="flex items-center gap-2">
                <a href="create-device.php" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700"><i class="fas fa-plus mr-2"></i>Create New Device</a>
            </div>
        </div>

        <?= $message ?>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4">All Devices</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b border-slate-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Device</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Map</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Last Seen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="devicesTableBody">
                        <?php if (empty($devices)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-slate-500">
                                    <i class="fas fa-server text-slate-600 text-4xl mb-4"></i>
                                    <p>No devices found. Create one to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($devices as $device): 
                                $status_class = '';
                                switch ($device['status']) {
                                    case 'online': $status_class = 'bg-green-500/20 text-green-400'; break;
                                    case 'warning': $status_class = 'bg-yellow-500/20 text-yellow-400'; break;
                                    case 'critical': $status_class = 'bg-red-500/20 text-red-400'; break;
                                    case 'offline': $status_class = 'bg-slate-600/50 text-slate-400'; break;
                                    default: $status_class = 'bg-slate-600/50 text-slate-400'; break;
                                }
                                $status_indicator_class = `status-indicator status-{$device['status']}`;
                                $last_seen = $device['last_seen'] ? date('Y-m-d H:i:s', strtotime($device['last_seen'])) : 'Never';
                                $map_link = $device['map_id'] ? htmlspecialchars($device['map_name']) : '<span class="text-slate-500">Unassigned</span>';
                            ?>
                                <tr class="border-b border-slate-700 hover:bg-slate-800/50">
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-white"><?= htmlspecialchars($device['name']) ?></div><div class="text-sm text-slate-400 capitalize"><?= htmlspecialchars($device['type']) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-slate-400 font-mono"><?= htmlspecialchars($device['ip'] ?: 'N/A') ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?= $map_link ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 inline-flex items-center gap-2 text-xs leading-5 font-semibold rounded-full <?= $status_class ?>"><div class="<?= $status_indicator_class ?>"></div><?= htmlspecialchars($device['status']) ?></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400"><?= $last_seen ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" action="devices.php" onsubmit="return confirm('Are you sure you want to delete this device?');" class="inline-block">
                                            <input type="hidden" name="delete_device" value="1">
                                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-400" title="Delete Device"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>