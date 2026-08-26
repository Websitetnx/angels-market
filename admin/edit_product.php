<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: products.php'); exit(); }

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { header('Location: products.php'); exit(); }

$pageTitle = 'Edit Product';
$categories = getCategories($pdo);
$images = getProductImages($pdo, $id);
$existingSizes = $product['sizes'] ? explode(',', $product['sizes']) : [];
$errors = [];

// Delete image
if (isset($_GET['delete_image'])) {
    $imgId = (int)$_GET['delete_image'];
    try {
        $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
        $imgStmt->execute([$imgId, $id]);
        $img = $imgStmt->fetch();
        if ($img) {
            deleteProductImageFile($img['image']);
            $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);
            setFlash('success', 'Image deleted successfully.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Failed to delete image: ' . $e->getMessage());
    }
    header("Location: edit_product.php?id=$id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $rating = (float)($_POST['rating'] ?? $product['rating']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $newArrival = isset($_POST['new_arrival']) ? 1 : 0;
    $location = sanitize($_POST['location'] ?? '');
    $status = sanitize($_POST['status'] ?? 'Available');

    if (!$productName) $errors[] = 'Product name is required.';
    if (!$categoryId) $errors[] = 'Category is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE products SET category_id=?, product_name=?, description=?, brand=?, gender=?, sizes=?, colors=?, price=?, discount=?, stock=?, rating=?, featured=?, new_arrival=?, location=?, status=? WHERE id=?");
        $stmt->execute([$categoryId, $productName, $description, $brand, $gender, $sizes, $colors, $price, $discount, $stock, $rating, $featured, $newArrival, $location, $status, $id]);

        // Upload new images
        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = ['name'=>$files['name'][$i], 'type'=>$files['type'][$i], 'tmp_name'=>$files['tmp_name'][$i], 'error'=>$files['error'][$i], 'size'=>$files['size'][$i]];
                if ($file['error'] === 0) {
                    $result = uploadProductImage($file, $id);
                    if ($result['success']) {
                        $pdo->prepare("INSERT INTO product_images (product_id, image, is_primary) VALUES (?,?,0)")->execute([$id, $result['filename']]);
                    }
                }
            }
        }

        setFlash('success', 'Product updated successfully!');
        header('Location: products.php');
        exit();
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Edit Product</h2>
        <a href="products.php" class="btn btn-shopee-outline"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="bg-white rounded-3 shadow-sm p-4 mb-3">
                    <h6 class="fw-bold mb-3">Product Information</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Product Name *</label>
                        <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($product['product_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($product['brand'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Gender</label>
                            <select name="gender" class="form-select">
                                <?php foreach (['Unisex','Men','Women'] as $g): ?>
                                <option value="<?= $g ?>" <?= $product['gender'] == $g ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
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
                                <input class="form-check-input" type="checkbox" name="sizes[]" value="<?= $size ?>" id="size<?= $size ?>" <?= in_array($size, $existingSizes) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="size<?= $size ?>"><?= $size ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Colors (comma separated)</label>
                        <input type="text" name="colors" class="form-control" value="<?= htmlspecialchars($product['colors'] ?? '') ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-600">Price (₱) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" value="<?= $product['price'] ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-600">Discount (%)</label>
                            <input type="number" name="discount" class="form-control" min="0" max="100" value="<?= $product['discount'] ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-600">Stock</label>
                            <input type="number" name="stock" class="form-control" min="0" value="<?= $product['stock'] ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-600">Rating</label>
                            <input type="number" name="rating" class="form-control" step="0.1" min="0" max="5" value="<?= $product['rating'] ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Current Images -->
                <div class="bg-white rounded-3 shadow-sm p-4 mb-3">
                    <h6 class="fw-bold mb-3">Current Images</h6>
                    <?php if (!empty($images)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($images as $img):
                            $imgSrc = file_exists(UPLOAD_PATH . $img['image']) ? UPLOAD_URL . $img['image'] : BASE_URL . 'assets/images/no-image.png';
                        ?>
                        <div class="position-relative">
                            <img src="<?= $imgSrc ?>" style="width:70px;height:70px;object-fit:cover;border-radius:6px" alt="">
                            <a href="edit_product.php?id=<?= $id ?>&delete_image=<?= $img['id'] ?>" class="btn btn-sm btn-danger position-absolute top-0 end-0" style="padding:0 4px;font-size:10px;border-radius:50%" onclick="return confirm('Delete this image?')">×</a>
                            <?php if ($img['is_primary']): ?><span class="badge bg-success position-absolute bottom-0 start-0" style="font-size:8px">Primary</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small">No images uploaded.</p>
                    <?php endif; ?>
                    <label class="form-label small fw-600">Add More Images</label>
                    <input type="file" name="images[]" id="productImages" class="form-control" multiple accept="image/*">
                    <div id="imagePreview" class="mt-2 d-flex flex-wrap"></div>
                </div>

                <div class="bg-white rounded-3 shadow-sm p-4 mb-3">
                    <h6 class="fw-bold mb-3">Status & Options</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Status</label>
                        <select name="status" class="form-select">
                            <option value="Available" <?= $product['status'] == 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Out of Stock" <?= $product['status'] == 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Location</label>
                        <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($product['location'] ?? '') ?>">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="featured" id="featured" <?= $product['featured'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="featured">Featured Product</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="new_arrival" id="newArrival" <?= $product['new_arrival'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="newArrival">New Arrival</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-shopee w-100 py-2"><i class="bi bi-check-circle"></i> Update Product</button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>const baseUrl = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body></html>
