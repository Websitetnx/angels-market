<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$pageTitle = 'My Orders';
$userId = $_SESSION['user_id'];
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Handle Order Cancellation by Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Refresh the page and try again.');
    } elseif ($orderId === false) {
        setFlash('error', 'Invalid order selected.');
    } else {
        try {
            changeOrderStatus($pdo, $orderId, 'Cancelled', $userId);
            setFlash('success', 'Order cancelled successfully.');
        } catch (Throwable $e) {
            error_log('Client order cancellation failed: ' . $e->getMessage());
            setFlash('error', 'Only your pending orders can be cancelled.');
        }
    }

    header('Location: orders.php' . ($statusFilter ? '?status=' . urlencode($statusFilter) : ''));
    exit();
}

$where = "o.user_id = ?";
$params = [$userId];
if ($statusFilter && in_array($statusFilter, ['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'])) {
    $where .= " AND o.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT o.* FROM orders o WHERE $where ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-receipt" style="color:var(--primary)"></i> My Orders</h4>

    <!-- Status Filter Tabs -->
    <div class="d-flex gap-2 mb-4 overflow-auto pb-2">
        <?php
        $statuses = [''=>'All','Pending'=>'Pending','Confirmed'=>'Confirmed','Processing'=>'Processing','Shipped'=>'To receive','Delivered'=>'Delivered','Cancelled'=>'Cancelled'];
        foreach ($statuses as $key => $label):
            $active = ($statusFilter === $key) ? 'btn-shopee' : 'btn-shopee-outline';
        ?>
        <a href="?status=<?= $key ?>" class="btn btn-sm <?= $active ?> text-nowrap"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
    <div class="text-center py-5 bg-white rounded shadow-sm">
        <i class="bi bi-receipt-cutoff" style="font-size:64px;color:#ddd"></i>
        <h5 class="text-muted mt-3">No orders found</h5>
        <a href="home.php" class="btn btn-shopee mt-2">Start Shopping</a>
    </div>
    <?php else: ?>
    <?php foreach ($orders as $order):
        $itemsStmt = $pdo->prepare("SELECT oi.*, p.product_name,
            (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
            FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $itemsStmt->execute([$order['id']]);
        $orderItems = $itemsStmt->fetchAll();
    ?>
    <div class="order-card">
        <div class="order-header">
            <div>
                <strong><?= $order['order_number'] ?></strong>
                <span class="text-muted small ms-2"><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></span>
            </div>
            <span class="badge bg-<?= getStatusBadge($order['status']) ?>"><?= formatStatus($order['status']) ?></span>
        </div>
        <div class="order-body">
            <?php foreach ($orderItems as $oi):
                $imgSrc = ($oi['primary_image'] && file_exists(UPLOAD_PATH . $oi['primary_image'])) ? UPLOAD_URL . $oi['primary_image'] : BASE_URL . 'assets/images/no-image.png';
            ?>
            <div class="order-item">
                <img src="<?= $imgSrc ?>" alt="">
                <div class="flex-1">
                    <div class="fw-500"><?= htmlspecialchars($oi['product_name']) ?></div>
                    <div class="text-muted small">
                        <?= $oi['size'] ? "Size: {$oi['size']}" : '' ?>
                        <?= $oi['color'] ? "| Color: {$oi['color']}" : '' ?>
                    </div>
                    <div class="small">x<?= $oi['quantity'] ?></div>
                </div>
                <div class="fw-bold" style="color:var(--primary)"><?= formatPrice($oi['price'] * $oi['quantity']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="order-footer d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted small me-3"><i class="bi bi-credit-card"></i> <?= $order['payment_method'] ?></span>
                Total: <span class="order-total"><?= formatPrice($order['total_amount']) ?></span>
            </div>
            <?php if ($order['status'] === 'Pending'): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <button type="submit" name="cancel_order" class="btn btn-sm btn-outline-danger">Cancel Order</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
