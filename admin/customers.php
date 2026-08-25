<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Manage Customers';

// Remove customer access and personal profile data while preserving order history.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try deleting the customer again.');
        header('Location: customers.php');
        exit();
    }

    $delId = filter_var(
        $_POST['customer_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($delId === false) {
        setFlash('error', 'Invalid customer selected.');
        header('Location: customers.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Lock and verify the account. Admin accounts can never be removed here.
        $customerStmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ? AND role = 'client' FOR UPDATE");
        $customerStmt->execute([$delId]);
        $customer = $customerStmt->fetch();

        if (!$customer) {
            $pdo->rollBack();
            setFlash('error', 'Customer not found or admin accounts cannot be deleted.');
            header('Location: customers.php');
            exit();
        }

        // Cart data is temporary and can be safely removed.
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$delId]);

        // Anonymize and disable the account instead of destroying its orders.
        $deletedEmail = 'deleted-user-' . $delId . '-' . bin2hex(random_bytes(4)) . '@example.invalid';
        $disabledPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "UPDATE users
             SET fullname = 'Deleted Customer',
                 email = ?,
                 phone = NULL,
                 address = NULL,
                 city = NULL,
                 province = NULL,
                 zip_code = NULL,
                 password = ?,
                 avatar = NULL
             WHERE id = ? AND role = 'client'"
        );
        $stmt->execute([$deletedEmail, $disabledPassword, $delId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Customer record changed before removal.');
        }

        $pdo->commit();
        setFlash('success', 'Customer "' . $customer['fullname'] . '" was removed. Order history was preserved.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Customer removal failed: ' . $e->getMessage());
        setFlash('error', 'The customer could not be removed. Please try again.');
    }

    header('Location: customers.php');
    exit();
}

$search = sanitize($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = "u.role = 'client' AND u.email NOT LIKE 'deleted-user-%@example.invalid'";
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
                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove this customer account? Login access and profile data will be removed, while order history will be retained.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" name="delete_customer" class="btn btn-sm btn-outline-danger" title="Delete Customer">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>
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
