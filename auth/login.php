<?php
session_start();
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            header($user['role'] === 'admin'
                ? 'Location: ../admin/index.php'
                : 'Location: ../index.php');
            exit;
        } else {
            $error = 'Email hoặc mật khẩu không đúng!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Phone Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0057FF;
            --primary-dark: #0040CC;
            --dark: #0A0A0A;
            --gray: #6B7280;
            --light: #F8F8F8;
            --border: #E5E7EB;
            --danger: #EF4444;
            --font-display: 'Syne', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-body);
            background: #f0f4ff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: var(--primary);
            color: #fff;
            text-align: center;
            font-size: 0.78rem;
            padding: 7px 16px;
        }
        .navbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--dark);
            text-decoration: none;
        }
        .navbar-brand span { color: var(--primary); }
        .navbar-back {
            display: flex; align-items: center; gap: 6px;
            color: var(--gray); text-decoration: none;
            font-size: 0.85rem; font-weight: 500;
            transition: color 0.2s;
        }
        .navbar-back:hover { color: var(--primary); }

        .auth-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .auth-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            max-width: 900px;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        .auth-left {
            background: linear-gradient(145deg, #0A0A0A 0%, #0d1b3e 100%);
            padding: 52px 44px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .auth-left::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(0,87,255,0.3) 0%, transparent 70%);
            top: -80px; right: -80px;
        }
        .auth-left::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(0,87,255,0.15) 0%, transparent 70%);
            bottom: -40px; left: -40px;
        }
        .auth-left-brand {
            font-family: var(--font-display);
            font-size: 1.5rem; font-weight: 800; color: #fff;
        }
        .auth-left-brand span { color: var(--primary); }
        .auth-left-content { position: relative; z-index: 1; }
        .auth-left-title {
            font-family: var(--font-display);
            font-size: 1.9rem; font-weight: 800; color: #fff;
            line-height: 1.2; margin-bottom: 14px;
        }
        .auth-left-title span { color: #60A5FA; }
        .auth-left-desc {
            color: rgba(255,255,255,0.55);
            font-size: 0.875rem; line-height: 1.7; margin-bottom: 28px;
        }
        .auth-perks { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .auth-perk {
            display: flex; align-items: center; gap: 10px;
            color: rgba(255,255,255,0.75); font-size: 0.85rem;
        }
        .auth-perk-icon {
            width: 28px; height: 28px;
            background: rgba(0,87,255,0.2);
            border: 1px solid rgba(0,87,255,0.3);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; color: #60A5FA; flex-shrink: 0;
        }
        .auth-left-footer {
            color: rgba(255,255,255,0.3); font-size: 0.75rem; position: relative; z-index: 1;
        }
        .auth-right {
            padding: 52px 44px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .auth-title {
            font-family: var(--font-display);
            font-size: 1.6rem; font-weight: 800; color: var(--dark);
            margin-bottom: 6px; letter-spacing: -0.3px;
        }
        .auth-subtitle {
            color: var(--gray); font-size: 0.875rem; margin-bottom: 32px;
        }
        .auth-subtitle a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .auth-subtitle a:hover { text-decoration: underline; }
        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block; font-size: 0.82rem;
            font-weight: 600; color: var(--dark); margin-bottom: 7px;
        }
        .input-wrap { position: relative; }
        .input-wrap .input-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--gray); font-size: 1rem; pointer-events: none;
        }
        .input-wrap input {
            width: 100%;
            background: var(--light);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px 11px 40px;
            font-size: 0.875rem;
            font-family: var(--font-body);
            color: var(--dark);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,87,255,0.08);
            background: #fff;
        }
        .input-wrap input.is-error { border-color: var(--danger); }
        .toggle-password {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--gray); cursor: pointer; font-size: 1rem; padding: 0;
        }
        .toggle-password:hover { color: var(--primary); }
        .alert-error {
            background: #FEF2F2; border: 1px solid #FECACA;
            color: #DC2626; border-radius: 10px;
            padding: 11px 14px; font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-submit {
            width: 100%; background: var(--primary); color: #fff;
            border: none; border-radius: 10px; padding: 13px;
            font-size: 0.9rem; font-weight: 700;
            font-family: var(--font-body); cursor: pointer;
            transition: all 0.2s; margin-top: 4px;
        }
        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0,87,255,0.25);
        }
        .auth-register-link {
            text-align: center; font-size: 0.875rem;
            color: var(--gray); margin-top: 20px;
        }
        .auth-register-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .auth-register-link a:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .auth-container { grid-template-columns: 1fr; max-width: 440px; }
            .auth-left { display: none; }
            .auth-right { padding: 40px 28px; }
        }
        @media (max-width: 480px) {
            .auth-right { padding: 32px 20px; }
            .auth-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<div class="topbar">
    <i class="bi bi-shield-check"></i> Hàng chính hãng 100% &nbsp;|&nbsp;
    <i class="bi bi-truck"></i> Miễn phí ship đơn từ 500K &nbsp;|&nbsp;
    <i class="bi bi-arrow-repeat"></i> Đổi trả trong 30 ngày
</div>

<nav class="navbar">
    <a href="../index.php" class="navbar-brand">Phone<span>Store</span></a>
    <a href="../index.php" class="navbar-back"><i class="bi bi-arrow-left"></i> Về trang chủ</a>
</nav>

<div class="auth-wrapper">
    <div class="auth-container">

        <div class="auth-left">
            <div class="auth-left-brand">Phone<span>Store</span></div>
            <div class="auth-left-content">
                <div class="auth-left-title">Chào mừng<br>trở lại <span>👋</span></div>
                <p class="auth-left-desc">Đăng nhập để mua sắm, theo dõi đơn hàng và nhận ưu đãi độc quyền.</p>
                <ul class="auth-perks">
                    <li class="auth-perk">
                        <span class="auth-perk-icon"><i class="bi bi-bag-check"></i></span>
                        Theo dõi đơn hàng dễ dàng
                    </li>
                    <li class="auth-perk">
                        <span class="auth-perk-icon"><i class="bi bi-tag"></i></span>
                        Nhận ưu đãi & khuyến mãi riêng
                    </li>
                    <li class="auth-perk">
                        <span class="auth-perk-icon"><i class="bi bi-heart"></i></span>
                        Lưu sản phẩm yêu thích
                    </li>
                    <li class="auth-perk">
                        <span class="auth-perk-icon"><i class="bi bi-shield-check"></i></span>
                        Thanh toán an toàn & bảo mật
                    </li>
                </ul>
            </div>
            <div class="auth-left-footer">© 2024 PhoneStore. All rights reserved.</div>
        </div>

        <div class="auth-right">
            <h1 class="auth-title">Đăng nhập</h1>
            <p class="auth-subtitle">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>

            <?php if ($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="example@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="<?= $error ? 'is-error' : '' ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" name="password" id="passwordInput"
                               placeholder="Nhập mật khẩu"
                               class="<?= $error ? 'is-error' : '' ?>" required>
                        <button type="button" class="toggle-password" onclick="togglePass('passwordInput','eyeIcon')">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">
                    Đăng nhập &nbsp;<i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <div class="auth-register-link">
                Chưa có tài khoản? <a href="register.php">Tạo tài khoản mới</a>
            </div>
        </div>

    </div>
</div>

<script>
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>