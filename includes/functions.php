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
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
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
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WebP, GIF allowed.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Max 5MB.'];
    }

    // Create upload directory if it doesn't exist
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . $productId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = UPLOAD_PATH . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Upload failed.'];
}

// Delete product image file
function deleteProductImageFile($filename) {
    $filepath = UPLOAD_PATH . $filename;
    if (file_exists($filepath)) {
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
    return htmlspecialchars(strip_tags(trim($data)));
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
