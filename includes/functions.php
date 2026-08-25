<?php
/**
 * Helper Functions
 */

// Format price with peso sign
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

// Calculate discounted price
function getDiscountedPrice($price, $discount) {
    return $price - ($price * $discount / 100);
}

// Generate order number
function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

// Get cart count for current user
function getCartCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch()['count'];
}

// Get product primary image
function getProductImage($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT image FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
    $stmt->execute([$productId]);
    $img = $stmt->fetch();
    if ($img && file_exists(UPLOAD_PATH . $img['image'])) {
        return UPLOAD_URL . $img['image'];
    }
    // Return a placeholder if no image
    return BASE_URL . 'assets/images/no-image.png';
}

// Get all product images
function getProductImages($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

// Upload product image
function uploadProductImage($file, $productId) {
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $maxSize = 5 * 1024 * 1024;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'The image upload did not complete.'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'Invalid uploaded file.'];
    }

    $actualSize = filesize($tmpName);
    if ($actualSize === false || $actualSize > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Max 5MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    if (!isset($allowedTypes[$mimeType]) || getimagesize($tmpName) === false) {
        return ['success' => false, 'message' => 'Invalid image. Only JPG, PNG, WebP, and GIF are allowed.'];
    }

    if (!is_dir(UPLOAD_PATH) && !mkdir(UPLOAD_PATH, 0755, true) && !is_dir(UPLOAD_PATH)) {
        return ['success' => false, 'message' => 'The product image directory could not be created.'];
    }

    $filename = 'product_' . (int)$productId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
    $destination = UPLOAD_PATH . $filename;

    if (move_uploaded_file($tmpName, $destination)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Upload failed. Check the product image folder permissions.'];
}

// Delete product image file
function deleteProductImageFile($filename) {
    $safeFilename = basename((string)$filename);
    if ($safeFilename === '') {
        return;
    }

    $filepath = UPLOAD_PATH . $safeFilename;
    if (is_file($filepath)) {
        unlink($filepath);
    }
}

// Get categories
function getCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name");
    return $stmt->fetchAll();
}

// Sanitize input
function sanitize($data) {
    return trim(strip_tags((string)$data));
}

// Truncate text
function truncateText($text, $length = 50) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

// Get rating stars HTML
function getRatingStars($rating) {
    $html = '';
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;

    for ($i = 0; $i < $full; $i++) {
        $html .= '<i class="bi bi-star-fill text-warning"></i>';
    }
    if ($half) {
        $html .= '<i class="bi bi-star-half text-warning"></i>';
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= '<i class="bi bi-star text-warning"></i>';
    }
    return $html;
}

// Format sold count
function formatSold($sold) {
    if ($sold >= 1000) {
        return number_format($sold / 1000, 1) . 'k';
    }
    return $sold;
}

// Get order status badge class
function getStatusBadge($status) {
    $badges = [
        'Pending' => 'warning',
        'Confirmed' => 'info',
        'Processing' => 'primary',
        'Shipped' => 'secondary',
        'Delivered' => 'success',
        'Cancelled' => 'danger'
    ];
    return $badges[$status] ?? 'secondary';
}

// Format status label for user interface (e.g. 'Shipped' -> 'To receive')
function formatStatus($status) {
    if ($status === 'Shipped') {
        return 'To receive';
    }
    return $status;
}

// Pagination helper
function getPagination($totalRecords, $perPage, $currentPage) {
    $totalPages = ceil($totalRecords / $perPage);
    $offset = ($currentPage - 1) * $perPage;
    return [
        'totalPages' => $totalPages,
        'offset' => $offset,
        'currentPage' => $currentPage,
        'perPage' => $perPage
    ];
}

// Valid order status transitions. Cancelled and Delivered orders are final.
function getAllowedOrderTransitions($status) {
    $transitions = [
        'Pending'    => ['Confirmed', 'Processing', 'Cancelled'],
        'Confirmed'  => ['Processing', 'Cancelled'],
        'Processing' => ['Shipped', 'Cancelled'],
        'Shipped'    => ['Delivered'],
        'Delivered'  => [],
        'Cancelled'  => [],
    ];

    return $transitions[$status] ?? [];
}

// Change an order status atomically and keep inventory in sync.
function changeOrderStatus($pdo, $orderId, $newStatus, $userId = null) {
    $validStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    if (!in_array($newStatus, $validStatuses, true)) {
        throw new InvalidArgumentException('Invalid order status.');
    }

    try {
        $pdo->beginTransaction();

        $sql = "SELECT status FROM orders WHERE id = ?";
        $params = [(int)$orderId];
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = (int)$userId;
        }
        $sql .= " FOR UPDATE";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch();

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $currentStatus = $order['status'];
        if ($currentStatus === $newStatus) {
            $pdo->commit();
            return;
        }

        if (!in_array($newStatus, getAllowedOrderTransitions($currentStatus), true)) {
            throw new DomainException("Order cannot move from $currentStatus to $newStatus.");
        }

        if ($newStatus === 'Cancelled') {
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([(int)$orderId]);
            foreach ($itemsStmt->fetchAll() as $item) {
                $restoreStmt = $pdo->prepare(
                    "UPDATE products
                     SET stock = stock + ?,
                         sold = GREATEST(0, sold - ?),
                         status = 'Available'
                     WHERE id = ?"
                );
                $restoreStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            }
        }

        $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, (int)$orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Delete only Pending or Cancelled orders so completed sales history is preserved.
function deleteOrderSafely($pdo, $orderId) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([(int)$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }
        if (!in_array($order['status'], ['Pending', 'Cancelled'], true)) {
            throw new DomainException('Only Pending or Cancelled orders can be deleted.');
        }

        if ($order['status'] === 'Pending') {
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([(int)$orderId]);
            foreach ($itemsStmt->fetchAll() as $item) {
                $restoreStmt = $pdo->prepare(
                    "UPDATE products
                     SET stock = stock + ?,
                         sold = GREATEST(0, sold - ?),
                         status = 'Available'
                     WHERE id = ?"
                );
                $restoreStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            }
        }

        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([(int)$orderId]);
        $deleteStmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $deleteStmt->execute([(int)$orderId]);

        if ($deleteStmt->rowCount() !== 1) {
            throw new RuntimeException('Order could not be deleted.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

