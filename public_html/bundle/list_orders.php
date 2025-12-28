<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

$sku = '';
$name = '';
$preset = 0; // default unchecked
$items = [];
$url = 

$editing = true;



    $stmt = $mysqli->prepare("SELECT order_number, status, PDF_URL, carrier, tracking_number FROM shopify_orders ORDER BY order_number DESC");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $items[] = $row;
    $stmt->close();


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $editing ? 'Order List' : 'Add Bundle' ?></title>
<link rel="stylesheet" href="style.css">
<script>
function addRow() {
    const container = document.getElementById('items');
    const row = document.createElement('div');
    row.className = 'grid grid-4 gap';
    row.innerHTML = `
        <input type="text" name="order_number[]" placeholder="SKU">
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
        <div class="topbar-left"><strong><?= $editing ? 'Order List' : 'Order List' ?></strong></div>
        <div class="topbar-right">
            <a class="btn btn-ghost" href="dashboard.php">Back</a>
            <a class="btn" 
               href="https://www.google.com" 
               onclick="return confirm('Are you sure you want to fulfill orders?');"
               target="_blank" 
               rel="noopener noreferrer">
               Fulfill Orders
            </a>
            <a class="btn btn-ghost" href="logout.php">Logout</a>
        </div>
    </div>

    
    <div class="card">
    

    <!-- Filters -->
 

    <table id="orders-table" class="table">
    <thead>
        <tr>
            <th>Order #<br><br>
                <input type="text" class="col-filter" data-col="0" placeholder="Filter…">
            </th>
            <th>Status<br><br>
                <input type="text" class="col-filter" data-col="1" placeholder="Filter…">
            </th>
            <th>Carrier<br><br>
                <input type="text" class="col-filter" data-col="2" placeholder="Filter…">
            </th>
            <th>Tracking #<br><br>
                <input type="text" class="col-filter" data-col="3" placeholder="Filter…">
            </th>
            <th class="right">PDF</th>
        </tr>
    </thead>

    <tbody>
        <?php if ($editing && !empty($items)): ?>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['order_number']) ?></td>
                <td><?= htmlspecialchars($it['status']) ?></td>
                <td><?= htmlspecialchars($it['carrier']) ?></td>
                <td><?= htmlspecialchars($it['tracking_number']) ?></td>

                <td class="right">
                    <a href="https://lightgray-vulture-703201.hostingersite.com/PDF/SummaryDocuments/<?= htmlspecialchars($it['PDF_URL']) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="btn btn-sm" 
                       title="Open PDF">
                        📄
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="muted">No orders found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</div>

    <script>
        document.querySelectorAll(".col-filter").forEach(input => {
            input.addEventListener("input", function () {
        
                const colIndex = parseInt(this.dataset.col, 10);
                const filterValue = this.value.toLowerCase().trim();
                const table = document.getElementById("orders-table");
                const rows = table.querySelectorAll("tbody tr");
        
                rows.forEach(row => {
                    const cell = row.cells[colIndex];
                    if (!cell) return;
        
                    const text = cell.textContent.toLowerCase();
        
                    // Show row only if column text matches filter
                    row.style.display = text.includes(filterValue) ? "" : "none";
                });
            });
        });
    </script>


</body>
</html>
