<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$pageTitle = 'Checkout';
$userId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);

// Get cart items
$stmt = $pdo->prepare("SELECT c.*, p.product_name, p.price, p.discount, p.stock,
    (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
    FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND p.status = 'Available'");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    setFlash('error', 'Your cart is empty.');
    header('Location: cart.php');
    exit();
}

$totalAmount = 0;
foreach ($cartItems as $item) {
    $totalAmount += getDiscountedPrice($item['price'], $item['discount']) * $item['quantity'];
}

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $province = sanitize($_POST['province'] ?? '');
    $zipCode = sanitize($_POST['zip_code'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'COD');
    $notes = sanitize($_POST['notes'] ?? '');

    $errors = [];
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Refresh the page and try again.';
    if (!$fullname) $errors[] = 'Full name is required.';
    if (!$phone) $errors[] = 'Phone number is required.';
    if (!$address) $errors[] = 'Address is required.';
    if (!$city) $errors[] = 'City is required.';
    if (!$province) $errors[] = 'Province is required.';
    if (!$zipCode) $errors[] = 'ZIP code is required.';
    if (!in_array($paymentMethod, ['COD', 'GCash'], true)) $errors[] = 'Invalid payment method.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Lock current cart and product rows, then calculate the order from
            // current database values so stock cannot become negative.
            $lockStmt = $pdo->prepare(
                "SELECT c.*, p.product_name, p.price, p.discount, p.stock, p.status,
                        p.sizes AS available_sizes, p.colors AS available_colors
                 FROM cart c
                 INNER JOIN products p ON p.id = c.product_id
                 WHERE c.user_id = ?
                 ORDER BY c.id
                 FOR UPDATE"
            );
            $lockStmt->execute([$userId]);
            $lockedItems = $lockStmt->fetchAll();

            if (!$lockedItems) {
                throw new RuntimeException('Your cart is empty.');
            }

            $lockedTotal = 0;
            foreach ($lockedItems as $item) {
                $quantity = (int)$item['quantity'];
                if ($quantity < 1 || $item['status'] !== 'Available' || (int)$item['stock'] < $quantity) {
                    throw new RuntimeException($item['product_name'] . ' no longer has enough stock.');
                }

                $availableSizes = array_values(array_filter(array_map('trim', explode(',', (string)$item['available_sizes']))));
                $availableColors = array_values(array_filter(array_map('trim', explode(',', (string)$item['available_colors']))));
                if ($availableSizes && !in_array((string)$item['size'], $availableSizes, true)) {
                    throw new RuntimeException('Select a valid option for ' . $item['product_name'] . '.');
                }
                if ($availableColors && !in_array((string)$item['color'], $availableColors, true)) {
                    throw new RuntimeException('Select a valid color for ' . $item['product_name'] . '.');
                }

                $lockedTotal += getDiscountedPrice($item['price'], $item['discount']) * $quantity;
            }

            $orderNumber = generateOrderNumber();
            $orderStmt = $pdo->prepare(
                "INSERT INTO orders
                 (user_id, order_number, fullname, phone, address, city, province, zip_code, total_amount, payment_method, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
            );
            $orderStmt->execute([
                $userId, $orderNumber, $fullname, $phone, $address, $city,
                $province, $zipCode, $lockedTotal, $paymentMethod, $notes
            ]);
            $orderId = $pdo->lastInsertId();

            foreach ($lockedItems as $item) {
                $quantity = (int)$item['quantity'];
                $itemPrice = getDiscountedPrice($item['price'], $item['discount']);

                $itemStmt = $pdo->prepare(
                    "INSERT INTO order_items (order_id, product_id, quantity, price, size, color)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $itemStmt->execute([
                    $orderId, $item['product_id'], $quantity, $itemPrice,
                    $item['size'], $item['color']
                ]);

                $stockStmt = $pdo->prepare(
                    "UPDATE products
                     SET stock = stock - ?, sold = sold + ?
                     WHERE id = ? AND status = 'Available' AND stock >= ?"
                );
                $stockStmt->execute([$quantity, $quantity, $item['product_id'], $quantity]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException($item['product_name'] . ' stock changed during checkout.');
                }

                $pdo->prepare(
                    "UPDATE products SET status = 'Out of Stock' WHERE id = ? AND stock <= 0"
                )->execute([$item['product_id']]);
            }

            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare(
                "UPDATE users
                 SET phone = ?, address = ?, city = ?, province = ?, zip_code = ?
                 WHERE id = ?"
            )->execute([$phone, $address, $city, $province, $zipCode, $userId]);

            $pdo->commit();
            setFlash('success', "Order placed successfully! Order Number: $orderNumber");
            header('Location: orders.php');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout failed: ' . $e->getMessage());
            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Something went wrong. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-bag-check" style="color:var(--primary)"></i> Checkout</h4>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="row g-4">
            <div class="col-lg-7">
                <!-- Shipping Info -->
                <div class="checkout-section">
                    <h5><i class="bi bi-geo-alt"></i> Shipping Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-600">Full Name *</label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-600">Contact Number *</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-600">Complete Address *</label>
                            <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">City *</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Province *</label>
                            <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($user['province'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">ZIP Code *</label>
                            <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-600">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="checkout-section">
                    <h5><i class="bi bi-credit-card"></i> Payment Method</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="payment-option selected" onclick="this.querySelector('input').checked=true">
                                <input type="radio" name="payment_method" value="COD" checked class="d-none">
                                <i class="bi bi-cash-stack d-block mb-1"></i>
                                <strong>Cash on Delivery</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="payment-option" onclick="this.querySelector('input').checked=true">
                                <input type="radio" name="payment_method" value="GCash" class="d-none">
                                <i class="bi bi-phone d-block mb-1"></i>
                                <strong>GCash</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="cart-summary">
                    <h5 class="fw-bold mb-3">Order Summary</h5>
                    <?php foreach ($cartItems as $item):
                        $dPrice = getDiscountedPrice($item['price'], $item['discount']);
                        $imgSrc = ($item['primary_image'] && file_exists(UPLOAD_PATH . $item['primary_image'])) ? UPLOAD_URL . $item['primary_image'] : BASE_URL . 'assets/images/no-image.png';
                    ?>
                    <div class="d-flex gap-2 mb-3 pb-3 border-bottom">
                        <img src="<?= $imgSrc ?>" style="width:50px;height:50px;object-fit:cover;border-radius:4px" alt="">
                        <div class="flex-1 small">
                            <div class="fw-500"><?= truncateText($item['product_name'], 35) ?></div>
                            <div class="text-muted"><?= $item['size'] ? "Size: {$item['size']}" : '' ?> <?= $item['color'] ? "| {$item['color']}" : '' ?></div>
                            <div><?= formatPrice($dPrice) ?> x <?= $item['quantity'] ?></div>
                        </div>
                        <div class="fw-bold" style="color:var(--primary)"><?= formatPrice($dPrice * $item['quantity']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span><?= formatPrice($totalAmount) ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Shipping</span><span class="text-success">Free</span></div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold fs-5">Total</span>
                        <span class="fw-bold fs-5" style="color:var(--primary)"><?= formatPrice($totalAmount) ?></span>
                    </div>
                    <button type="submit" class="btn btn-shopee w-100 py-2 fs-5"><i class="bi bi-check-circle"></i> Place Order</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
