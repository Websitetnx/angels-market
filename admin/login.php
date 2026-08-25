<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn() && isAdmin()) { header('Location: dashboard.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(sanitize($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['fullname'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Angel's Beauty Co.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-container" style="background:linear-gradient(135deg,#2a0a1a,#1a0612)">
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock-fill" style="font-size:48px;color:var(--primary)"></i>
            <h2>Admin Panel</h2>
            <p class="text-muted">Angel's Beauty Co. Management</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label small fw-600">Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="angelombao@gmail.com" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-600">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-2">Login to Dashboard</button>
        </form>
        <p class="text-center mt-3 small"><a href="<?= CLIENT_URL ?>login.php" class="text-muted">← Back to Client Login</a></p>
    </div>
</div>
</body>
</html>
