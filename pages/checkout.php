<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$uid = (int) $_SESSION['user_id']; // ✅ FIX: ép kiểu int, tránh SQL Injection

// ── LẤY THÔNG TIN USER (prepared statement) ──────────────────────────────
$stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->bind_param("i", $uid);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();

// ── LẤY GIỎ HÀNG (prepared statement) ────────────────────────────────────
$stmt_cart = $conn->prepare("
    SELECT c.id AS cart_id, c.quantity,
        p.id AS product_id, p.name, p.price, p.thumbnail, p.stock,
        b.name AS brand_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE c.user_id = ?
");
$stmt_cart->bind_param("i", $uid);
$stmt_cart->execute();
$cart_items = $stmt_cart->get_result();

$items       = [];
$total_price = 0;
while ($item = $cart_items->fetch_assoc()) {
    $items[]      = $item;
    $total_price += $item['price'] * $item['quantity'];
}

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

$shipping_fee = $total_price >= 500000 ? 0 : 30000;
$final_total  = $total_price + $shipping_fee;

// ── XỬ LÝ ĐẶT HÀNG ──────────────────────────────────
$order_success = false;
$order_id      = null;
$payment_code  = null;
$errors        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name      = trim($_POST['full_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $note           = trim($_POST['note'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cod';

    // ✅ FIX: Validate chặt hơn
    if (empty($full_name))                          $errors[] = 'Vui lòng nhập họ tên!';
    if (empty($phone))                              $errors[] = 'Vui lòng nhập số điện thoại!';
    if (!preg_match('/^[0-9]{9,11}$/', $phone))     $errors[] = 'Số điện thoại không hợp lệ!';
    if (empty($address))                            $errors[] = 'Vui lòng nhập địa chỉ!';
    if (!in_array($payment_method, ['cod', 'bank_transfer'])) $errors[] = 'Phương thức thanh toán không hợp lệ!';

    // ✅ FIX: Kiểm tra stock trước khi tạo đơn
    foreach ($items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $errors[] = "Sản phẩm <b>{$item['name']}</b> không đủ hàng trong kho!";
        }
    }

    if (empty($errors)) {
        $payment_code = 'DH' . date('YmdHis') . rand(100, 999);

        // ✅ FIX logic thanh toán:
        // - bank_transfer: user đã thanh toán trong luồng fake payment → paid
        // - cod: thanh toán khi nhận hàng → unpaid cho đến khi delivered
        if ($payment_method === 'bank_transfer') {
            $payment_status = 'paid';
            $paid_at        = date('Y-m-d H:i:s');
        } else {
            $payment_status = 'unpaid';
            $paid_at        = null;
        }

        $stmt = $conn->prepare("
            INSERT INTO orders (user_id, full_name, phone, address, total_price,
                                payment_method, payment_status, payment_code, paid_at, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssississ",
            $uid, $full_name, $phone, $address, $final_total,
            $payment_method, $payment_status, $payment_code, $paid_at, $note
        );
        $stmt->execute();
        $order_id = $conn->insert_id;

        foreach ($items as $item) {
            $ins = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
            $ins->bind_param("iiii", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $ins->execute();
        }

        // ✅ FIX: Lưu thông tin vào session trước khi xóa giỏ hàng
        // tránh lỗi khi user F5 lại trang success (POST data mất)
        $_SESSION['last_order'] = [
            'payment_code'   => $payment_code,
            'full_name'      => $full_name,
            'phone'          => $phone,
            'address'        => $address,
            'payment_method' => $payment_method,
            'final_total'    => $final_total,
        ];

        $conn->query("DELETE FROM cart WHERE user_id = $uid");
        $order_success = true;
    }
}

// Lấy thông tin đơn từ session (tránh mất khi F5)
$last_order = $_SESSION['last_order'] ?? null;
if ($order_success && $last_order) {
    unset($_SESSION['last_order']); // Xóa sau khi dùng
}
?>

<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $order_success ? 'Đặt hàng thành công' : 'Thanh toán' ?> - Phone Store</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            .checkout-wrap {
                max-width: 1100px;
                margin: 0 auto;
                padding: 24px;
            }

            /* ── STEPS ── */
            .checkout-steps {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0;
                margin-bottom: 32px;
            }
            .step {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.82rem;
                font-weight: 700;
                color: var(--gray);
            }
            .step.active { color: var(--primary); }
            .step.done { color: #16A34A; }
            .step-num {
                width: 28px; height: 28px;
                border-radius: 50%;
                background: var(--border);
                color: var(--gray);
                display: flex; align-items: center; justify-content: center;
                font-size: 0.78rem; font-weight: 800;
            }
            .step.active .step-num { background: var(--primary); color: #fff; }
            .step.done .step-num { background: #16A34A; color: #fff; }
            .step-line {
                width: 60px; height: 2px;
                background: var(--border);
                margin: 0 8px;
            }
            .step-line.done { background: #16A34A; }

            /* ── LAYOUT ── */
            .checkout-grid {
                display: grid;
                grid-template-columns: 1fr 380px;
                gap: 24px;
                align-items: start;
            }

            /* ── FORM CARD ── */
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
                font-size: 0.95rem;
                font-weight: 800;
                color: var(--dark);
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .form-card-header i { color: var(--primary); }
            .form-card-body { padding: 20px 24px; }

            .form-row-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px;
            }
            .form-group { margin-bottom: 16px; }
            .form-group:last-child { margin-bottom: 0; }
            .form-label {
                display: block;
                font-size: 0.82rem;
                font-weight: 700;
                color: var(--dark);
                margin-bottom: 6px;
            }
            .form-label .req { color: #EF4444; }
            .form-input {
                width: 100%;
                background: var(--light);
                border: 1.5px solid var(--border);
                border-radius: 10px;
                padding: 10px 14px;
                font-size: 0.875rem;
                font-family: 'Nunito', sans-serif;
                color: var(--dark);
                outline: none;
                transition: border-color 0.2s;
            }
            .form-input:focus { border-color: var(--primary); background: #fff; }
            .form-input.error { border-color: #EF4444; }
            textarea.form-input { resize: vertical; min-height: 80px; }

            /* ── PAYMENT OPTIONS ── */
            .payment-option {
                border: 2px solid var(--border);
                border-radius: 12px;
                padding: 14px 16px;
                cursor: pointer;
                transition: all 0.2s;
                margin-bottom: 10px;
            }
            .payment-option:last-child { margin-bottom: 0; }
            .payment-option.selected {
                border-color: var(--primary);
                background: #EEF4FF;
            }
            .payment-option-header {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .payment-option input[type="radio"] {
                width: 18px; height: 18px;
                accent-color: var(--primary);
            }
            .payment-option-icon {
                width: 36px; height: 36px;
                border-radius: 8px;
                display: flex; align-items: center; justify-content: center;
                font-size: 1.2rem;
            }
            .payment-option-title {
                font-weight: 700;
                font-size: 0.9rem;
                color: var(--dark);
            }
            .payment-option-desc {
                font-size: 0.78rem;
                color: var(--gray);
                margin-top: 1px;
            }

            /* Bank transfer details */
            .bank-details {
                display: none;
                margin-top: 14px;
                padding-top: 14px;
                border-top: 1px solid var(--border);
            }
            .bank-details.show { display: block; }
            .bank-info-box {
                background: #F8FAFF;
                border: 1px solid #C7D9FF;
                border-radius: 10px;
                padding: 16px;
            }
            .bank-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 0.82rem;
                padding: 5px 0;
            }
            .bank-row .bk-label { color: var(--gray); }
            .bank-row .bk-value { font-weight: 700; color: var(--dark); }
            .bank-row .bk-value.highlight { color: var(--primary); font-size: 1rem; }
            .qr-placeholder {
                width: 120px; height: 120px;
                background: var(--light);
                border: 2px dashed var(--border);
                border-radius: 10px;
                display: flex; align-items: center; justify-content: center;
                margin: 12px auto 0;
                font-size: 0.75rem;
                color: var(--gray);
                text-align: center;
            }

            /* ── ORDER SUMMARY ── */
            .summary-card {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 16px;
                overflow: hidden;
                position: sticky;
                top: 88px;
            }
            .summary-header {
                padding: 16px 20px;
                border-bottom: 1px solid var(--border);
                font-size: 0.95rem;
                font-weight: 800;
                color: var(--dark);
                display: flex; align-items: center; gap: 8px;
            }
            .summary-header i { color: var(--primary); }
            .summary-items { padding: 12px 20px; }
            .summary-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 0;
                border-bottom: 1px solid var(--border);
            }
            .summary-item:last-child { border-bottom: none; }
            .summary-item-img {
                width: 48px; height: 48px;
                border-radius: 8px;
                background: var(--light);
                overflow: hidden;
                display: flex; align-items: center; justify-content: center;
                font-size: 1.5rem;
                flex-shrink: 0;
            }
            .summary-item-img img { width: 100%; height: 100%; object-fit: cover; }
            .summary-item-name {
                font-size: 0.82rem;
                font-weight: 600;
                color: var(--dark);
                flex: 1;
                line-height: 1.3;
            }
            .summary-item-qty {
                font-size: 0.75rem;
                color: var(--gray);
            }
            .summary-item-price {
                font-size: 0.85rem;
                font-weight: 800;
                color: var(--dark);
                white-space: nowrap;
            }
            .summary-footer {
                padding: 16px 20px;
                border-top: 1px solid var(--border);
                background: var(--light);
            }
            .summary-row {
                display: flex;
                justify-content: space-between;
                font-size: 0.85rem;
                margin-bottom: 8px;
            }
            .summary-row .lbl { color: var(--gray); }
            .summary-row .val { font-weight: 700; }
            .summary-row .val.free { color: #16A34A; }
            .summary-total-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid var(--border);
            }
            .summary-total-row .lbl { font-weight: 800; font-size: 0.9rem; }
            .summary-total-row .val { font-size: 1.2rem; font-weight: 800; color: #EF4444; }

            .btn-order {
                width: 100%;
                background: var(--primary);
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 14px;
                font-size: 0.95rem;
                font-weight: 700;
                font-family: 'Nunito', sans-serif;
                cursor: pointer;
                transition: all 0.2s;
                margin-top: 16px;
                display: flex; align-items: center; justify-content: center; gap: 8px;
            }
            .btn-order:hover {
                background: var(--primary-dark);
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(0,87,255,0.25);
            }
            .btn-order:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }

            .alert-error {
                background: #FEF2F2; border: 1px solid #FECACA;
                color: #DC2626; border-radius: 10px;
                padding: 12px 16px; font-size: 0.85rem;
                margin-bottom: 16px;
            }

            /* ══════════════════════════════
               FAKE PAYMENT OVERLAY
            ══════════════════════════════ */
            #paymentOverlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
                z-index: 9999;
                align-items: center;
                justify-content: center;
            }
            #paymentOverlay.show {
                display: flex;
            }
            .payment-modal {
                background: #fff;
                border-radius: 20px;
                padding: 40px 36px;
                text-align: center;
                max-width: 380px;
                width: 90%;
                box-shadow: 0 24px 60px rgba(0,0,0,0.2);
                animation: modalIn 0.3s ease;
            }
            @keyframes modalIn {
                from { transform: scale(0.85); opacity: 0; }
                to   { transform: scale(1);    opacity: 1; }
            }

            /* Icon vòng tròn xoay */
            .payment-spinner {
                width: 72px; height: 72px;
                border-radius: 50%;
                border: 5px solid #E0EAFF;
                border-top-color: var(--primary);
                animation: spin 0.9s linear infinite;
                margin: 0 auto 20px;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            /* Icon check khi xong */
            .payment-check {
                display: none;
                width: 72px; height: 72px;
                border-radius: 50%;
                background: #F0FDF4;
                border: 3px solid #BBF7D0;
                align-items: center; justify-content: center;
                font-size: 2rem;
                margin: 0 auto 20px;
                animation: popIn 0.4s cubic-bezier(0.34,1.56,0.64,1);
            }
            .payment-check.show {
                display: flex;
            }
            @keyframes popIn {
                from { transform: scale(0); }
                to   { transform: scale(1); }
            }

            .payment-modal-title {
                font-size: 1.1rem;
                font-weight: 800;
                color: var(--dark);
                margin-bottom: 6px;
            }
            .payment-modal-sub {
                font-size: 0.82rem;
                color: var(--gray);
                margin-bottom: 20px;
                line-height: 1.6;
            }

            /* Thanh progress */
            .payment-progress-wrap {
                background: #F0F4FF;
                border-radius: 99px;
                height: 8px;
                overflow: hidden;
                margin-bottom: 12px;
            }
            .payment-progress-bar {
                height: 100%;
                width: 0%;
                background: linear-gradient(90deg, var(--primary), #6EA8FE);
                border-radius: 99px;
                transition: width 0.3s ease;
            }
            .payment-progress-label {
                font-size: 0.78rem;
                color: var(--gray);
                font-weight: 600;
            }

            /* Các bước xử lý */
            .payment-steps-list {
                text-align: left;
                margin-top: 16px;
                border-top: 1px solid var(--border);
                padding-top: 14px;
            }
            .pstep {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 0.82rem;
                color: var(--gray);
                padding: 5px 0;
                transition: color 0.3s;
            }
            .pstep.active { color: var(--primary); font-weight: 700; }
            .pstep.done   { color: #16A34A; font-weight: 600; }
            .pstep-dot {
                width: 20px; height: 20px;
                border-radius: 50%;
                border: 2px solid var(--border);
                display: flex; align-items: center; justify-content: center;
                font-size: 0.65rem;
                flex-shrink: 0;
                transition: all 0.3s;
            }
            .pstep.active .pstep-dot {
                border-color: var(--primary);
                background: #EEF4FF;
                animation: pulse 1s infinite;
            }
            .pstep.done .pstep-dot {
                border-color: #16A34A;
                background: #F0FDF4;
                color: #16A34A;
            }
            @keyframes pulse {
                0%,100% { box-shadow: 0 0 0 0 rgba(0,87,255,0.3); }
                50%      { box-shadow: 0 0 0 5px rgba(0,87,255,0);  }
            }

            /* ── SUCCESS PAGE ── */
            .success-wrap {
                max-width: 600px;
                margin: 48px auto;
                padding: 24px;
                text-align: center;
            }
            .success-icon {
                width: 80px; height: 80px;
                background: #F0FDF4;
                border: 3px solid #BBF7D0;
                border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 2.2rem;
                margin: 0 auto 20px;
            }
            .success-title {
                font-size: 1.6rem;
                font-weight: 800;
                color: var(--dark);
                margin-bottom: 8px;
            }
            .success-sub {
                color: var(--gray);
                font-size: 0.9rem;
                margin-bottom: 28px;
                line-height: 1.6;
            }
            .order-info-box {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 24px;
                margin-bottom: 24px;
                text-align: left;
            }
            .order-info-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid var(--border);
                font-size: 0.875rem;
            }
            .order-info-row:last-child { border-bottom: none; }
            .order-info-row .lbl { color: var(--gray); }
            .order-info-row .val { font-weight: 700; color: var(--dark); }
            .order-code {
                font-family: monospace;
                background: #EEF4FF;
                color: var(--primary);
                padding: 3px 10px;
                border-radius: 6px;
                font-size: 0.9rem;
                font-weight: 800;
            }
            .bank-success-box {
                background: #F8FAFF;
                border: 1.5px solid #C7D9FF;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 20px;
                text-align: left;
            }
            .bank-success-title {
                font-weight: 800;
                font-size: 0.9rem;
                color: var(--primary);
                margin-bottom: 12px;
                display: flex; align-items: center; gap: 6px;
            }
            .success-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
            .btn-success-primary {
                background: var(--primary); color: #fff;
                border: none; border-radius: 10px;
                padding: 12px 24px;
                font-size: 0.9rem; font-weight: 700;
                font-family: 'Nunito', sans-serif;
                text-decoration: none; transition: background 0.2s;
            }
            .btn-success-primary:hover { background: var(--primary-dark); color: #fff; }
            .btn-success-outline {
                background: transparent; color: var(--gray);
                border: 1.5px solid var(--border); border-radius: 10px;
                padding: 12px 24px;
                font-size: 0.9rem; font-weight: 700;
                font-family: 'Nunito', sans-serif;
                text-decoration: none; transition: all 0.2s;
            }
            .btn-success-outline:hover { border-color: var(--primary); color: var(--primary); }

            /* Responsive */
            @media (max-width: 900px) {
                .checkout-grid { grid-template-columns: 1fr; }
                .summary-card { position: static; }
            }
            @media (max-width: 600px) {
                .checkout-wrap { padding: 12px; }
                .form-row-2 { grid-template-columns: 1fr; }
                .checkout-steps { gap: 4px; }
                .step-line { width: 30px; }
            }
        </style>
    </head>
    <body>

    <!-- ══ TOPBAR ══ -->
    <div class="topbar">
        <div class="topbar-inner">
            <span class="topbar-item"><i class="bi bi-shield-check"></i> Hàng chính hãng 100%</span>
            <span class="topbar-item"><i class="bi bi-truck"></i> Miễn phí ship đơn từ 500K</span>
            <span class="topbar-item"><i class="bi bi-arrow-repeat"></i> Đổi trả trong 30 ngày</span>
        </div>
    </div>

    <!-- ══ NAVBAR ══ -->
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="../index.php" class="navbar-brand">Phone<span>Store</span></a>
            <ul class="nav-links">
                <li><a href="products.php"><i class="bi bi-phone"></i> Sản phẩm</a></li>
            </ul>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-dropdown" style="margin-left:auto">
                <button class="btn-login user-dropdown-btn">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                    <i class="bi bi-chevron-down" style="font-size:0.65rem"></i>
                </button>
                <div class="user-dropdown-menu">
                    <div class="user-dropdown-header">
                        <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                        <div>
                            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                            <div class="user-role"><?= $_SESSION['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?></div>
                        </div>
                    </div>
                    <div class="user-dropdown-divider"></div>
                    <a href="orders.php" class="user-dropdown-item"><i class="bi bi-bag-check"></i> Đơn hàng của tôi</a>
                    <div class="user-dropdown-divider"></div>
                    <a href="../auth/logout.php" class="user-dropdown-item user-dropdown-logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($order_success): ?>
    <!-- ══════════════════════════════════════
        TRANG ĐẶT HÀNG THÀNH CÔNG
    ══════════════════════════════════════ -->
    <div class="success-wrap">
        <div class="success-icon">🎉</div>
        <div class="success-title">Đặt hàng thành công!</div>
        <p class="success-sub">
            Cảm ơn bạn đã mua hàng tại PhoneStore.<br>
            Chúng tôi sẽ liên hệ xác nhận đơn hàng trong thời gian sớm nhất.
        </p>

        <div class="order-info-box">
            <div class="order-info-row">
                <span class="lbl">Mã đơn hàng</span>
                <span class="val"><span class="order-code"><?= htmlspecialchars($payment_code) ?></span></span>
            </div>
            <div class="order-info-row">
                <span class="lbl">Người nhận</span>
                <span class="val"><?= htmlspecialchars($last_order['full_name'] ?? $_POST['full_name'] ?? '') ?></span>
            </div>
            <div class="order-info-row">
                <span class="lbl">Số điện thoại</span>
                <span class="val"><?= htmlspecialchars($last_order['phone'] ?? $_POST['phone'] ?? '') ?></span>
            </div>
            <div class="order-info-row">
                <span class="lbl">Địa chỉ giao hàng</span>
                <span class="val"><?= htmlspecialchars($last_order['address'] ?? $_POST['address'] ?? '') ?></span>
            </div>
            <div class="order-info-row">
                <span class="lbl">Phương thức thanh toán</span>
                <span class="val">
                    <?php
                    $pm = $last_order['payment_method'] ?? $_POST['payment_method'] ?? 'cod';
                    echo $pm === 'cod' ? '💵 Thanh toán khi nhận hàng' : '🏦 Chuyển khoản ngân hàng';
                    ?>
                </span>
            </div>
            <div class="order-info-row">
                <span class="lbl">Tổng tiền</span>
                <span class="val" style="color:#EF4444;font-size:1rem">
                    <?= number_format($last_order['final_total'] ?? $final_total, 0, ',', '.') ?>đ
                </span>
            </div>
        </div>

        <?php
        $pm = $last_order['payment_method'] ?? $_POST['payment_method'] ?? 'cod';
        if ($pm === 'bank_transfer'):
        ?>
        <!-- ✅ bank_transfer: đã thanh toán xong trong luồng → chỉ hiện badge xác nhận -->
        <div class="bank-success-box" style="text-align:center;">
            <div style="font-size:2.2rem;margin-bottom:10px;">✅</div>
            <div style="font-weight:800;font-size:1rem;color:#16A34A;margin-bottom:6px;">
                Thanh toán đã được xác nhận
            </div>
            <div style="font-size:0.82rem;color:var(--gray);line-height:1.6;">
                Giao dịch chuyển khoản của bạn đã được xử lý thành công.<br>
                Đơn hàng sẽ được xử lý và giao trong thời gian sớm nhất.
            </div>
        </div>
        <?php endif; ?>

        <div class="success-actions">
            <a href="../index.php" class="btn-success-primary">
                <i class="bi bi-house"></i> Về trang chủ
            </a>
            <a href="orders.php" class="btn-success-outline">
                <i class="bi bi-bag-check"></i> Xem đơn hàng
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- ══════════════════════════════════════
        TRANG CHECKOUT
    ══════════════════════════════════════ -->

    <!-- ══ FAKE PAYMENT OVERLAY ══ -->
    <div id="paymentOverlay">
        <div class="payment-modal">
            <!-- Spinner (hiện khi đang xử lý) -->
            <div class="payment-spinner" id="pmSpinner"></div>
            <!-- Check (hiện khi xong) -->
            <div class="payment-check" id="pmCheck">✅</div>

            <div class="payment-modal-title" id="pmTitle">Đang xử lý thanh toán...</div>
            <div class="payment-modal-sub" id="pmSub">
                Vui lòng không tắt trình duyệt trong lúc này
            </div>

            <!-- Progress bar -->
            <div class="payment-progress-wrap">
                <div class="payment-progress-bar" id="pmBar"></div>
            </div>
            <div class="payment-progress-label" id="pmLabel">0%</div>

            <!-- Các bước -->
            <div class="payment-steps-list">
                <div class="pstep" id="pstep1">
                    <div class="pstep-dot" id="dot1">1</div>
                    Xác thực thông tin đơn hàng
                </div>
                <div class="pstep" id="pstep2">
                    <div class="pstep-dot" id="dot2">2</div>
                    Kết nối cổng thanh toán
                </div>
                <div class="pstep" id="pstep3">
                    <div class="pstep-dot" id="dot3">3</div>
                    Xử lý giao dịch
                </div>
                <div class="pstep" id="pstep4">
                    <div class="pstep-dot" id="dot4">4</div>
                    Xác nhận & hoàn tất
                </div>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;">
        <div style="max-width:1100px;margin:0 auto;padding:0 24px;font-size:0.82rem;color:var(--gray);">
            <a href="../index.php" style="color:var(--gray);text-decoration:none;">Trang chủ</a>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <a href="cart.php" style="color:var(--gray);text-decoration:none;">Giỏ hàng</a>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <span style="color:var(--dark);font-weight:600;">Thanh toán</span>
        </div>
    </div>

    <div class="checkout-wrap">

        <!-- Steps -->
        <div class="checkout-steps">
            <div class="step done">
                <div class="step-num"><i class="bi bi-check"></i></div>
                <span>Giỏ hàng</span>
            </div>
            <div class="step-line done"></div>
            <div class="step active">
                <div class="step-num">2</div>
                <span>Thanh toán</span>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-num">3</div>
                <span>Hoàn tất</span>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= implode('<br>', $errors) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm">
        <div class="checkout-grid">

            <!-- ══ LEFT: FORM ══ -->
            <div>
                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-geo-alt-fill"></i> Thông tin giao hàng
                    </div>
                    <div class="form-card-body">
                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Họ và tên <span class="req">*</span></label>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?= htmlspecialchars($_POST['full_name'] ?? $user['full_name']) ?>"
                                    placeholder="Nguyễn Văn A" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Số điện thoại <span class="req">*</span></label>
                                <input type="text" name="phone" class="form-input"
                                    value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone']) ?>"
                                    placeholder="0901 234 567" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Địa chỉ giao hàng <span class="req">*</span></label>
                            <input type="text" name="address" class="form-input"
                                value="<?= htmlspecialchars($_POST['address'] ?? $user['address']) ?>"
                                placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ghi chú đơn hàng</label>
                            <textarea name="note" class="form-input"
                                    placeholder="Ghi chú thêm (giao giờ hành chính, để trước cửa...)"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-credit-card-fill"></i> Phương thức thanh toán
                    </div>
                    <div class="form-card-body">
                        <label class="payment-option selected" id="opt-cod" onclick="selectPayment('cod')">
                            <div class="payment-option-header">
                                <input type="radio" name="payment_method" value="cod" checked>
                                <div class="payment-option-icon" style="background:#FFF7ED;">💵</div>
                                <div>
                                    <div class="payment-option-title">Thanh toán khi nhận hàng (COD)</div>
                                    <div class="payment-option-desc">Trả tiền mặt khi shipper giao hàng</div>
                                </div>
                            </div>
                        </label>

                        <label class="payment-option" id="opt-bank" onclick="selectPayment('bank_transfer')">
                            <div class="payment-option-header">
                                <input type="radio" name="payment_method" value="bank_transfer">
                                <div class="payment-option-icon" style="background:#EEF4FF;">🏦</div>
                                <div>
                                    <div class="payment-option-title">Chuyển khoản ngân hàng</div>
                                    <div class="payment-option-desc">Chuyển khoản qua ngân hàng hoặc QR</div>
                                </div>
                            </div>
                            <div class="bank-details" id="bankDetails">
                                <div class="bank-info-box">
                                    <div class="bank-row">
                                        <span class="bk-label">Ngân hàng</span>
                                        <span class="bk-value">Vietcombank</span>
                                    </div>
                                    <div class="bank-row">
                                        <span class="bk-label">Số tài khoản</span>
                                        <span class="bk-value">1234 5678 9012</span>
                                    </div>
                                    <div class="bank-row">
                                        <span class="bk-label">Chủ tài khoản</span>
                                        <span class="bk-value">CONG TY PHONE STORE</span>
                                    </div>
                                    <div class="bank-row">
                                        <span class="bk-label">Số tiền</span>
                                        <span class="bk-value highlight"><?= number_format($final_total,0,',','.') ?>đ</span>
                                    </div>
                                    <div class="bank-row">
                                        <span class="bk-label">Nội dung CK</span>
                                        <span class="bk-value" style="color:var(--gray);font-style:italic">
                                            Tự động tạo sau khi đặt hàng
                                        </span>
                                    </div>
                                    <div class="qr-placeholder">
                                        <div><div style="font-size:2rem">📱</div>QR Code</div>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- ══ RIGHT: SUMMARY ══ -->
            <div>
                <div class="summary-card">
                    <div class="summary-header">
                        <i class="bi bi-receipt"></i> Đơn hàng của bạn
                    </div>
                    <div class="summary-items">
                        <?php foreach ($items as $item): ?>
                        <div class="summary-item">
                            <div class="summary-item-img">
                                <?php if ($item['thumbnail']): ?>
                                    <img src="../assets/images/products/<?= htmlspecialchars($item['thumbnail']) ?>"
                                        alt="<?= htmlspecialchars($item['name']) ?>">
                                <?php else: ?>📱<?php endif; ?>
                            </div>
                            <div class="summary-item-name">
                                <?= htmlspecialchars($item['name']) ?>
                                <div class="summary-item-qty">x<?= $item['quantity'] ?></div>
                            </div>
                            <div class="summary-item-price">
                                <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="summary-footer">
                        <div class="summary-row">
                            <span class="lbl">Tạm tính</span>
                            <span class="val"><?= number_format($total_price,0,',','.') ?>đ</span>
                        </div>
                        <div class="summary-row">
                            <span class="lbl">Phí vận chuyển</span>
                            <span class="val <?= $shipping_fee===0?'free':'' ?>">
                                <?= $shipping_fee===0 ? 'Miễn phí' : number_format($shipping_fee,0,',','.').'đ' ?>
                            </span>
                        </div>
                        <div class="summary-total-row">
                            <span class="lbl">Tổng cộng</span>
                            <span class="val"><?= number_format($final_total,0,',','.') ?>đ</span>
                        </div>
                        <button type="submit" class="btn-order" id="btnOrder">
                            <i class="bi bi-bag-check-fill"></i> Đặt hàng ngay
                        </button>
                        <a href="cart.php" style="display:block;text-align:center;margin-top:10px;font-size:0.82rem;color:var(--gray);text-decoration:none;">
                            <i class="bi bi-arrow-left"></i> Quay lại giỏ hàng
                        </a>
                    </div>
                </div>
            </div>

        </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ══ FOOTER ══ -->
    <footer style="margin-top:48px">
        <div class="container-main">
            <div class="footer-bottom" style="border-top:none;padding-top:0">
                <span>© 2024 PhoneStore. All rights reserved.</span>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ══ PAYMENT OPTION TOGGLE ══
    function selectPayment(method) {
        document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
        document.getElementById('opt-' + (method === 'cod' ? 'cod' : 'bank')).classList.add('selected');
        document.querySelectorAll('input[name="payment_method"]').forEach(r => {
            r.checked = r.value === method;
        });
        const bankDetails = document.getElementById('bankDetails');
        if (bankDetails) {
            bankDetails.classList.toggle('show', method === 'bank_transfer');
        }
    }

    // ══ FAKE PAYMENT PROCESSING ══
    const TOTAL_DURATION = 10000; // 10 giây

    // Kịch bản các bước: [thời điểm bắt đầu ms, tên step id, text title, text sub]
    const STEPS = [
        { at: 0,    stepId: 'pstep1', title: 'Đang xử lý thanh toán...', sub: 'Vui lòng không tắt trình duyệt' },
        { at: 2500, stepId: 'pstep2', title: 'Kết nối cổng thanh toán...', sub: 'Đang kết nối tới máy chủ ngân hàng' },
        { at: 5500, stepId: 'pstep3', title: 'Đang xử lý giao dịch...', sub: 'Hệ thống đang xác minh thông tin' },
        { at: 8000, stepId: 'pstep4', title: 'Hoàn tất đơn hàng...', sub: 'Đang lưu thông tin đơn hàng' },
    ];

    let currentStepIdx = -1;

    function setStepDone(id) {
        const el = document.getElementById(id);
        el.classList.remove('active');
        el.classList.add('done');
        // Đổi dot thành dấu check
        el.querySelector('.pstep-dot').textContent = '✓';
    }

    function setStepActive(id) {
        const el = document.getElementById(id);
        el.classList.add('active');
    }

    function startFakePayment() {
        const overlay  = document.getElementById('paymentOverlay');
        const bar      = document.getElementById('pmBar');
        const label    = document.getElementById('pmLabel');
        const title    = document.getElementById('pmTitle');
        const sub      = document.getElementById('pmSub');
        const spinner  = document.getElementById('pmSpinner');
        const check    = document.getElementById('pmCheck');

        overlay.classList.add('show');

        const startTime = Date.now();

        // Progress bar & label cập nhật mỗi 100ms
        const progressInterval = setInterval(() => {
            const elapsed  = Date.now() - startTime;
            const progress = Math.min((elapsed / TOTAL_DURATION) * 100, 100);
            bar.style.width   = progress + '%';
            label.textContent = Math.floor(progress) + '%';
        }, 100);

        // Kích hoạt từng step theo timeline
        STEPS.forEach((step, idx) => {
            setTimeout(() => {
                // Done bước trước
                if (idx > 0) setStepDone(STEPS[idx - 1].stepId);
                // Active bước hiện tại
                setStepActive(step.stepId);
                title.textContent = step.title;
                sub.textContent   = step.sub;
            }, step.at);
        });

        // Kết thúc sau TOTAL_DURATION
        setTimeout(() => {
            clearInterval(progressInterval);
            bar.style.width   = '100%';
            label.textContent = '100%';

            // Done bước cuối
            setStepDone(STEPS[STEPS.length - 1].stepId);

            // Đổi spinner → check
            spinner.style.display = 'none';
            check.classList.add('show');
            title.textContent = 'Thanh toán thành công!';
            sub.textContent   = 'Đang chuyển đến trang xác nhận...';

            // Submit form thật sau 1.2s (để user thấy trạng thái thành công)
            setTimeout(() => {
                document.getElementById('checkoutForm').submit();
            }, 1200);

        }, TOTAL_DURATION);
    }

    // Bắt sự kiện submit form → chặn lại, chạy fake payment trước
    document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
        // Validate cơ bản trước khi hiện overlay
        const fullName = document.querySelector('input[name="full_name"]').value.trim();
        const phone    = document.querySelector('input[name="phone"]').value.trim();
        const address  = document.querySelector('input[name="address"]').value.trim();

        if (!fullName || !phone || !address) {
            // Để form submit tự nhiên để server validate và hiện lỗi
            return true;
        }

        // ✅ FIX: chỉ chạy fake payment khi chuyển khoản
        // COD không cần xác thực thanh toán → submit thẳng
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
        if (paymentMethod !== 'bank_transfer') {
            return true; // COD: submit bình thường
        }

        // Chặn submit thật (chỉ với bank_transfer)
        e.preventDefault();

        // Disable nút để tránh bấm 2 lần
        document.getElementById('btnOrder').disabled = true;

        // Chạy fake payment
        startFakePayment();
    });

    // ══ USER DROPDOWN ══
    document.querySelector('.user-dropdown-btn')?.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelector('.user-dropdown-menu').classList.toggle('show');
    });
    document.addEventListener('click', function() {
        document.querySelector('.user-dropdown-menu')?.classList.remove('show');
    });
    </script>
    </body>
</html>