<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

$sku = '';
$name = '';
$items = [];

if ($editing) {
    $stmt = $mysqli->prepare("SELECT sku, name FROM bundles WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($sku, $name);
    if (!$stmt->fetch()) { $editing = false; }
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT id, product_sku, product_name, quantity, price FROM bundle_items WHERE bundle_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $items[] = $row;
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['bundle_sku'] ?? '');
    $name = trim($_POST['bundle_name'] ?? '');

    $product_sku  = $_POST['product_sku'] ?? [];
    $product_name = $_POST['product_name'] ?? [];
    $quantity     = $_POST['quantity'] ?? [];
    $price        = $_POST['price'] ?? [];

    if ($editing) {
        $stmt = $mysqli->prepare("UPDATE bundles SET sku = ?, name = ? WHERE id = ?");
        $stmt->bind_param('ssi', $sku, $name, $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("DELETE FROM bundle_items WHERE bundle_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO bundle_items (bundle_id, product_sku, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
        for ($i=0; $i<count($product_sku); $i++) {
            $psku = trim($product_sku[$i]);
            $pname = trim($product_name[$i]);
            $qty = (int)($quantity[$i] ?? 0);
            $pr = (float)($price[$i] ?? 0);
            if ($psku !== '' && $qty > 0) {
                $stmt->bind_param('issid', $id, $psku, $pname, $qty, $pr);
                $stmt->execute();
            }
        }
        $stmt->close();

        header('Location: dashboard.php?msg=Updated');
        exit;
    } else {
        $stmt = $mysqli->prepare("INSERT INTO bundles (sku, name) VALUES (?, ?)");
        $stmt->bind_param('ss', $sku, $name);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO bundle_items (bundle_id, product_sku, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
        for ($i=0; $i<count($product_sku); $i++) {
            $psku = trim($product_sku[$i]);
            $pname = trim($product_name[$i]);
            $qty = (int)($quantity[$i] ?? 0);
            $pr = (float)($price[$i] ?? 0);
            if ($psku !== '' && $qty > 0) {
                $stmt->bind_param('issid', $new_id, $psku, $pname, $qty, $pr);
                $stmt->execute();
            }
        }
        $stmt->close();

        header('Location: dashboard.php?msg=Added');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $editing ? 'Edit Bundle' : 'Add Bundle' ?></title>
<link rel="stylesheet" href="style.css">
<script>
function addRow() {
    const container = document.getElementById('items');
    const row = document.createElement('div');
    row.className = 'grid grid-4 gap';
    row.innerHTML = `
        <input type="text" name="product_sku[]" placeholder="SKU">
        <input type="text" name="product_name[]" placeholder="Product Name">
        <input type="number" name="quantity[]" placeholder="Quantity" min="1" step="1">
        <input type="number" name="price[]" placeholder="Price" min="0" step="0.01">
    `;
    container.appendChild(row);
}
</script>
</head>
<body class="bg">
    <div class="topbar">
        <div class="topbar-left"><strong><?= $editing ? 'Edit Bundle' : 'Add Bundle' ?></strong></div>
        <div class="topbar-right">
            <a class="btn btn-ghost" href="dashboard.php">Back</a>
            <a class="btn btn-ghost" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="card">
        <form method="post" class="form">
            <label>Bundle SKU
                <input type="text" name="bundle_sku" value="<?= htmlspecialchars($sku) ?>" required>
            </label>
            <label>Bundle Name
                <input type="text" name="bundle_name" value="<?= htmlspecialchars($name) ?>" required>
            </label>

            <div class="section-title">Products</div>
            <div id="items">
                <?php if ($editing && !empty($items)): ?>
                    <?php foreach ($items as $it): ?>
                        <div class="grid grid-4 gap">
                            <input type="text" name="product_sku[]" value="<?= htmlspecialchars($it['product_sku']) ?>" placeholder="SKU">
                            <input type="text" name="product_name[]" value="<?= htmlspecialchars($it['product_name']) ?>" placeholder="Product Name">
                            <input type="number" name="quantity[]" value="<?= (int)$it['quantity'] ?>" placeholder="Quantity" min="1" step="1">
                            <input type="number" name="price[]" value="<?= number_format((float)$it['price'], 2, '.', '') ?>" placeholder="Price" min="0" step="0.01">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="grid grid-4 gap">
                        <input type="text" name="product_sku[]" placeholder="SKU">
                        <input type="text" name="product_name[]" placeholder="Product Name">
                        <input type="number" name="quantity[]" placeholder="Quantity" min="1" step="1">
                        <input type="number" name="price[]" placeholder="Price" min="0" step="0.01">
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" class="btn" onclick="addRow()">Add Product</button>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Update Bundle' : 'Save Bundle' ?></button>
        </form>
    </div>
</body>
</html>
