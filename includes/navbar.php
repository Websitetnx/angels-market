<?php
$cartCount = 0;
if (isLoggedIn() && isClient()) {
    $cartCount = getCartCount($pdo, $_SESSION['user_id']);
}
$navCategories = getCategories($pdo);
?>
<!-- Top Bar -->
<div class="top-bar d-none d-md-block">
    <div class="container d-flex justify-content-between">
        <div>
            <span>Download the Angel's Beauty Co. App</span>
            <a href="#"><i class="bi bi-facebook"></i></a>
            <a href="#"><i class="bi bi-instagram"></i></a>
            <a href="#"><i class="bi bi-twitter-x"></i></a>
        </div>
        <div>
            <?php if (isLoggedIn()): ?>
                <a href="<?= CLIENT_URL ?>profile.php"><i class="bi bi-person"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Account') ?></a>
                <a href="<?= CLIENT_URL ?>logout.php">Logout</a>
            <?php else: ?>
                <a href="<?= CLIENT_URL ?>register.php">Sign Up</a>
                <a href="<?= CLIENT_URL ?>login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="main-header">
    <div class="container d-flex align-items-center">
        <a href="<?= CLIENT_URL ?>home.php" class="logo">
            <i class="bi bi-gem"></i>
            <span class="d-none d-sm-inline">Angel's Beauty Co.</span>
        </a>

        <form class="search-box" id="searchForm" action="<?= CLIENT_URL ?>home.php" method="GET">
            <input type="text" name="q" placeholder="Search for beauty products..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <button type="submit"><i class="bi bi-search"></i></button>
        </form>

        <div class="header-actions d-flex align-items-center">
            <a href="<?= CLIENT_URL ?>cart.php" title="Cart">
                <i class="bi bi-cart3"></i>
                <span class="cart-badge"><?= $cartCount ?></span>
            </a>
            <a href="<?= CLIENT_URL ?>orders.php" title="Orders" class="d-none d-md-inline">
                <i class="bi bi-receipt"></i>
            </a>
        </div>
    </div>
</header>

<!-- Category Navigation -->
<nav class="nav-categories">
    <div class="container d-flex gap-1 overflow-auto">
        <a href="<?= CLIENT_URL ?>home.php" class="<?= !isset($_GET['cat']) ? 'active' : '' ?>">All</a>
        <?php foreach ($navCategories as $cat): ?>
            <a href="<?= CLIENT_URL ?>home.php?cat=<?= $cat['id'] ?>" class="<?= (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['category_name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<!-- Flash Messages -->
<?php
$flashSuccess = getFlash('success');
$flashError = getFlash('error');
if ($flashSuccess || $flashError):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($flashSuccess): ?>
    showToast('<?= addslashes($flashSuccess) ?>', 'success');
    <?php endif; ?>
    <?php if ($flashError): ?>
    showToast('<?= addslashes($flashError) ?>', 'error');
    <?php endif; ?>
});
</script>
<?php endif; ?>
