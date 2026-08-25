<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn() && isClient()) { header('Location: home.php'); exit(); }

$pageTitle = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(sanitize($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'client'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['fullname'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            setFlash('success', 'Welcome back, ' . $user['fullname'] . '!');
            header('Location: home.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Angel's Beauty Co.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="bi bi-bag-heart-fill" style="font-size:48px;color:var(--primary)"></i>
            <h2>Welcome Back</h2>
            <p class="text-muted">Login to your Angel's Beauty Co. account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= $error ?></div>
        <?php endif; ?>

        <?php $flash = getFlash('success'); if ($flash): ?>
        <div class="alert alert-success py-2 small"><?= $flash ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-600">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-600">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-2">Login</button>
        </form>

        <p class="text-center mt-4 small">
            Don't have an account? <a href="register.php" class="fw-bold" style="color:var(--primary)">Sign Up</a>
        </p>
        <p class="text-center small">
            <a href="<?= BASE_URL ?>admin/login.php" class="text-muted">Admin Login →</a>
        </p>
    </div>
</div>
</body>
</html>
