<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$pageTitle = 'My Profile';
$userId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $province = sanitize($_POST['province'] ?? '');
    $zipCode = sanitize($_POST['zip_code'] ?? '');

    if (!$fullname) {
        $error = 'Full name is required.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET fullname=?, phone=?, address=?, city=?, province=?, zip_code=? WHERE id=?");
        $stmt->execute([$fullname, $phone, $address, $city, $province, $zipCode, $userId]);
        $_SESSION['user_name'] = $fullname;
        $success = 'Profile updated successfully!';
        $user = getCurrentUser($pdo);
    }

    // Password change
    if (!empty($_POST['new_password'])) {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } else {
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPass, $userId]);
            $success = 'Password updated successfully!';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="profile-card">
                <div class="text-center mb-4">
                    <div class="profile-avatar"><?= strtoupper(substr($user['fullname'], 0, 1)) ?></div>
                    <h4 class="fw-bold"><?= htmlspecialchars($user['fullname']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                    <span class="badge bg-success">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>

                <?php if ($success): ?><div class="alert alert-success py-2 small"><?= $success ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= $error ?></div><?php endif; ?>

                <form method="POST">
                    <h6 class="fw-bold mb-3 border-bottom pb-2" style="border-color:var(--primary)!important">Personal Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-600">Full Name</label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-600">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-600">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">City</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Province</label>
                            <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($user['province'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">ZIP Code</label>
                            <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>">
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 border-bottom pb-2" style="border-color:var(--primary)!important">Change Password</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Current Password</label>
                            <input type="password" name="current_password" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">New Password</label>
                            <input type="password" name="new_password" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Confirm New</label>
                            <input type="password" name="confirm_password" class="form-control">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-shopee"><i class="bi bi-check-circle"></i> Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
