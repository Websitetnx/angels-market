<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Dashboard';

// Stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn();
$revenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('Confirmed','Processing','Shipped','Delivered')")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10 AND stock > 0")->fetchColumn();

// Recent orders
$recentOrders = $pdo->query("SELECT o.*, u.fullname FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();

// Recent customers
$recentCustomers = $pdo->query("SELECT * FROM users WHERE role='client' ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Top selling
$topSelling = $pdo->query("SELECT p.*, (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image 
    FROM products p ORDER BY p.sold DESC LIMIT 5")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <div>
            <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <h2>Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?></p>
        </div>
        <span class="text-muted small"><?= date('l, F j, Y') ?></span>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <?php
        $stats = [
            ['icon'=>'bi-box-seam','color'=>'#E91E63','bg'=>'#FCE4EC','label'=>'Total Products','value'=>$totalProducts],
            ['icon'=>'bi-people','color'=>'#2196f3','bg'=>'#e3f2fd','label'=>'Total Customers','value'=>$totalCustomers],
            ['icon'=>'bi-receipt','color'=>'#00bfa5','bg'=>'#e0f7fa','label'=>'Total Orders','value'=>$totalOrders],
            ['icon'=>'bi-clock-history','color'=>'#ff9800','bg'=>'#fff3e0','label'=>'Pending Orders','value'=>$pendingOrders],
            ['icon'=>'bi-currency-exchange','color'=>'#4caf50','bg'=>'#e8f5e9','label'=>'Revenue','value'=>formatPrice($revenue)],
            ['icon'=>'bi-exclamation-triangle','color'=>'#f44336','bg'=>'#ffebee','label'=>'Low Stock','value'=>$lowStock],
        ];
        foreach ($stats as $s):
        ?>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card" style="border-left-color:<?= $s['color'] ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= $s['value'] ?></div>
                        <div class="stat-label"><?= $s['label'] ?></div>
                    </div>
                    <div class="stat-icon" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>">
                        <i class="bi <?= $s['icon'] ?>"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Recent Orders -->
        <div class="col-lg-8">
            <div class="data-table">
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Recent Orders</h6>
                    <a href="orders.php" class="btn btn-sm btn-shopee-outline">View All</a>
                </div>
                <table class="table table-hover">
                    <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td class="fw-500"><?= $o['order_number'] ?></td>
                        <td><?= htmlspecialchars($o['fullname']) ?></td>
                        <td class="fw-bold" style="color:var(--primary)"><?= formatPrice($o['total_amount']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= $o['payment_method'] ?></span></td>
                        <td><span class="badge bg-<?= getStatusBadge($o['status']) ?>"><?= formatStatus($o['status']) ?></span></td>
                        <td class="text-muted small"><?= date('M d', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No orders yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar: Top Selling & Recent Customers -->
        <div class="col-lg-4">
            <!-- Top Selling -->
            <div class="bg-white rounded-3 shadow-sm p-3 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-trophy text-warning"></i> Top Selling</h6>
                <?php foreach ($topSelling as $tp):
                    $tpImg = ($tp['primary_image'] && file_exists(UPLOAD_PATH . $tp['primary_image'])) ? UPLOAD_URL . $tp['primary_image'] : BASE_URL . 'assets/images/no-image.png';
                ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="<?= $tpImg ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px" alt="">
                    <div class="flex-1 small">
                        <div class="fw-500"><?= truncateText($tp['product_name'], 28) ?></div>
                        <span class="text-muted"><?= formatSold($tp['sold']) ?> sold</span>
                    </div>
                    <span class="fw-bold small" style="color:var(--primary)"><?= formatPrice(getDiscountedPrice($tp['price'], $tp['discount'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Customers -->
            <div class="bg-white rounded-3 shadow-sm p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-people text-primary"></i> Recent Customers</h6>
                <?php foreach ($recentCustomers as $rc): ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px">
                        <?= strtoupper(substr($rc['fullname'], 0, 1)) ?>
                    </div>
                    <div class="small">
                        <div class="fw-500"><?= htmlspecialchars($rc['fullname']) ?></div>
                        <div class="text-muted"><?= htmlspecialchars($rc['email']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>const baseUrl = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body></html>
