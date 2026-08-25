<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Add Product';
$categories = getCategories($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }

    $productName = sanitize($_POST['product_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $brand = sanitize($_POST['brand'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'Unisex');
    $sizes = isset($_POST['sizes']) ? implode(',', $_POST['sizes']) : '';
    $colors = sanitize($_POST['colors'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $discount = (int)($_POST['discount'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $newArrival = isset($_POST['new_arrival']) ? 1 : 0;
    $location = sanitize($_POST['location'] ?? '');
    $status = sanitize($_POST['status'] ?? 'Available');

    if (!$productName) $errors[] = 'Product name is required.';
    if (!$categoryId) $errors[] = 'Category is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, product_name, description, brand, gender, sizes, colors, price, discount, stock, featured, new_arrival, location, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$categoryId, $productName, $description, $brand, $gender, $sizes, $colors, $price, $discount, $stock, $featured, $newArrival, $location, $status]);
        $productId = $pdo->lastInsertId();

        // Upload images
        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            $uploadedCount = 0;
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                if ($file['error'] === 0) {
                    $result = uploadProductImage($file, $productId);
                    if ($result['success']) {
                        $isPrimary = ($uploadedCount === 0) ? 1 : 0;
                        $pdo->prepare("INSERT INTO product_images (product_id, image, is_primary) VALUES (?,?,?)")->execute([$productId, $result['filename'], $isPrimary]);
                        $uploadedCount++;
                    }
                }
            }
        }

        setFlash('success', 'Product added successfully!');
        header('Location: products.php');
        exit();
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Add Product</h2>
        <a href="products.php" class="btn btn-shopee-outline"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="bg-white rounded-3 shadow-sm p-4 mb-3">
                    <h6 class="fw-bold mb-3">Product Information</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Product Name *</label>
                        <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="Unisex">Unisex</option>
                                <option value="Men">Men</option>
                                <option value="Women">Women</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3 shadow-sm p-4 mb-3">
                    <h6 class="fw-bold mb-3">Variants & Pricing</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Sizes</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (['XS','S','M','L','XL','XXL'] as $size): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sizes[]" value="<?= $size ?>" id="size<?= $size ?>">
                                <label class="form-check-label small" for="size<?= $size ?>"><?= $size ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Colors (comma separated)</label>
                        <input type="text" name="colors" class="form-control" placeholder="Black, White, Red" value="<?= htmlspecialchars($_POST['colors'] ?? '') ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Price (₱) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= $_POST['price'] ?? '' ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Discount (%)</label>
                            <input type="number" name="discount" class="form-control" min="0" max="100" value="<?= $_POST['discount'] ?? '0' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Stock</label>
                            <input type="number" name="stock" class="form-control" min="0" value="<?= $_POST['stock'] ?? '0' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="bg-white rounded-3 shadow-sm p-4 mb-3">
                    <h6 class="fw-bold mb-3">Product Images</h6>
                    <input type="file" name="images[]" id="productImages" class="form-control" multiple accept="image/*">
                    <small class="text-muted">First image will be the primary. Max 5MB each.</small>
                    <div id="imagePreview" class="mt-3 d-flex flex-wrap"></div>
                </div>

                <div class="bg-white rounded-3 shadow-sm p-4 mb-3">
                    <h6 class="fw-bold mb-3">Status & Options</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Status</label>
                        <select name="status" class="form-select">
                            <option value="Available">Available</option>
                            <option value="Out of Stock">Out of Stock</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Makati City" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="featured" id="featured">
                        <label class="form-check-label small" for="featured">Featured Product</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="new_arrival" id="newArrival">
                        <label class="form-check-label small" for="newArrival">New Arrival</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-shopee w-100 py-2"><i class="bi bi-plus-circle"></i> Add Product</button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>const baseUrl = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body></html>
