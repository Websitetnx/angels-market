<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: home.php'); exit(); }

$stmt = $pdo->prepare("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { header('Location: home.php'); exit(); }

$pageTitle = $product['product_name'];
$images = getProductImages($pdo, $id);
$sizes = $product['sizes'] ? explode(',', $product['sizes']) : [];
$colors = $product['colors'] ? explode(',', $product['colors']) : [];
$discountedPrice = getDiscountedPrice($product['price'], $product['discount']);

// Related products
$relatedStmt = $pdo->prepare("SELECT p.*, (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image 
    FROM products p WHERE p.category_id = ? AND p.id != ? AND p.status = 'Available' ORDER BY RAND() LIMIT 5");
$relatedStmt->execute([$product['category_id'], $id]);
$relatedProducts = $relatedStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="home.php" class="text-decoration-none" style="color:var(--primary)">Home</a></li>
            <li class="breadcrumb-item"><a href="home.php?cat=<?= $product['category_id'] ?>" class="text-decoration-none" style="color:var(--primary)"><?= htmlspecialchars($product['category_name']) ?></a></li>
            <li class="breadcrumb-item active"><?= truncateText($product['product_name'], 40) ?></li>
        </ol>
    </nav>

    <div class="product-detail">
        <div class="row g-4">
            <!-- Image Gallery -->
            <div class="col-lg-5">
                <?php
                $mainImg = BASE_URL . 'assets/images/no-image.png';
                if (!empty($images) && file_exists(UPLOAD_PATH . $images[0]['image'])) {
                    $mainImg = UPLOAD_URL . $images[0]['image'];
                }
                ?>
                <div class="gallery-main"><img src="<?= $mainImg ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" id="mainImage"></div>
                <?php if (count($images) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($images as $i => $img):
                        $thumbSrc = file_exists(UPLOAD_PATH . $img['image']) ? UPLOAD_URL . $img['image'] : BASE_URL . 'assets/images/no-image.png';
                    ?>
                    <img src="<?= $thumbSrc ?>" class="<?= $i === 0 ? 'active' : '' ?>" data-full="<?= $thumbSrc ?>" alt="Thumb">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div class="col-lg-7">
                <?php if ($product['new_arrival']): ?>
                <span class="badge bg-success mb-2">New Arrival</span>
                <?php endif; ?>
                <?php if ($product['featured']): ?>
                <span class="badge bg-warning text-dark mb-2"><i class="bi bi-star-fill"></i> Featured</span>
                <?php endif; ?>

                <h1 class="fs-4 fw-bold mb-2"><?= htmlspecialchars($product['product_name']) ?></h1>

                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="text-warning fw-bold"><?= $product['rating'] ?> <?= getRatingStars($product['rating']) ?></span>
                    <span class="text-muted small">|</span>
                    <span class="text-muted small"><?= formatSold($product['sold']) ?> Sold</span>
                    <span class="text-muted small">|</span>
                    <span class="text-muted small"><?= $product['stock'] ?> In Stock</span>
                </div>

                <div class="detail-price-box">
                    <span class="price-current"><?= formatPrice($discountedPrice) ?></span>
                    <?php if ($product['discount'] > 0): ?>
                    <span class="price-original"><?= formatPrice($product['price']) ?></span>
                    <span class="discount-badge">-<?= $product['discount'] ?>% OFF</span>
                    <?php endif; ?>
                </div>

                <div class="detail-section d-flex gap-3">
                    <span class="detail-label">Brand</span>
                    <span class="detail-value"><?= htmlspecialchars($product['brand'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-section d-flex gap-3">
                    <span class="detail-label">Category</span>
                    <span class="detail-value"><?= htmlspecialchars($product['category_name']) ?></span>
                </div>
                <div class="detail-section d-flex gap-3">
                    <span class="detail-label">Gender</span>
                    <span class="detail-value"><?= htmlspecialchars($product['gender']) ?></span>
                </div>

                <?php if (!empty($sizes)): ?>
                <div class="detail-section">
                    <div class="detail-label mb-2">Size</div>
                    <div class="size-options">
                        <?php foreach ($sizes as $s): ?>
                        <button type="button" class="size-btn" data-size="<?= htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim($s)) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($colors)): ?>
                <div class="detail-section">
                    <div class="detail-label mb-2">Color</div>
                    <div class="color-options">
                        <?php foreach ($colors as $c): ?>
                        <button type="button" class="color-btn" data-color="<?= htmlspecialchars(trim($c), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim($c)) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="detail-section d-flex align-items-center gap-3">
                    <span class="detail-label">Quantity</span>
                    <div class="qty-control">
                        <button type="button" class="qty-minus">−</button>
                        <input type="number" id="qty-input" value="1" min="1" max="<?= $product['stock'] ?>" data-max="<?= $product['stock'] ?>">
                        <button type="button" class="qty-plus">+</button>
                    </div>
                    <span class="text-muted small"><?= $product['stock'] ?> pieces available</span>
                </div>

                <?php if ($product['location']): ?>
                <div class="detail-section d-flex gap-3">
                    <span class="detail-label">Ships From</span>
                    <span class="detail-value"><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($product['location']) ?></span>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-3 mt-4">
                    <?php if ($product['status'] === 'Available' && (int)$product['stock'] > 0): ?>
                    <button class="btn-add-cart btn-add-to-cart" data-product-id="<?= (int)$product['id'] ?>">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                    <button class="btn-buy-now btn-add-to-cart" data-product-id="<?= (int)$product['id'] ?>" data-redirect="cart.php">
                        <i class="bi bi-bag-check"></i> Buy Now
                    </button>
                    <?php else: ?>
                    <button class="btn-add-cart" disabled><i class="bi bi-x-circle"></i> Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="product-detail mt-3">
        <h5 class="fw-bold border-bottom pb-2 mb-3" style="border-color:var(--primary) !important">Product Description</h5>
        <div class="text-muted" style="white-space:pre-line"><?= nl2br(htmlspecialchars($product['description'] ?: 'No description available.')) ?></div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
    <div class="section-title mt-4"><i class="bi bi-collection"></i> Related Products</div>
    <div class="product-grid">
        <?php foreach ($relatedProducts as $rp):
            $rpImg = ($rp['primary_image'] && file_exists(UPLOAD_PATH . $rp['primary_image'])) ? UPLOAD_URL . $rp['primary_image'] : BASE_URL . 'assets/images/no-image.png';
            $rpPrice = getDiscountedPrice($rp['price'], $rp['discount']);
        ?>
        <div class="product-card" data-url="product.php?id=<?= $rp['id'] ?>">
            <div class="card-img-wrap"><img src="<?= $rpImg ?>" alt="<?= htmlspecialchars($rp['product_name']) ?>" loading="lazy">
                <?php if ($rp['discount'] > 0): ?><span class="badge-discount">-<?= $rp['discount'] ?>%</span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="product-name"><?= htmlspecialchars($rp['product_name']) ?></div>
                <div class="price-row"><span class="price-current"><?= formatPrice($rpPrice) ?></span></div>
                <div class="card-meta"><div class="rating"><i class="bi bi-star-fill"></i> <?= $rp['rating'] ?></div><div class="sold"><?= formatSold($rp['sold']) ?> sold</div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
