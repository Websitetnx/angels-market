<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Manage Products';

// Delete products only when they are not part of an order.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $delId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Refresh the page and try again.');
    } elseif ($delId === false) {
        setFlash('error', 'Invalid product selected.');
    } else {
        $imageFiles = [];
        try {
            $pdo->beginTransaction();

            $productStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? FOR UPDATE");
            $productStmt->execute([$delId]);
            if (!$productStmt->fetch()) {
                throw new RuntimeException('Product not found.');
            }

            $historyStmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
            $historyStmt->execute([$delId]);
            if ((int)$historyStmt->fetchColumn() > 0) {
                throw new DomainException('This product has order history. Mark it Out of Stock instead of deleting it.');
            }

            $imgStmt = $pdo->prepare("SELECT image FROM product_images WHERE product_id = ?");
            $imgStmt->execute([$delId]);
            $imageFiles = array_column($imgStmt->fetchAll(), 'image');

            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM cart WHERE product_id = ?")->execute([$delId]);
            $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $deleteStmt->execute([$delId]);

            if ($deleteStmt->rowCount() !== 1) {
                throw new RuntimeException('Product could not be deleted.');
            }

            $pdo->commit();
            foreach ($imageFiles as $imageFile) {
                deleteProductImageFile($imageFile);
            }
            setFlash('success', 'Product deleted successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Product deletion failed: ' . $e->getMessage());
            setFlash('error', $e instanceof DomainException
                ? $e->getMessage()
                : 'The product could not be deleted. Please try again.');
        }
    }

    header('Location: products.php');
    exit();
}

// Filters
$search = sanitize($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = "1=1";
$params = [];
if ($search) {
    $where .= " AND (p.product_name LIKE ? OR p.brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = getPagination($total, $perPage, $page);

$sql = "SELECT p.*, c.category_name,
    (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
    FROM products p LEFT JOIN categories c ON p.category_id = c.id
    WHERE $where ORDER BY p.created_at DESC
    LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <div>
            <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <h2>Products</h2>
        </div>
        <a href="add_product.php" class="btn btn-shopee"><i class="bi bi-plus-lg"></i> Add Product</a>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-3 shadow-sm p-3 mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="q" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-shopee"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <!-- Products Table -->
    <div class="data-table">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Image</th><th>Product Name</th><th>Category</th><th>Price</th>
                    <th>Discount</th><th>Stock</th><th>Status</th><th>Created</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p):
                $imgSrc = ($p['primary_image'] && file_exists(UPLOAD_PATH . $p['primary_image'])) ? UPLOAD_URL . $p['primary_image'] : BASE_URL . 'assets/images/no-image.png';
            ?>
            <tr>
                <td><img src="<?= $imgSrc ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px" alt=""></td>
                <td>
                    <div class="fw-500"><?= truncateText($p['product_name'], 35) ?></div>
                    <small class="text-muted"><?= $p['brand'] ?></small>
                </td>
                <td><span class="badge bg-light text-dark"><?= htmlspecialchars($p['category_name'] ?? 'N/A') ?></span></td>
                <td class="fw-bold" style="color:var(--primary)"><?= formatPrice($p['price']) ?></td>
                <td><?= $p['discount'] ?>%</td>
                <td>
                    <?php if ($p['stock'] <= 10 && $p['stock'] > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $p['stock'] ?></span>
                    <?php elseif ($p['stock'] <= 0): ?>
                    <span class="badge bg-danger">0</span>
                    <?php else: ?>
                    <span class="badge bg-success"><?= $p['stock'] ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-<?= $p['status'] === 'Available' ? 'success' : 'danger' ?>"><?= $p['status'] ?></span></td>
                <td class="text-muted small"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="<?= CLIENT_URL ?>product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="View"><i class="bi bi-eye"></i></a>
                        <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this product? Products with order history cannot be deleted.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" name="delete_product" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No products found</td></tr>
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
