<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) { header('Location: home.php'); exit(); }

$pageTitle = 'Register';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = strtolower(sanitize($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$fullname || !$email || !$password) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'client')");
            $stmt->execute([$fullname, $email, $hashedPassword]);
            setFlash('success', 'Registration successful! Please login.');
            header('Location: login.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Angel's Beauty Co.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="bi bi-bag-heart-fill" style="font-size:48px;color:var(--primary)"></i>
            <h2>Create Account</h2>
            <p class="text-muted">Join Angel's Beauty Co. today</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label small fw-600">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                    <input type="text" name="fullname" class="form-control" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
                </div>
            </div>
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
                    <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-600">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-2">Sign Up</button>
        </form>
        <p class="text-center mt-4 small">
            Already have an account? <a href="login.php" class="fw-bold" style="color:var(--primary)">Login</a>
        </p>
    </div>
</div>
</body>
</html>
