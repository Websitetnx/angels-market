<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Handle AJAX cart actions
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $size = sanitize($_POST['size'] ?? '');
        $color = sanitize($_POST['color'] ?? '');

        // Check product exists and has stock
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'Available'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not available.']);
            exit();
        }

        if ($product['stock'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock.']);
            exit();
        }

        // Check if already in cart with same size/color
        $checkStmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND size = ? AND color = ?");
        $checkStmt->execute([$userId, $productId, $size, $color]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;
            $updateStmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $updateStmt->execute([$newQty, $existing['id']]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, size, color) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute([$userId, $productId, $quantity, $size, $color]);
        }

        $cartCount = getCartCount($pdo, $userId);
        echo json_encode(['success' => true, 'message' => 'Added to cart!', 'cart_count' => $cartCount]);
        break;

    case 'increase':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT c.*, p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
        $stmt->execute([$cartId, $userId]);
        $item = $stmt->fetch();
        if ($item && $item['quantity'] < $item['stock']) {
            $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?")->execute([$cartId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Maximum stock reached.']);
        }
        break;

    case 'decrease':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cartId, $userId]);
        $item = $stmt->fetch();
        if ($item && $item['quantity'] > 1) {
            $pdo->prepare("UPDATE cart SET quantity = quantity - 1 WHERE id = ?")->execute([$cartId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1.']);
        }
        break;

    case 'remove':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([$cartId, $userId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
