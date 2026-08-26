<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Manage Orders';

// Update status via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = sanitize($_POST['new_status']);
    $validStatuses = ['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'];
    if (in_array($newStatus, $validStatuses)) {
        // If cancelling, restore stock
        if ($newStatus === 'Cancelled') {
            $currentStmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $currentStmt->execute([$orderId]);
            $currentOrder = $currentStmt->fetch();
            if ($currentOrder && $currentOrder['status'] !== 'Cancelled') {
                $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$orderId]);
                while ($item = $itemsStmt->fetch()) {
                    $pdo->prepare("UPDATE products SET stock = stock + ?, sold = GREATEST(0, sold - ?) WHERE id = ?")
                        ->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
                }
            }
        }
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
        setFlash('success', "Order status updated to $newStatus.");
    } else {
        setFlash('error', 'Invalid status.');
    }
    // Preserve current filters on redirect
    $redirect = 'orders.php';
    if (!empty($_POST['return_url'])) {
        $redirect = $_POST['return_url'];
    }
    header('Location: ' . $redirect);
    exit();
}

// Delete order
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();

        // If order was not cancelled, restore product stock
        $curStmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $curStmt->execute([$delId]);
        $orderInfo = $curStmt->fetch();

        if ($orderInfo && $orderInfo['status'] !== 'Cancelled') {
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$delId]);
            while ($item = $itemsStmt->fetch()) {
                $pdo->prepare("UPDATE products SET stock = stock + ?, sold = GREATEST(0, sold - ?) WHERE id = ?")
                    ->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            }
        }

        // Delete order items
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$delId]);

        // Delete the order
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$delId]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            setFlash('success', 'Order deleted successfully.');
        } else {
            $pdo->rollBack();
            setFlash('error', 'Order not found.');
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to delete order: ' . $e->getMessage());
    }
    $redirect = 'orders.php';
    if (!empty($_GET['status'])) $redirect .= '?status=' . urlencode($_GET['status']);
    header('Location: ' . $redirect);
    exit();
}

$statusFilter = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = "1=1";
$params = [];
if ($statusFilter && in_array($statusFilter, ['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'])) {
    $where .= " AND o.status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $where .= " AND (o.order_number LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id WHERE $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = getPagination($total, $perPage, $page);

$stmt = $pdo->prepare("SELECT o.*, u.fullname as customer_name, u.email as customer_email,
    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
    FROM orders o JOIN users u ON o.user_id = u.id 
    WHERE $where ORDER BY o.created_at DESC 
    LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status counts for badges
$statusCounts = [];
$countAll = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$statusCounts[''] = $countAll;
foreach (['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'] as $s) {
    $sc = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $sc->execute([$s]);
    $statusCounts[$s] = $sc->fetchColumn();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <div>
            <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <h2><i class="bi bi-receipt" style="color:var(--primary)"></i> Orders</h2>
        </div>
        <span class="text-muted small"><?= $total ?> total orders</span>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-3 shadow-sm p-3 mb-3">
        <div class="d-flex gap-2 flex-wrap align-items-center mb-3">
            <form class="d-flex gap-2" method="GET" style="min-width:280px">
                <?php if ($statusFilter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <?php endif; ?>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search order # or customer..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-shopee btn-sm"><i class="bi bi-search"></i></button>
                <?php if ($search): ?>
                <a href="orders.php<?= $statusFilter ? '?status=' . urlencode($statusFilter) : '' ?>" class="btn btn-sm btn-outline-secondary" title="Clear search"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php
            $allStatuses = [''=>'All','Pending'=>'Pending','Confirmed'=>'Confirmed','Processing'=>'Processing','Shipped'=>'To receive','Delivered'=>'Delivered','Cancelled'=>'Cancelled'];
            foreach ($allStatuses as $key => $label):
                $active = ($statusFilter === $key) ? 'btn-shopee' : 'btn-shopee-outline';
                $count = $statusCounts[$key] ?? 0;
            ?>
            <a href="?status=<?= $key ?><?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $active ?>">
                <?= $label ?> <span class="badge bg-white text-dark ms-1" style="font-size:10px"><?= $count ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="data-table">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="min-width:180px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><span class="fw-600"><?= $o['order_number'] ?></span></td>
                <td>
                    <div class="fw-500"><?= htmlspecialchars($o['customer_name']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($o['customer_email']) ?></small>
                </td>
                <td><?= $o['item_count'] ?> item<?= $o['item_count'] > 1 ? 's' : '' ?></td>
                <td class="fw-bold" style="color:var(--primary)"><?= formatPrice($o['total_amount']) ?></td>
                <td>
                    <span class="badge <?= $o['payment_method'] === 'GCash' ? 'bg-primary' : 'bg-secondary' ?>">
                        <i class="bi <?= $o['payment_method'] === 'GCash' ? 'bi-phone' : 'bi-cash-stack' ?>"></i>
                        <?= $o['payment_method'] ?>
                    </span>
                </td>
                <td><span class="badge bg-<?= getStatusBadge($o['status']) ?>"><?= formatStatus($o['status']) ?></span></td>
                <td class="text-muted small"><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <!-- View Details -->
                        <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <!-- Update Status -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown" title="Update Status">
                                <i class="bi bi-arrow-repeat"></i> Status
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><h6 class="dropdown-header">Update Status</h6></li>
                                <?php foreach (['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'] as $s):
                                    $isCurrent = ($o['status'] === $s);
                                    $icon = match($s) {
                                        'Pending' => 'bi-clock',
                                        'Confirmed' => 'bi-check-circle',
                                        'Processing' => 'bi-gear',
                                        'Shipped' => 'bi-truck',
                                        'Delivered' => 'bi-check2-all',
                                        'Cancelled' => 'bi-x-circle',
                                        default => 'bi-circle'
                                    };
                                ?>
                                <li>
                                    <form method="POST">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $s ?>">
                                        <input type="hidden" name="return_url" value="orders.php?<?= http_build_query($_GET) ?>">
                                        <button type="submit" name="update_status" class="dropdown-item d-flex align-items-center gap-2 <?= $isCurrent ? 'active' : '' ?>" <?= $isCurrent ? 'disabled' : '' ?>>
                                            <i class="bi <?= $icon ?>"></i> <?= formatStatus($s) ?>
                                            <?php if ($isCurrent): ?><i class="bi bi-check-lg ms-auto text-success"></i><?php endif; ?>
                                        </button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <!-- Delete Order -->
                        <a href="orders.php?delete=<?= $o['id'] ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this order?');" title="Delete Order">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-inbox" style="font-size:48px;color:#ddd"></i>
                <p class="mt-2 mb-0">No orders found</p>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['totalPages'] > 1): ?>
    <nav class="mt-3 d-flex justify-content-center">
        <ul class="pagination pagination-sm">
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++):
                $urlParams = $_GET; $urlParams['page'] = $i;
            ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query($urlParams) ?>" style="<?= $i == $page ? 'background:var(--primary);border-color:var(--primary)' : 'color:var(--primary)' ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>const baseUrl = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body></html>
