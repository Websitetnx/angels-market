<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$pageTitle = 'My Cart';
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT c.*, p.product_name, p.price, p.discount, p.stock, p.status,
    (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
    FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

$totalAmount = 0;
foreach ($cartItems as $item) {
    $totalAmount += getDiscountedPrice($item['price'], $item['discount']) * $item['quantity'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-cart3" style="color:var(--primary)"></i> Shopping Cart</h4>

    <?php if (empty($cartItems)): ?>
    <div class="text-center py-5 bg-white rounded shadow-sm">
        <i class="bi bi-cart-x" style="font-size:80px;color:#ddd"></i>
        <h5 class="text-muted mt-3">Your cart is empty</h5>
        <a href="home.php" class="btn btn-shopee mt-3"><i class="bi bi-bag"></i> Continue Shopping</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <?php foreach ($cartItems as $item):
                $imgSrc = ($item['primary_image'] && file_exists(UPLOAD_PATH . $item['primary_image'])) ? UPLOAD_URL . $item['primary_image'] : BASE_URL . 'assets/images/no-image.png';
                $discountedPrice = getDiscountedPrice($item['price'], $item['discount']);
                $subtotal = $discountedPrice * $item['quantity'];
            ?>
            <div class="cart-item">
                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                <div class="item-info">
                    <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="item-variant">
                        <?php if ($item['size']): ?>Size: <?= htmlspecialchars($item['size']) ?><?php endif; ?>
                        <?php if ($item['color']): ?> | Color: <?= htmlspecialchars($item['color']) ?><?php endif; ?>
                    </div>
                    <div class="item-price mt-1"><?= formatPrice($discountedPrice) ?>
                        <?php if ($item['discount'] > 0): ?>
                        <small class="text-decoration-line-through text-muted ms-1"><?= formatPrice($item['price']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-center gap-2">
                    <div class="qty-control">
                        <button class="cart-qty-btn" data-cart-id="<?= $item['id'] ?>" data-action="decrease">−</button>
                        <input type="number" value="<?= $item['quantity'] ?>" readonly>
                        <button class="cart-qty-btn" data-cart-id="<?= $item['id'] ?>" data-action="increase">+</button>
                    </div>
                    <div class="fw-bold" style="color:var(--primary)"><?= formatPrice($subtotal) ?></div>
                </div>
                <button class="btn btn-sm btn-outline-danger ms-3 cart-remove-btn" data-cart-id="<?= $item['id'] ?>">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="col-lg-4">
            <div class="cart-summary">
                <h5 class="fw-bold mb-3">Order Summary</h5>
                <div class="d-flex justify-content-between mb-2"><span>Items (<?= count($cartItems) ?>)</span><span><?= formatPrice($totalAmount) ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span>Shipping</span><span class="text-success">Free</span></div>
                <hr>
                <div class="d-flex justify-content-between mb-3"><span class="fw-bold fs-5">Total</span><span class="fw-bold fs-5" style="color:var(--primary)"><?= formatPrice($totalAmount) ?></span></div>
                <a href="checkout.php" class="btn btn-shopee w-100 py-2"><i class="bi bi-bag-check"></i> Proceed to Checkout</a>
                <a href="home.php" class="btn btn-shopee-outline w-100 mt-2"><i class="bi bi-arrow-left"></i> Continue Shopping</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
