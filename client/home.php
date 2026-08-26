<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Shop Clothes Online';

// Filters
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$categoryFilter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

// Build query
$where = ["p.status = 'Available'"];
$params = [];

if ($search) {
    $where[] = "(p.product_name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryFilter) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

$whereClause = implode(' AND ', $where);

// Sort
$orderBy = match($sort) {
    'price_low' => 'p.price * (1 - p.discount/100) ASC',
    'price_high' => 'p.price * (1 - p.discount/100) DESC',
    'popular' => 'p.sold DESC',
    'rating' => 'p.rating DESC',
    default => 'p.created_at DESC'
};

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$pagination = getPagination($totalProducts, $perPage, $page);

// Fetch products
$sql = "SELECT p.*, c.category_name, 
        (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $whereClause 
        ORDER BY $orderBy 
        LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Featured products
$featuredStmt = $pdo->query("SELECT p.*, c.category_name,
    (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
    FROM products p LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.featured = 1 AND p.status = 'Available' ORDER BY p.sold DESC LIMIT 10");
$featuredProducts = $featuredStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Hero Banner -->
<?php if (!$search && !$categoryFilter): ?>
<section class="hero-banner">
    <div class="container">
        <h1><i class="bi bi-bag-heart-fill"></i> Angel's Beauty Co.</h1>
        <p>Discover trendy clothes at unbeatable prices &mdash; Free shipping on orders over ₱999!</p>
        <a href="#products" class="btn btn-light btn-lg mt-3 fw-bold" style="color:var(--primary)">
            <i class="bi bi-fire"></i> Shop Now
        </a>
    </div>
</section>
<?php endif; ?>

<div class="container py-3">

    <!-- Search Results Header -->
    <?php if ($search): ?>
    <div class="mb-3">
        <h5 class="fw-bold">Search results for "<?= htmlspecialchars($search) ?>" 
            <span class="text-muted fw-normal fs-6">(<?= $totalProducts ?> items found)</span>
        </h5>
    </div>
    <?php endif; ?>

    <!-- Sort Bar -->
    <div class="d-flex justify-content-between align-items-center bg-white rounded p-3 mb-3 shadow-sm flex-wrap gap-2">
        <span class="fw-600 fs-6">Sort By:</span>
        <div class="d-flex gap-2 flex-wrap">
            <?php
            $sorts = ['newest'=>'Newest','popular'=>'Most Popular','price_low'=>'Lowest Price','price_high'=>'Highest Price','rating'=>'Top Rated'];
            foreach ($sorts as $key => $label):
                $activeClass = ($sort === $key) ? 'btn-shopee' : 'btn-shopee-outline';
                $urlParams = $_GET;
                $urlParams['sort'] = $key;
                unset($urlParams['page']);
            ?>
            <a href="?<?= http_build_query($urlParams) ?>" class="btn btn-sm <?= $activeClass ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Featured Products -->
    <?php if (!$search && !$categoryFilter && count($featuredProducts) > 0): ?>
    <div class="section-title"><i class="bi bi-fire"></i> Featured Products</div>
    <div class="product-grid mb-4">
        <?php foreach ($featuredProducts as $fp): ?>
        <?php
        $imgSrc = ($fp['primary_image'] && file_exists(UPLOAD_PATH . $fp['primary_image'])) 
            ? UPLOAD_URL . $fp['primary_image'] 
            : BASE_URL . 'assets/images/no-image.png';
        $discountedPrice = getDiscountedPrice($fp['price'], $fp['discount']);
        ?>
        <div class="product-card fade-in-up" data-url="<?= CLIENT_URL ?>product.php?id=<?= $fp['id'] ?>">
            <div class="card-img-wrap">
                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($fp['product_name']) ?>" loading="lazy">
                <?php if ($fp['discount'] > 0): ?>
                <span class="badge-discount">-<?= $fp['discount'] ?>%</span>
                <?php endif; ?>
                <?php if ($fp['new_arrival']): ?>
                <span class="badge-new">New</span>
                <?php elseif ($fp['featured']): ?>
                <span class="badge-featured"><i class="bi bi-star-fill"></i> Featured</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="product-name"><?= htmlspecialchars($fp['product_name']) ?></div>
                <div class="price-row">
                    <span class="price-current"><?= formatPrice($discountedPrice) ?></span>
                    <?php if ($fp['discount'] > 0): ?>
                    <span class="price-original"><?= formatPrice($fp['price']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-meta">
                    <div class="rating"><i class="bi bi-star-fill"></i> <?= $fp['rating'] ?></div>
                    <div class="sold"><?= formatSold($fp['sold']) ?> sold</div>
                </div>
                <?php if ($fp['location']): ?>
                <div class="card-location"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($fp['location']) ?></div>
                <?php endif; ?>
            </div>
            <div class="card-actions">
                <a href="<?= CLIENT_URL ?>product.php?id=<?= $fp['id'] ?>" class="btn btn-sm btn-shopee-outline"><i class="bi bi-eye"></i> View</a>
                <button class="btn btn-sm btn-shopee btn-add-to-cart" data-product-id="<?= $fp['id'] ?>"><i class="bi bi-cart-plus"></i> Cart</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- All Products -->
    <div class="section-title" id="products"><i class="bi bi-grid-3x3-gap-fill"></i> 
        <?= $categoryFilter ? htmlspecialchars($products[0]['category_name'] ?? 'Category') : ($search ? 'Search Results' : 'All Products') ?>
    </div>

    <?php if (empty($products)): ?>
    <div class="text-center py-5">
        <i class="bi bi-search" style="font-size:64px;color:#ddd"></i>
        <h5 class="text-muted mt-3">No products found</h5>
        <a href="<?= CLIENT_URL ?>home.php" class="btn btn-shopee mt-2">Browse All Products</a>
    </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $p): ?>
        <?php
        $imgSrc = ($p['primary_image'] && file_exists(UPLOAD_PATH . $p['primary_image'])) 
            ? UPLOAD_URL . $p['primary_image'] 
            : BASE_URL . 'assets/images/no-image.png';
        $discountedPrice = getDiscountedPrice($p['price'], $p['discount']);
        ?>
        <div class="product-card fade-in-up" data-url="<?= CLIENT_URL ?>product.php?id=<?= $p['id'] ?>">
            <div class="card-img-wrap">
                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($p['product_name']) ?>" loading="lazy">
                <?php if ($p['discount'] > 0): ?>
                <span class="badge-discount">-<?= $p['discount'] ?>%</span>
                <?php endif; ?>
                <?php if ($p['new_arrival']): ?>
                <span class="badge-new">New</span>
                <?php endif; ?>
                <?php if ($p['stock'] <= 0): ?>
                <span class="badge-sold-out">SOLD OUT</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                <div class="price-row">
                    <span class="price-current"><?= formatPrice($discountedPrice) ?></span>
                    <?php if ($p['discount'] > 0): ?>
                    <span class="price-original"><?= formatPrice($p['price']) ?></span>
                    <span class="discount-tag">-<?= $p['discount'] ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="card-meta">
                    <div class="rating"><i class="bi bi-star-fill"></i> <?= $p['rating'] ?></div>
                    <div class="sold"><?= formatSold($p['sold']) ?> sold</div>
                </div>
                <?php if ($p['location']): ?>
                <div class="card-location"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['location']) ?></div>
                <?php endif; ?>
            </div>
            <div class="card-actions">
                <a href="<?= CLIENT_URL ?>product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-shopee-outline"><i class="bi bi-eye"></i> View</a>
                <button class="btn btn-sm btn-shopee btn-add-to-cart" data-product-id="<?= $p['id'] ?>"><i class="bi bi-cart-plus"></i> Cart</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['totalPages'] > 1): ?>
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination">
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++):
                $urlParams = $_GET;
                $urlParams['page'] = $i;
            ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query($urlParams) ?>" style="<?= $i == $page ? 'background:var(--primary);border-color:var(--primary)' : 'color:var(--primary)' ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
