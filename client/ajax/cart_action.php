<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function cartResponse($success, $message = '', $extra = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cartResponse(false, 'Invalid request method.', [], 405);
}
if (!isLoggedIn() || !isClient()) {
    cartResponse(false, 'Please login as a customer first.', [], 401);
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    cartResponse(false, 'Your session expired. Refresh the page and try again.', [], 419);
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $size = sanitize($_POST['size'] ?? '');
            $color = sanitize($_POST['color'] ?? '');

            if ($productId === false || $quantity === false) {
                cartResponse(false, 'Invalid product or quantity.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id, stock, sizes, colors
                 FROM products
                 WHERE id = ? AND status = 'Available'
                 FOR UPDATE"
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product || (int)$product['stock'] <= 0) {
                $pdo->rollBack();
                cartResponse(false, 'Product is not available.');
            }

            $availableSizes = array_values(array_filter(array_map('trim', explode(',', (string)$product['sizes']))));
            $availableColors = array_values(array_filter(array_map('trim', explode(',', (string)$product['colors']))));

            if ($availableSizes && !in_array($size, $availableSizes, true)) {
                $pdo->rollBack();
                cartResponse(false, 'Please select a valid size or product option.');
            }
            if ($availableColors && !in_array($color, $availableColors, true)) {
                $pdo->rollBack();
                cartResponse(false, 'Please select a valid color.');
            }

            $checkStmt = $pdo->prepare(
                "SELECT id, quantity FROM cart
                 WHERE user_id = ? AND product_id = ? AND size = ? AND color = ?
                 FOR UPDATE"
            );
            $checkStmt->execute([$userId, $productId, $size, $color]);
            $existing = $checkStmt->fetch();
            $newQuantity = $quantity + ($existing ? (int)$existing['quantity'] : 0);

            // Stock is shared by every size/color variant of a product.
            $totalStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0) AS total_quantity
                 FROM cart
                 WHERE user_id = ? AND product_id = ?"
            );
            $totalStmt->execute([$userId, $productId]);
            $newProductTotal = (int)$totalStmt->fetch()['total_quantity'] + $quantity;

            if ($newProductTotal > (int)$product['stock']) {
                $pdo->rollBack();
                cartResponse(
                    false,
                    'Only ' . (int)$product['stock'] . ' item(s) are available across all selected options.'
                );
            }

            if ($existing) {
                $updateStmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $updateStmt->execute([$newQuantity, $existing['id'], $userId]);
            } else {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO cart (user_id, product_id, quantity, size, color)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $insertStmt->execute([$userId, $productId, $quantity, $size, $color]);
            }

            $pdo->commit();
            cartResponse(true, 'Added to cart!', ['cart_count' => getCartCount($pdo, $userId)]);

        case 'increase':
            $cartId = filter_var($_POST['cart_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($cartId === false) {
                cartResponse(false, 'Invalid cart item.');
            }

            $pdo->beginTransaction();

            $itemStmt = $pdo->prepare(
                "SELECT c.id, c.product_id, c.quantity, p.stock, p.status
                 FROM cart c
                 INNER JOIN products p ON p.id = c.product_id
                 WHERE c.id = ? AND c.user_id = ?
                 FOR UPDATE"
            );
            $itemStmt->execute([$cartId, $userId]);
            $cartItem = $itemStmt->fetch();

            if (!$cartItem || $cartItem['status'] !== 'Available') {
                $pdo->rollBack();
                cartResponse(false, 'The cart item is unavailable.');
            }

            $totalStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0) AS total_quantity
                 FROM cart
                 WHERE user_id = ? AND product_id = ?"
            );
            $totalStmt->execute([$userId, $cartItem['product_id']]);
            $productTotal = (int)$totalStmt->fetch()['total_quantity'];

            if ($productTotal >= (int)$cartItem['stock']) {
                $pdo->rollBack();
                cartResponse(false, 'Maximum stock reached across all selected options.');
            }

            $updateStmt = $pdo->prepare(
                "UPDATE cart SET quantity = quantity + 1 WHERE id = ? AND user_id = ?"
            );
            $updateStmt->execute([$cartId, $userId]);
            $pdo->commit();
            cartResponse(true, 'Cart updated.');

        case 'decrease':
            $cartId = filter_var($_POST['cart_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($cartId === false) {
                cartResponse(false, 'Invalid cart item.');
            }

            $stmt = $pdo->prepare(
                "UPDATE cart SET quantity = quantity - 1
                 WHERE id = ? AND user_id = ? AND quantity > 1"
            );
            $stmt->execute([$cartId, $userId]);
            if ($stmt->rowCount() !== 1) {
                cartResponse(false, 'Minimum quantity is 1.');
            }
            cartResponse(true, 'Cart updated.');

        case 'remove':
            $cartId = filter_var($_POST['cart_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($cartId === false) {
                cartResponse(false, 'Invalid cart item.');
            }

            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartId, $userId]);
            if ($stmt->rowCount() !== 1) {
                cartResponse(false, 'Cart item was not found.');
            }
            cartResponse(true, 'Item removed.', ['cart_count' => getCartCount($pdo, $userId)]);

        default:
            cartResponse(false, 'Invalid cart action.', [], 400);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Cart action failed: ' . $e->getMessage());
    cartResponse(false, 'The cart could not be updated. Please try again.', [], 500);
}
