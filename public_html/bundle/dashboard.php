<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Handle delete action (POST for safety)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    // delete bundle items first due to FK
    $stmt = $mysqli->prepare("DELETE FROM bundle_items WHERE bundle_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("DELETE FROM bundles WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: dashboard.php?msg=Deleted');
    exit;
}

// Fetch bundles
$bundles = [];
$res = $mysqli->query("SELECT id, sku, name FROM bundles ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) $bundles[] = $row;
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bundles</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="bg">
    <div class="topbar">
        <div class="topbar-left"><strong>Bundles</strong></div>
        <div class="topbar-right">
            <a class="btn" href="add_edit_bundle.php">Add Bundle</a>
            <a class="btn btn-ghost" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th class="right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bundles)): ?>
                <tr><td colspan="3" class="muted">No bundles yet.</td></tr>
                <?php else: foreach ($bundles as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['sku']) ?></td>
                    <td><?= htmlspecialchars($b['name']) ?></td>
                    <td class="right">
                        <a class="btn btn-primary btn-sm" href="add_edit_bundle.php?id=<?= $b['id'] ?>">Edit</a>
                        <form method="post" action="dashboard.php" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this bundle?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
