<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: orders.php'); exit(); }

// Update or delete from the detail page using protected POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Refresh the page and try again.');
        header("Location: order_detail.php?id=$id");
        exit();
    }

    try {
        if (isset($_POST['update_status'])) {
            $newStatus = sanitize($_POST['new_status'] ?? '');
            changeOrderStatus($pdo, $id, $newStatus);
            setFlash('success', "Order status updated to $newStatus.");
            header("Location: order_detail.php?id=$id");
            exit();
        }

        if (isset($_POST['delete_order'])) {
            deleteOrderSafely($pdo, $id);
            setFlash('success', 'Order deleted successfully.');
            header('Location: orders.php');
            exit();
        }
    } catch (Throwable $e) {
        error_log('Order detail action failed: ' . $e->getMessage());
        setFlash('error', $e instanceof DomainException
            ? $e->getMessage()
            : 'The order could not be updated. Please try again.');
        header("Location: order_detail.php?id=$id");
        exit();
    }
}

// Fetch order
$stmt = $pdo->prepare("SELECT o.*, u.email AS customer_email
    FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { header('Location: orders.php'); exit(); }

// Fetch order items
$itemsStmt = $pdo->prepare("SELECT oi.*, p.product_name, p.brand,
    (SELECT pi.image FROM product_images pi WHERE pi.product_id = oi.product_id AND pi.is_primary = 1 LIMIT 1) as product_image
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ? ORDER BY oi.id");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'Order ' . $order['order_number'];

// Status timeline
$statusFlow = ['Pending','Confirmed','Processing','Shipped','Delivered'];
$currentIndex = array_search($order['status'], $statusFlow);
$isCancelled = ($order['status'] === 'Cancelled');
$allowedTransitions = getAllowedOrderTransitions($order['status']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
.order-timeline { display:flex; justify-content:space-between; position:relative; margin:24px 0 32px; padding:0 20px; }
.order-timeline::before { content:''; position:absolute; top:18px; left:60px; right:60px; height:3px; background:#e0e8f0; z-index:0; }
.order-timeline .step { display:flex; flex-direction:column; align-items:center; position:relative; z-index:1; min-width:80px; }
.order-timeline .step-icon { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; margin-bottom:8px; border:3px solid #e0e8f0; background:#fff; color:#999; transition:all .3s; }
.order-timeline .step.completed .step-icon { background:var(--primary); border-color:var(--primary); color:#fff; }
.order-timeline .step.current .step-icon { background:#fff; border-color:var(--primary); color:var(--primary); box-shadow:0 0 0 4px rgba(74,144,217,.2); }
.order-timeline .step.cancelled .step-icon { background:#dc3545; border-color:#dc3545; color:#fff; }
.order-timeline .step-label { font-size:11px; font-weight:600; color:#999; text-align:center; }
.order-timeline .step.completed .step-label,
.order-timeline .step.current .step-label { color:var(--primary); }
.order-timeline .step.cancelled .step-label { color:#dc3545; }

.detail-card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(74,144,217,.08); padding:24px; margin-bottom:20px; }
.detail-card h6 { font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:2px solid #f0f5fa; display:flex; align-items:center; gap:8px; }
.detail-card h6 i { color:var(--primary); }
.info-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f8f9fa; font-size:13px; }
.info-row:last-child { border-bottom:none; }
.info-label { color:#757575; font-weight:500; }
.info-value { font-weight:600; color:#222; text-align:right; }

.order-item-row { display:flex; gap:14px; padding:14px 0; border-bottom:1px solid #f5f5f5; align-items:center; }
.order-item-row:last-child { border-bottom:none; }
.order-item-img { width:72px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #eee; }
.order-item-info { flex:1; }
.order-item-name { font-weight:600; font-size:14px; margin-bottom:2px; }
.order-item-variant { font-size:12px; color:#999; }
.order-item-price { text-align:right; min-width:100px; }
.order-item-price .unit { font-size:12px; color:#999; }
.order-item-price .subtotal { font-size:15px; font-weight:700; color:var(--primary); }
</style>

<div class="admin-content">
    <!-- Header -->
    <div class="admin-header">
        <div>
            <a href="orders.php" class="btn btn-sm btn-shopee-outline me-2"><i class="bi bi-arrow-left"></i> Back to Orders</a>
            <h2 class="d-inline-block align-middle mb-0">Order <?= $order['order_number'] ?></h2>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-<?= getStatusBadge($order['status']) ?>" style="font-size:14px;padding:8px 16px"><?= formatStatus($order['status']) ?></span>
            <?php if (in_array($order['status'], ['Pending', 'Cancelled'], true)): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this order? Only pending or cancelled orders can be deleted.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" name="delete_order" class="btn btn-sm btn-outline-danger" title="Delete Order">
                    <i class="bi bi-trash"></i> Delete Order
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Timeline -->
    <?php if (!$isCancelled): ?>
    <div class="detail-card">
        <h6><i class="bi bi-signpost-split"></i> Order Progress</h6>
        <div class="order-timeline">
            <?php foreach ($statusFlow as $idx => $sf):
                $stepIcon = match($sf) {
                    'Pending' => 'bi-clock', 'Confirmed' => 'bi-check-circle', 'Processing' => 'bi-gear',
                    'Shipped' => 'bi-truck', 'Delivered' => 'bi-check2-all', default => 'bi-circle'
                };
                $class = '';
                if ($currentIndex !== false) {
                    if ($idx < $currentIndex) $class = 'completed';
                    elseif ($idx === $currentIndex) $class = 'current';
                }
            ?>
            <div class="step <?= $class ?>">
                <div class="step-icon"><i class="bi <?= $stepIcon ?>"></i></div>
                <span class="step-label"><?= formatStatus($sf) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="detail-card" style="border-left:4px solid #dc3545">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-x-octagon-fill text-danger" style="font-size:32px"></i>
            <div>
                <h6 class="mb-1" style="border:none;padding:0">Order Cancelled</h6>
                <p class="text-muted mb-0 small">This order has been cancelled. Stock has been restored.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Items + Summary -->
        <div class="col-lg-8">
            <!-- Order Items -->
            <div class="detail-card">
                <h6><i class="bi bi-bag"></i> Order Items (<?= count($items) ?>)</h6>
                <?php foreach ($items as $item):
                    $imgSrc = ($item['product_image'] && file_exists(UPLOAD_PATH . $item['product_image']))
                        ? UPLOAD_URL . $item['product_image']
                        : BASE_URL . 'assets/images/no-image.png';
                    $lineTotal = $item['price'] * $item['quantity'];
                ?>
                <div class="order-item-row">
                    <img src="<?= $imgSrc ?>" class="order-item-img" alt="">
                    <div class="order-item-info">
                        <div class="order-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="order-item-variant">
                            <?php if (!empty($item['brand'])): ?><span class="me-2">Brand: <?= htmlspecialchars($item['brand']) ?></span><?php endif; ?>
                            <?php if (!empty($item['size'])): ?><span class="me-2">Size: <strong><?= htmlspecialchars($item['size']) ?></strong></span><?php endif; ?>
                            <?php if (!empty($item['color'])): ?><span>Color: <strong><?= htmlspecialchars($item['color']) ?></strong></span><?php endif; ?>
                        </div>
                        <div class="mt-1 small text-muted">Qty: <strong><?= $item['quantity'] ?></strong></div>
                    </div>
                    <div class="order-item-price">
                        <div class="unit"><?= formatPrice($item['price']) ?> × <?= $item['quantity'] ?></div>
                        <div class="subtotal"><?= formatPrice($lineTotal) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Totals -->
                <div class="mt-3 pt-3" style="border-top:2px solid #f0f5fa">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-600"><?= formatPrice($order['total_amount']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Shipping Fee</span>
                        <span class="fw-600 text-success">Free</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2" style="border-top:1px dashed #e0e8f0">
                        <span class="fw-700 fs-6">Total</span>
                        <span class="fw-800 fs-5" style="color:var(--primary)"><?= formatPrice($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Details Sidebar -->
        <div class="col-lg-4">
            <!-- Customer Info -->
            <div class="detail-card">
                <h6><i class="bi bi-person-circle"></i> Customer</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px">
                        <?= strtoupper(substr($order['fullname'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="fw-600"><?= htmlspecialchars($order['fullname']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($order['customer_email'] ?? 'Account removed') ?></small>
                    </div>
                </div>
                <?php if ($order['phone']): ?>
                <div class="info-row"><span class="info-label"><i class="bi bi-telephone"></i> Phone</span><span class="info-value"><?= htmlspecialchars($order['phone']) ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Shipping Address -->
            <div class="detail-card">
                <h6><i class="bi bi-geo-alt"></i> Shipping Address</h6>
                <p class="small mb-1 fw-500"><?= htmlspecialchars($order['shipping_address'] ?? $order['address'] ?? 'N/A') ?></p>
                <?php if ($order['city'] || $order['province']): ?>
                <p class="small text-muted mb-0"><?= htmlspecialchars(trim(($order['city'] ?? '') . ', ' . ($order['province'] ?? ''), ', ')) ?> <?= htmlspecialchars($order['zip_code'] ?? '') ?></p>
                <?php endif; ?>
            </div>

            <!-- Payment Info -->
            <div class="detail-card">
                <h6><i class="bi bi-credit-card"></i> Payment</h6>
                <div class="info-row">
                    <span class="info-label">Method</span>
                    <span class="info-value">
                        <span class="badge <?= $order['payment_method'] === 'GCash' ? 'bg-primary' : 'bg-secondary' ?>">
                            <i class="bi <?= $order['payment_method'] === 'GCash' ? 'bi-phone' : 'bi-cash-stack' ?>"></i>
                            <?= $order['payment_method'] ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Amount</span>
                    <span class="info-value fw-700" style="color:var(--primary)"><?= formatPrice($order['total_amount']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time</span>
                    <span class="info-value"><?= date('h:i A', strtotime($order['created_at'])) ?></span>
                </div>
            </div>

            <!-- Update Status Card -->
            <div class="detail-card" style="border:2px solid var(--primary-pale)">
                <h6><i class="bi bi-arrow-repeat"></i> Update Status</h6>
                <?php if ($allowedTransitions): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="update_status" value="1">
                    <select name="new_status" class="form-select mb-3" required>
                        <option value="">Choose next status</option>
                        <?php foreach ($allowedTransitions as $s): ?>
                        <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"><?= formatStatus($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-shopee w-100" onclick="return confirm('Update order status?')">
                        <i class="bi bi-check-circle"></i> Update Status
                    </button>
                </form>
                <?php else: ?>
                <p class="text-muted small mb-0">This order is final and cannot be changed.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>const baseUrl = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body></html>
