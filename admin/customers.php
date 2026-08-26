<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Manage Customers';

// Delete customer
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();

        // Delete cart items for this customer
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$delId]);

        // Delete order items for this customer's orders
        $pdo->prepare("DELETE oi FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE o.user_id = ?")->execute([$delId]);

        // Delete orders for this customer
        $pdo->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$delId]);

        // Delete the customer (only clients, not admins)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'client'");
        $stmt->execute([$delId]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            setFlash('success', 'Customer deleted successfully.');
        } else {
            $pdo->rollBack();
            setFlash('error', 'Customer not found or cannot delete admin accounts.');
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to delete customer: ' . $e->getMessage());
    }
    header('Location: customers.php');
    exit();
}

$search = sanitize($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = "u.role = 'client'";
$params = [];
if ($search) { $where .= " AND (u.fullname LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$total = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$pagination = getPagination($totalCount, $perPage, $page);

$stmt = $pdo->prepare("SELECT u.*, 
    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count,
    (SELECT COALESCE(SUM(o2.total_amount),0) FROM orders o2 WHERE o2.user_id = u.id AND o2.status != 'Cancelled') as total_spent
    FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Customers</h2>
    </div>

    <div class="bg-white rounded-3 shadow-sm p-3 mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="q" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-shopee"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="data-table">
        <table class="table table-hover">
            <thead><tr><th>Customer</th><th>Email</th><th>Phone</th><th>Location</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px"><?= strtoupper(substr($c['fullname'],0,1)) ?></div>
                        <span class="fw-500"><?= htmlspecialchars($c['fullname']) ?></span>
                    </div>
                </td>
                <td class="small"><?= htmlspecialchars($c['email']) ?></td>
                <td class="small"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                <td class="small"><?= $c['city'] ? htmlspecialchars($c['city'] . ', ' . $c['province']) : '—' ?></td>
                <td><span class="badge bg-primary"><?= $c['order_count'] ?></span></td>
                <td class="fw-bold" style="color:var(--primary)"><?= formatPrice($c['total_spent']) ?></td>
                <td class="text-muted small"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <a href="customers.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this customer? All their orders and cart items will also be deleted.');" title="Delete Customer">
                        <i class="bi bi-trash"></i> Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($customers)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No customers found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
    <nav class="mt-3 d-flex justify-content-center">
        <ul class="pagination pagination-sm">
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++):
                $urlParams = $_GET; $urlParams['page'] = $i;
            ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query($urlParams) ?>" style="<?= $i == $page ? 'background:var(--primary);border-color:var(--primary)' : 'color:var(--primary)' ?>"><?= $i ?></a></li>
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
