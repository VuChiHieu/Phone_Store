<?php
session_start();
require_once '../config.php';
include '../includes/navbar.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$uid     = $_SESSION['user_id'];
$user    = $conn->query("SELECT * FROM users WHERE id = $uid")->fetch_assoc();
$success = '';
$error   = '';

// ── CẬP NHẬT THÔNG TIN ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address']);

    if (empty($full_name)) {
        $error = 'Vui lòng nhập họ tên!';
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, address=? WHERE id=?");
        $stmt->bind_param("sssi", $full_name, $phone, $address, $uid);
        $stmt->execute();
        $_SESSION['full_name'] = $full_name;
        $user['full_name']     = $full_name;
        $user['phone']         = $phone;
        $user['address']       = $address;
        $success = 'Cập nhật thông tin thành công!';
    }
}

// ── ĐỔI MẬT KHẨU ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_pass  = $_POST['old_password'];
    $new_pass  = $_POST['new_password'];
    $confirm   = $_POST['confirm_password'];

    if (!password_verify($old_pass, $user['password'])) {
        $error = 'Mật khẩu hiện tại không đúng!';
    } elseif (strlen($new_pass) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
    } elseif ($new_pass !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $uid);
        $stmt->execute();
        $success = 'Đổi mật khẩu thành công!';
    }
}

// Đếm giỏ hàng & đơn hàng
$cart_count  = $conn->query("SELECT SUM(quantity) AS t FROM cart WHERE user_id=$uid")->fetch_assoc()['t'] ?? 0;
$order_count = $conn->query("SELECT COUNT(*) AS t FROM orders WHERE user_id=$uid")->fetch_assoc()['t'];
$total_spent = $conn->query("SELECT SUM(total_price) AS t FROM orders WHERE user_id=$uid AND status='delivered'")->fetch_assoc()['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt tài khoản - Phone Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 24px;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* ── SIDEBAR ── */
        .profile-sidebar { position: sticky; top: 88px; }

        .profile-card {
            background: linear-gradient(145deg, #0A0A0A, #0d1b3e);
            border-radius: 16px;
            padding: 24px;
            color: #fff;
            text-align: center;
            margin-bottom: 14px;
            position: relative;
            overflow: hidden;
        }
        .profile-card::before {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(0,87,255,0.25) 0%, transparent 70%);
            top: -60px; right: -60px;
        }
        .profile-avatar {
            width: 72px; height: 72px;
            background: rgba(0,87,255,0.3);
            border: 3px solid rgba(0,87,255,0.4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: #60A5FA;
            margin: 0 auto 12px;
            position: relative; z-index: 1;
        }
        .profile-name {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 4px;
            position: relative; z-index: 1;
        }
        .profile-email {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            margin-bottom: 16px;
            position: relative; z-index: 1;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            position: relative; z-index: 1;
        }
        .profile-stat {
            background: rgba(255,255,255,0.07);
            border-radius: 8px;
            padding: 8px;
        }
        .profile-stat-num {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
        }
        .profile-stat-label {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-nav {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }
        .profile-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: var(--gray);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            border-left: 3px solid transparent;
            border-bottom: 1px solid var(--border);
            transition: all 0.2s;
        }
        .profile-nav-item:last-child { border-bottom: none; }
        .profile-nav-item:hover { background: var(--light); color: var(--primary); }
        .profile-nav-item.active {
            color: var(--primary);
            background: #EEF4FF;
            border-left-color: var(--primary);
        }
        .profile-nav-item i { font-size: 1rem; width: 18px; }

        /* ── MAIN ── */
        .form-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .form-card-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-card-title {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--dark);
        }
        .form-card-title::before {
            content: '';
            display: inline-block;
            width: 4px; height: 16px;
            background: var(--primary);
            border-radius: 2px;
            margin-right: 8px;
            vertical-align: middle;
        }
        .form-card-body { padding: 24px; }

        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 6px;
        }
        .input-wrap { position: relative; }
        .input-wrap .input-icon {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%);
            color: var(--gray); font-size: 0.95rem;
            pointer-events: none;
        }
        .form-input {
            width: 100%;
            background: var(--light);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px 10px 38px;
            font-size: 0.875rem;
            font-family: 'Nunito', sans-serif;
            color: var(--dark);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,87,255,0.08);
            background: #fff;
        }
        .form-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .toggle-password {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--gray); cursor: pointer; font-size: 1rem;
        }
        .toggle-password:hover { color: var(--primary); }

        .btn-save {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 11px 24px;
            font-size: 0.9rem;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(0,87,255,0.25);
        }

        /* Strength bar */
        .strength-bar { height: 3px; border-radius: 2px; background: var(--border); margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; width: 0; transition: width 0.3s, background 0.3s; }
        .strength-text { font-size: 0.72rem; margin-top: 4px; color: var(--gray); }

        /* Alert */
        .alert-success { background:#F0FDF4; border:1px solid #BBF7D0; color:#16A34A; border-radius:10px; padding:12px 16px; font-size:0.875rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; font-weight:600; }
        .alert-error   { background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; border-radius:10px; padding:12px 16px; font-size:0.875rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; }

        /* Danger zone */
        .danger-card {
            background: #fff;
            border: 1.5px solid #FECACA;
            border-radius: 16px;
            padding: 20px 24px;
        }
        .danger-title {
            font-weight: 800;
            font-size: 0.9rem;
            color: #DC2626;
            margin-bottom: 6px;
            display: flex; align-items: center; gap: 6px;
        }
        .danger-desc { font-size: 0.82rem; color: var(--gray); margin-bottom: 14px; line-height: 1.5; }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-wrap { grid-template-columns: 1fr; }
            .profile-sidebar { position: static; }
            .form-grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .profile-wrap { padding: 16px; }
            .form-card-body { padding: 16px; }
        }
    </style>
</head>
<body>


<!-- ══ BREADCRUMB ══ -->
<div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;">
    <div style="max-width:900px;margin:0 auto;padding:0 24px;font-size:0.82rem;color:var(--gray);">
        <a href="../index.php" style="color:var(--gray);text-decoration:none;">Trang chủ</a>
        <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
        <span style="color:var(--dark);font-weight:600;">Cài đặt tài khoản</span>
    </div>
</div>

<div class="profile-wrap">

    <!-- ══ SIDEBAR ══ -->
    <div class="profile-sidebar">

        <!-- Avatar card -->
        <div class="profile-card">
            <div class="profile-avatar">
                <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
            </div>
            <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="profile-stat-num"><?= $order_count ?></div>
                    <div class="profile-stat-label">Đơn hàng</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-num" style="font-size:0.85rem">
                        <?= $total_spent > 0 ? number_format($total_spent/1000000, 1).'M' : '0' ?>
                    </div>
                    <div class="profile-stat-label">Chi tiêu</div>
                </div>
            </div>
        </div>

        <!-- Nav -->
        <div class="profile-nav">
            <a href="profile.php" class="profile-nav-item active">
                <i class="bi bi-person-fill"></i> Thông tin cá nhân
            </a>
            <a href="orders.php" class="profile-nav-item">
                <i class="bi bi-bag-check"></i> Đơn hàng của tôi
            </a>
            <a href="products.php" class="profile-nav-item">
                <i class="bi bi-phone"></i> Tiếp tục mua sắm
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="../admin/index.php" class="profile-nav-item">
                <i class="bi bi-speedometer2"></i> Trang quản trị
            </a>
            <?php endif; ?>
            <a href="../auth/logout.php" class="profile-nav-item" style="color:#EF4444">
                <i class="bi bi-box-arrow-right"></i> Đăng xuất
            </a>
        </div>
    </div>

    <!-- ══ MAIN ══ -->
    <div>

        <?php if ($success): ?>
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
        <?php endif; ?>

        <!-- Thông tin cá nhân -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-title">Thông tin cá nhân</div>
            </div>
            <div class="form-card-body">
                <form method="POST">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Họ và tên *</label>
                            <div class="input-wrap">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" name="full_name" class="form-input"
                                       value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email (không thể thay đổi)</label>
                            <div class="input-wrap">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" class="form-input"
                                       value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Số điện thoại</label>
                            <div class="input-wrap">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="text" name="phone" class="form-input"
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                       placeholder="0901 234 567">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quyền tài khoản</label>
                            <div class="input-wrap">
                                <i class="bi bi-shield input-icon"></i>
                                <input type="text" class="form-input"
                                       value="<?= $user['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?>"
                                       disabled>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Địa chỉ giao hàng mặc định</label>
                        <div class="input-wrap">
                            <i class="bi bi-geo-alt input-icon"></i>
                            <input type="text" name="address" class="form-input"
                                   value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                   placeholder="Số nhà, tên đường, phường, quận, tỉnh/thành phố">
                        </div>
                    </div>
                    <button type="submit" name="update_info" class="btn-save">
                        <i class="bi bi-check-lg"></i> Lưu thay đổi
                    </button>
                </form>
            </div>
        </div>

        <!-- Đổi mật khẩu -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-title">Đổi mật khẩu</div>
            </div>
            <div class="form-card-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Mật khẩu hiện tại *</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="old_password" id="oldPass" class="form-input"
                                   placeholder="Nhập mật khẩu hiện tại" required>
                            <button type="button" class="toggle-password"
                                    onclick="togglePass('oldPass','eye0')">
                                <i class="bi bi-eye" id="eye0"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Mật khẩu mới *</label>
                            <div class="input-wrap">
                                <i class="bi bi-lock-fill input-icon"></i>
                                <input type="password" name="new_password" id="newPass" class="form-input"
                                       placeholder="Tối thiểu 6 ký tự"
                                       oninput="checkStrength(this.value)" required>
                                <button type="button" class="toggle-password"
                                        onclick="togglePass('newPass','eye1')">
                                    <i class="bi bi-eye" id="eye1"></i>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                            <div class="strength-text" id="strengthText"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Xác nhận mật khẩu mới *</label>
                            <div class="input-wrap">
                                <i class="bi bi-lock-fill input-icon"></i>
                                <input type="password" name="confirm_password" id="confirmPass" class="form-input"
                                       placeholder="Nhập lại mật khẩu mới" required>
                                <button type="button" class="toggle-password"
                                        onclick="togglePass('confirmPass','eye2')">
                                    <i class="bi bi-eye" id="eye2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn-save">
                        <i class="bi bi-shield-lock"></i> Đổi mật khẩu
                    </button>
                </form>
            </div>
        </div>

        <!-- Thông tin tài khoản -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-title">Thông tin tài khoản</div>
            </div>
            <div class="form-card-body">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                    <div style="background:var(--light);border-radius:10px;padding:14px;text-align:center">
                        <div style="font-size:1.5rem;font-weight:800;color:var(--primary)"><?= $order_count ?></div>
                        <div style="font-size:0.75rem;color:var(--gray);font-weight:600;margin-top:2px">Tổng đơn hàng</div>
                    </div>
                    <div style="background:var(--light);border-radius:10px;padding:14px;text-align:center">
                        <div style="font-size:1.1rem;font-weight:800;color:#EF4444">
                            <?= $total_spent > 0 ? number_format($total_spent,0,',','.').'đ' : '0đ' ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--gray);font-weight:600;margin-top:2px">Tổng chi tiêu</div>
                    </div>
                    <div style="background:var(--light);border-radius:10px;padding:14px;text-align:center">
                        <div style="font-size:0.9rem;font-weight:800;color:var(--dark)">
                            <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--gray);font-weight:600;margin-top:2px">Ngày tham gia</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        { w:'0%',   bg:'#E5E7EB', label:'' },
        { w:'25%',  bg:'#EF4444', label:'😟 Yếu' },
        { w:'50%',  bg:'#F97316', label:'😐 Trung bình' },
        { w:'75%',  bg:'#EAB308', label:'🙂 Khá tốt' },
        { w:'90%',  bg:'#22C55E', label:'😊 Tốt' },
        { w:'100%', bg:'#059669', label:'💪 Rất mạnh' },
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