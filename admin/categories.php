<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Manage Categories';

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = sanitize($_POST['category_name'] ?? '');
    if ($name) {
        $check = $pdo->prepare("SELECT id FROM categories WHERE category_name = ?");
        $check->execute([$name]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)")->execute([$name]);
            setFlash('success', 'Category added.');
        } else {
            setFlash('error', 'Category already exists.');
        }
    }
    header('Location: categories.php');
    exit();
}

// Delete category
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();

        // Delete product images for all products in this category
        $pdo->prepare("DELETE pi FROM product_images pi INNER JOIN products p ON pi.product_id = p.id WHERE p.category_id = ?")->execute([$delId]);

        // Delete cart items for products in this category
        $pdo->prepare("DELETE c FROM cart c INNER JOIN products p ON c.product_id = p.id WHERE p.category_id = ?")->execute([$delId]);

        // Delete order items for products in this category
        $pdo->prepare("DELETE oi FROM order_items oi INNER JOIN products p ON oi.product_id = p.id WHERE p.category_id = ?")->execute([$delId]);

        // Delete products in this category
        $pdo->prepare("DELETE FROM products WHERE category_id = ?")->execute([$delId]);

        // Delete the category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$delId]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            setFlash('success', 'Category and its products deleted successfully.');
        } else {
            $pdo->rollBack();
            setFlash('error', 'Category not found.');
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to delete category: ' . $e->getMessage());
    }
    header('Location: categories.php');
    exit();
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as product_count FROM categories c ORDER BY c.category_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Categories</h2>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="bg-white rounded-3 shadow-sm p-4">
                <h6 class="fw-bold mb-3">Add New Category</h6>
                <form method="POST">
                    <div class="input-group">
                        <input type="text" name="category_name" class="form-control" placeholder="Category name" required>
                        <button type="submit" name="add_category" class="btn btn-shopee"><i class="bi bi-plus-lg"></i> Add</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="data-table">
                <table class="table table-hover">
                    <thead><tr><th>#</th><th>Category Name</th><th>Products</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $i => $cat): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-500"><?= htmlspecialchars($cat['category_name']) ?></td>
                        <td><span class="badge bg-primary"><?= $cat['product_count'] ?></span></td>
                        <td class="text-muted small"><?= date('M d, Y', strtotime($cat['created_at'])) ?></td>
                        <td>
                            <a href="categories.php?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this category? All associated products will also be deleted.');" title="Delete Category">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>const baseUrl = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body></html>
