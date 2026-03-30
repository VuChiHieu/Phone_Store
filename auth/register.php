<?php
session_start();
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $password  = trim($_POST['password']);
    $confirm   = trim($_POST['confirm_password']);

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Email này đã được đăng ký!';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $phone, $hashed);

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Đã xảy ra lỗi, vui lòng thử lại!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Phone Store</title>
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
            --success: #059669;
            --font-display: 'Syne', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-body);
            background: #f0f4ff;
            min-height: 100vh;
            display: flex; flex-direction: column;
        }
        .topbar {
            background: var(--primary); color: #fff;
            text-align: center; font-size: 0.78rem; padding: 7px 16px;
        }
        .navbar {
            background: #fff; border-bottom: 1px solid var(--border);
            padding: 0 24px; height: 60px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .navbar-brand {
            font-family: var(--font-display); font-weight: 800;
            font-size: 1.3rem; color: var(--dark); text-decoration: none;
        }
        .navbar-brand span { color: var(--primary); }
        .navbar-back {
            display: flex; align-items: center; gap: 6px;
            color: var(--gray); text-decoration: none;
            font-size: 0.85rem; font-weight: 500; transition: color 0.2s;
        }
        .navbar-back:hover { color: var(--primary); }

        .auth-wrapper {
            flex: 1; display: flex;
            align-items: center; justify-content: center; padding: 40px 16px;
        }
        .auth-container {
            display: grid; grid-template-columns: 1fr 1.2fr;
            width: 100%; max-width: 960px;
            background: #fff; border-radius: 24px; overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        /* Left */
        .auth-left {
            background: linear-gradient(145deg, #0A0A0A 0%, #0d1b3e 100%);
            padding: 52px 40px;
            display: flex; flex-direction: column; justify-content: space-between;
            position: relative; overflow: hidden;
        }
        .auth-left::before {
            content: ''; position: absolute;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(0,87,255,0.3) 0%, transparent 70%);
            top: -80px; right: -80px;
        }
        .auth-left::after {
            content: ''; position: absolute;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(0,87,255,0.15) 0%, transparent 70%);
            bottom: -40px; left: -40px;
        }
        .auth-left-brand {
            font-family: var(--font-display); font-size: 1.5rem;
            font-weight: 800; color: #fff;
        }
        .auth-left-brand span { color: var(--primary); }
        .auth-left-content { position: relative; z-index: 1; }
        .auth-left-title {
            font-family: var(--font-display); font-size: 1.8rem;
            font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 14px;
        }
        .auth-left-title span { color: #60A5FA; }
        .auth-left-desc {
            color: rgba(255,255,255,0.55); font-size: 0.875rem;
            line-height: 1.7; margin-bottom: 28px;
        }
        .auth-steps { display: flex; flex-direction: column; gap: 14px; }
        .auth-step {
            display: flex; align-items: flex-start; gap: 12px;
        }
        .auth-step-num {
            width: 26px; height: 26px; flex-shrink: 0;
            background: var(--primary); color: #fff;
            border-radius: 50%; font-size: 0.75rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }
        .auth-step-text { color: rgba(255,255,255,0.7); font-size: 0.85rem; line-height: 1.5; }
        .auth-step-text strong { color: #fff; display: block; margin-bottom: 2px; }
        .auth-left-footer {
            color: rgba(255,255,255,0.3); font-size: 0.75rem; position: relative; z-index: 1;
        }

        /* Right */
        .auth-right { padding: 44px 44px; overflow-y: auto; }
        .auth-title {
            font-family: var(--font-display); font-size: 1.6rem;
            font-weight: 800; color: var(--dark); margin-bottom: 6px; letter-spacing: -0.3px;
        }
        .auth-subtitle {
            color: var(--gray); font-size: 0.875rem; margin-bottom: 28px;
        }
        .auth-subtitle a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .auth-subtitle a:hover { text-decoration: underline; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block; font-size: 0.82rem;
            font-weight: 600; color: var(--dark); margin-bottom: 6px;
        }
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .input-wrap { position: relative; }
        .input-wrap .input-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--gray); font-size: 1rem; pointer-events: none;
        }
        .input-wrap input {
            width: 100%;
            background: var(--light); border: 1.5px solid var(--border);
            border-radius: 10px; padding: 11px 14px 11px 40px;
            font-size: 0.875rem; font-family: var(--font-body);
            color: var(--dark); outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,87,255,0.08);
            background: #fff;
        }
        .input-wrap input.is-error { border-color: var(--danger); }
        .toggle-password {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--gray);
            cursor: pointer; font-size: 1rem; padding: 0;
        }
        .toggle-password:hover { color: var(--primary); }

        /* Password strength */
        .password-hint {
            font-size: 0.75rem; color: var(--gray); margin-top: 5px;
        }
        .strength-bar {
            height: 3px; border-radius: 2px; background: var(--border);
            margin-top: 6px; overflow: hidden;
        }
        .strength-fill {
            height: 100%; border-radius: 2px; width: 0;
            transition: width 0.3s, background 0.3s;
        }

        .alert-error {
            background: #FEF2F2; border: 1px solid #FECACA;
            color: #DC2626; border-radius: 10px;
            padding: 11px 14px; font-size: 0.85rem; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-success {
            background: #F0FDF4; border: 1px solid #BBF7D0;
            color: var(--success); border-radius: 10px;
            padding: 16px; font-size: 0.875rem; margin-bottom: 20px;
            text-align: center;
        }
        .alert-success .success-icon { font-size: 2rem; margin-bottom: 8px; display: block; }

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
        .auth-login-link {
            text-align: center; font-size: 0.875rem;
            color: var(--gray); margin-top: 16px;
        }
        .auth-login-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .auth-login-link a:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .auth-container { grid-template-columns: 1fr; max-width: 480px; }
            .auth-left { display: none; }
            .auth-right { padding: 36px 24px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
        @media (max-width: 480px) {
            .auth-right { padding: 28px 18px; }
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

        <!-- LEFT -->
        <div class="auth-left">
            <div class="auth-left-brand">Phone<span>Store</span></div>
            <div class="auth-left-content">
                <div class="auth-left-title">Tạo tài khoản<br><span>miễn phí 🎉</span></div>
                <p class="auth-left-desc">Chỉ mất 1 phút để tạo tài khoản và bắt đầu mua sắm ngay hôm nay.</p>
                <div class="auth-steps">
                    <div class="auth-step">
                        <span class="auth-step-num">1</span>
                        <div class="auth-step-text">
                            <strong>Điền thông tin</strong>
                            Họ tên, email và mật khẩu
                        </div>
                    </div>
                    <div class="auth-step">
                        <span class="auth-step-num">2</span>
                        <div class="auth-step-text">
                            <strong>Tạo tài khoản</strong>
                            Hoàn toàn miễn phí, không mất phí
                        </div>
                    </div>
                    <div class="auth-step">
                        <span class="auth-step-num">3</span>
                        <div class="auth-step-text">
                            <strong>Mua sắm ngay</strong>
                            Khám phá hàng nghìn sản phẩm
                        </div>
                    </div>
                </div>
            </div>
            <div class="auth-left-footer">© 2024 PhoneStore. All rights reserved.</div>
        </div>

        <!-- RIGHT -->
        <div class="auth-right">
            <h1 class="auth-title">Đăng ký tài khoản</h1>
            <p class="auth-subtitle">Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>

            <?php if ($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert-success">
                <span class="success-icon">🎉</span>
                <strong>Đăng ký thành công!</strong><br>
                Tài khoản của bạn đã được tạo. <a href="login.php" style="color:var(--success);font-weight:600">Đăng nhập ngay →</a>
            </div>
            <?php else: ?>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Họ và tên <span class="required">*</span></label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="full_name" placeholder="Nguyễn Văn A"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                   class="<?= $error ? 'is-error' : '' ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Số điện thoại</label>
                        <div class="input-wrap">
                            <i class="bi bi-telephone input-icon"></i>
                            <input type="text" name="phone" placeholder="0901 234 567"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="example@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="<?= $error ? 'is-error' : '' ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mật khẩu <span class="required">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" name="password" id="passwordInput"
                               placeholder="Tối thiểu 6 ký tự"
                               class="<?= $error ? 'is-error' : '' ?>"
                               oninput="checkStrength(this.value)" required>
                        <button type="button" class="toggle-password"
                                onclick="togglePass('passwordInput','eyeIcon1')">
                            <i class="bi bi-eye" id="eyeIcon1"></i>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <div class="password-hint" id="strengthText">Nhập mật khẩu để kiểm tra độ mạnh</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Xác nhận mật khẩu <span class="required">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-lock-fill input-icon"></i>
                        <input type="password" name="confirm_password" id="confirmInput"
                               placeholder="Nhập lại mật khẩu"
                               class="<?= $error ? 'is-error' : '' ?>" required>
                        <button type="button" class="toggle-password"
                                onclick="togglePass('confirmInput','eyeIcon2')">
                            <i class="bi bi-eye" id="eyeIcon2"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    Tạo tài khoản &nbsp;<i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <div class="auth-login-link">
                Đã có tài khoản? <a href="login.php">Đăng nhập</a>
            </div>

            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w: '0%',   bg: '#E5E7EB', label: 'Nhập mật khẩu để kiểm tra độ mạnh' },
        { w: '25%',  bg: '#EF4444', label: '😟 Yếu — nên dùng ít nhất 6 ký tự' },
        { w: '50%',  bg: '#F97316', label: '😐 Trung bình — thêm số hoặc ký tự đặc biệt' },
        { w: '75%',  bg: '#EAB308', label: '🙂 Khá tốt — thêm chữ hoa để tốt hơn' },
        { w: '90%',  bg: '#22C55E', label: '😊 Tốt' },
        { w: '100%', bg: '#059669', label: '💪 Rất mạnh' },
    ];
    const lv = val.length === 0 ? 0 : Math.min(score + 1, 5);
    fill.style.width      = levels[lv].w;
    fill.style.background = levels[lv].bg;
    text.textContent      = levels[lv].label;
    text.style.color      = levels[lv].bg;
}
</script>
</body>
</html>