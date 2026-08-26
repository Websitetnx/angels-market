<?php
// Admin sidebar - included in admin pages
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-bag-heart-fill text-danger"></i> Angel's Beauty Co.</h4>
        <small>Admin Panel</small>
    </div>
    <nav class="mt-3">
        <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="products.php" class="nav-link <?= in_array($currentPage, ['products.php','add_product.php','edit_product.php']) ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Products
        </a>
        <a href="categories.php" class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a href="orders.php" class="nav-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Orders
        </a>
        <a href="customers.php" class="nav-link <?= $currentPage === 'customers.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <hr class="border-secondary mx-3">
        <a href="<?= CLIENT_URL ?>home.php" class="nav-link" target="_blank">
            <i class="bi bi-shop"></i> View Store
        </a>
        <a href="logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
</aside>

<?php
// Render flash messages as JavaScript toast notifications
$_flashSuccess = getFlash('success');
$_flashError = getFlash('error');
?>
<?php if ($_flashSuccess || $_flashError): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($_flashSuccess): ?>
    if (typeof showToast === 'function') { showToast(<?= json_encode($_flashSuccess) ?>, 'success'); }
    else { alert(<?= json_encode($_flashSuccess) ?>); }
    <?php endif; ?>
    <?php if ($_flashError): ?>
    if (typeof showToast === 'function') { showToast(<?= json_encode($_flashError) ?>, 'error'); }
    else { alert(<?= json_encode($_flashError) ?>); }
    <?php endif; ?>
});
</script>
<?php endif; ?>

