<?php
    session_start();
    require_once '../config.php';

    $success = false;
    $error   = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name']);
        $email     = trim($_POST['email']);
        $phone     = trim($_POST['phone'] ?? '');
        $message   = trim($_POST['message']);

        if (empty($full_name) || empty($email) || empty($message)) {
            $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ!';
        } else {
            $stmt = $conn->prepare("INSERT INTO contacts (full_name, email, phone, message) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $full_name, $email, $phone, $message);
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Đã có lỗi xảy ra, vui lòng thử lại!';
            }
        }
    }

    // Đếm giỏ hàng
    $cart_count = 0;
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $r   = $conn->query("SELECT SUM(quantity) AS total FROM cart WHERE user_id = $uid");
        $cart_count = $r->fetch_assoc()['total'] ?? 0;
    }
?>
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Liên hệ - Phone Store</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            .contact-wrap {
                max-width: 1100px;
                margin: 0 auto;
                padding: 36px 24px;
            }
            .contact-grid {
                display: grid;
                grid-template-columns: 1fr 1.4fr;
                gap: 24px;
                margin-bottom: 24px;
            }

            /* ── INFO CARD ── */
            .info-card {
                background: linear-gradient(145deg, #0A0A0A, #0d1b3e);
                border-radius: 16px;
                padding: 32px 28px;
                color: #fff;
                position: relative;
                overflow: hidden;
            }
            .info-card::before {
                content: '';
                position: absolute;
                width: 250px; height: 250px;
                background: radial-gradient(circle, rgba(0,87,255,0.25) 0%, transparent 70%);
                top: -60px; right: -60px;
            }
            .info-card::after {
                content: '';
                position: absolute;
                width: 150px; height: 150px;
                background: radial-gradient(circle, rgba(0,87,255,0.15) 0%, transparent 70%);
                bottom: -30px; left: -30px;
            }
            .info-card-title {
                font-size: 1.4rem;
                font-weight: 800;
                margin-bottom: 8px;
                position: relative; z-index: 1;
            }
            .info-card-sub {
                color: rgba(255,255,255,0.55);
                font-size: 0.875rem;
                line-height: 1.6;
                margin-bottom: 32px;
                position: relative; z-index: 1;
            }
            .info-items {
                display: flex;
                flex-direction: column;
                gap: 20px;
                position: relative; z-index: 1;
            }
            .info-item {
                display: flex;
                align-items: flex-start;
                gap: 14px;
            }
            .info-item-icon {
                width: 42px; height: 42px;
                background: rgba(0,87,255,0.2);
                border: 1px solid rgba(0,87,255,0.3);
                border-radius: 10px;
                display: flex; align-items: center; justify-content: center;
                font-size: 1.1rem;
                color: #60A5FA;
                flex-shrink: 0;
            }
            .info-item-label {
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
                color: rgba(255,255,255,0.4);
                margin-bottom: 3px;
            }
            .info-item-value {
                font-size: 0.9rem;
                font-weight: 600;
                color: #fff;
                line-height: 1.4;
            }
            .info-item-value a {
                color: #60A5FA;
                text-decoration: none;
            }
            .info-item-value a:hover { text-decoration: underline; }

            .info-divider {
                height: 1px;
                background: rgba(255,255,255,0.08);
                margin: 24px 0;
                position: relative; z-index: 1;
            }
            .social-links {
                display: flex;
                gap: 10px;
                position: relative; z-index: 1;
            }
            .social-btn {
                width: 38px; height: 38px;
                background: rgba(255,255,255,0.08);
                border: 1px solid rgba(255,255,255,0.12);
                border-radius: 8px;
                display: flex; align-items: center; justify-content: center;
                color: rgba(255,255,255,0.6);
                text-decoration: none;
                font-size: 1rem;
                transition: all 0.2s;
            }
            .social-btn:hover {
                background: rgba(0,87,255,0.3);
                border-color: rgba(0,87,255,0.5);
                color: #60A5FA;
            }

            /* ── FORM CARD ── */
            .form-card {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 32px 28px;
            }
            .form-card-title {
                font-size: 1.2rem;
                font-weight: 800;
                color: var(--dark);
                margin-bottom: 6px;
            }
            .form-card-sub {
                font-size: 0.875rem;
                color: var(--gray);
                margin-bottom: 24px;
            }
            .form-row-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px;
            }
            .form-group { margin-bottom: 16px; }
            .form-label {
                display: block;
                font-size: 0.82rem;
                font-weight: 700;
                color: var(--dark);
                margin-bottom: 6px;
            }
            .form-label .req { color: #EF4444; }
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
            textarea.form-input {
                padding-top: 12px;
                resize: vertical;
                min-height: 130px;
            }
            .btn-submit {
                width: 100%;
                background: var(--primary);
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 13px;
                font-size: 0.95rem;
                font-weight: 700;
                font-family: 'Nunito', sans-serif;
                cursor: pointer;
                transition: all 0.2s;
                display: flex; align-items: center; justify-content: center; gap: 8px;
            }
            .btn-submit:hover {
                background: var(--primary-dark);
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(0,87,255,0.25);
            }
            .alert-success {
                background: #F0FDF4; border: 1px solid #BBF7D0;
                color: #16A34A; border-radius: 10px;
                padding: 14px 16px; font-size: 0.875rem;
                margin-bottom: 20px;
                display: flex; align-items: flex-start; gap: 10px;
            }
            .alert-error {
                background: #FEF2F2; border: 1px solid #FECACA;
                color: #DC2626; border-radius: 10px;
                padding: 12px 16px; font-size: 0.85rem;
                margin-bottom: 16px;
                display: flex; align-items: center; gap: 8px;
            }

            /* ── MAP ── */
            .map-section {
                border-radius: 16px;
                overflow: hidden;
                border: 1px solid var(--border);
                position: relative;
            }
            .map-section iframe {
                width: 100%;
                height: 420px;
                border: none;
                display: block;
            }
            .map-overlay {
                position: absolute;
                top: 16px; left: 16px;
                background: #fff;
                border-radius: 10px;
                padding: 12px 16px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.12);
                font-size: 0.82rem;
            }
            .map-overlay-title {
                font-weight: 800;
                color: var(--dark);
                margin-bottom: 3px;
                display: flex; align-items: center; gap: 6px;
            }
            .map-overlay-title i { color: var(--primary); }
            .map-overlay-addr { color: var(--gray); font-size: 0.78rem; }

            /* Page header */
            .page-header {
                text-align: center;
                margin-bottom: 36px;
            }
            .page-header-label {
                display: inline-block;
                background: #EEF4FF;
                color: var(--primary);
                font-size: 0.75rem;
                font-weight: 700;
                letter-spacing: 2px;
                text-transform: uppercase;
                padding: 5px 14px;
                border-radius: 100px;
                margin-bottom: 12px;
            }
            .page-header h1 {
                font-size: 1.8rem;
                font-weight: 800;
                color: var(--dark);
                margin-bottom: 8px;
            }
            .page-header p {
                color: var(--gray);
                font-size: 0.9rem;
            }

            /* Responsive */
            @media (max-width: 900px) {
                .contact-grid { grid-template-columns: 1fr; }
                .info-card { padding: 24px 20px; }
            }
            @media (max-width: 600px) {
                .contact-wrap { padding: 16px; }
                .form-row-2 { grid-template-columns: 1fr; }
                .map-section iframe { height: 280px; }
                .form-card { padding: 20px 16px; }
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
            <span class="topbar-item"><i class="bi bi-headset"></i> Hotline: 1800 2097</span>
        </div>
    </div>

    <!-- ══ NAVBAR ══ -->
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="../index.php" class="navbar-brand">Phone<span>Store</span></a>
            <div class="search-wrap">
                <input type="text" id="searchInput" placeholder="Bạn muốn tìm gì hôm nay?"
                    onkeydown="if(event.key==='Enter') window.location='products.php?q='+encodeURIComponent(this.value)">
                <button class="search-btn" onclick="window.location='products.php?q='+encodeURIComponent(document.getElementById('searchInput').value)">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <ul class="nav-links">
                <li><a href="products.php"><i class="bi bi-phone"></i> Sản phẩm</a></li>
                <li><a href="contact.php" class="active"><i class="bi bi-headset"></i> Liên hệ</a></li>
            </ul>
            <a href="cart.php" class="cart-link">
                <i class="bi bi-bag" style="font-size:1.1rem"></i> Giỏ hàng
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-dropdown">
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
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="../admin/index.php" class="user-dropdown-item"><i class="bi bi-speedometer2"></i> Trang quản trị</a>
                    <?php endif; ?>
                    <a href="orders.php" class="user-dropdown-item"><i class="bi bi-bag-check"></i> Đơn hàng của tôi</a>
                    <a href="profile.php" class="user-dropdown-item"><i class="bi bi-gear"></i> Cài đặt tài khoản</a>
                    <div class="user-dropdown-divider"></div>
                    <a href="../auth/logout.php" class="user-dropdown-item user-dropdown-logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
                </div>
            </div>
            <?php else: ?>
                <a href="../auth/login.php" class="btn-login"><i class="bi bi-person"></i> Đăng nhập</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ══ BREADCRUMB ══ -->
    <div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;">
        <div style="max-width:1100px;margin:0 auto;padding:0 24px;font-size:0.82rem;color:var(--gray);">
            <a href="../index.php" style="color:var(--gray);text-decoration:none;">Trang chủ</a>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <span style="color:var(--dark);font-weight:600;">Liên hệ</span>
        </div>
    </div>

    <div class="contact-wrap">

        <!-- Page header -->
        <div class="page-header">
            <span class="page-header-label">✦ Hỗ trợ khách hàng</span>
            <h1>Liên hệ với chúng tôi</h1>
            <p>Chúng tôi luôn sẵn sàng hỗ trợ bạn. Hãy để lại tin nhắn hoặc liên hệ trực tiếp!</p>
        </div>

        <!-- Contact grid -->
        <div class="contact-grid">

            <!-- ══ THÔNG TIN LIÊN HỆ ══ -->
            <div class="info-card">
                <div class="info-card-title">Thông tin liên hệ</div>
                <p class="info-card-sub">Liên hệ với chúng tôi qua các kênh bên dưới hoặc điền form để được hỗ trợ nhanh nhất.</p>

                <div class="info-items">
                    <div class="info-item">
                        <div class="info-item-icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <div>
                            <div class="info-item-label">Địa chỉ</div>
                            <div class="info-item-value">123 Nguyễn Huệ, Quận 1<br>TP. Hồ Chí Minh</div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-icon"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <div class="info-item-label">Hotline (Miễn phí)</div>
                            <div class="info-item-value">
                                <a href="tel:18002097">1800 2097</a>
                            </div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-icon"><i class="bi bi-envelope-fill"></i></div>
                        <div>
                            <div class="info-item-label">Email</div>
                            <div class="info-item-value">
                                <a href="mailto:support@phonestore.com">support@phonestore.com</a>
                            </div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-icon"><i class="bi bi-clock-fill"></i></div>
                        <div>
                            <div class="info-item-label">Giờ làm việc</div>
                            <div class="info-item-value">Thứ 2 – Chủ nhật<br>8:00 – 21:00</div>
                        </div>
                    </div>
                </div>

                <div class="info-divider"></div>

                <div style="font-size:0.78rem;color:rgba(255,255,255,0.4);margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;">
                    Mạng xã hội
                </div>
                <div class="social-links">
                    <a href="#" class="social-btn"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-tiktok"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-youtube"></i></a>
                </div>
            </div>

            <!-- ══ FORM LIÊN HỆ ══ -->
            <div class="form-card">
                <div class="form-card-title">Gửi tin nhắn cho chúng tôi</div>
                <p class="form-card-sub">Chúng tôi sẽ phản hồi trong vòng 24 giờ làm việc.</p>

                <?php if ($success): ?>
                <div class="alert-success">
                    <i class="bi bi-check-circle-fill" style="font-size:1.2rem;flex-shrink:0"></i>
                    <div>
                        <strong>Gửi thành công!</strong><br>
                        Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất có thể!
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Họ và tên <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?= htmlspecialchars($_POST['full_name'] ?? ($user['full_name'] ?? '')) ?>"
                                    placeholder="Nguyễn Văn A" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Số điện thoại</label>
                            <div class="input-wrap">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="text" name="phone" class="form-input"
                                    value="<?= htmlspecialchars($_POST['phone'] ?? ($user['phone'] ?? '')) ?>"
                                    placeholder="0901 234 567">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" name="email" class="form-input"
                                value="<?= htmlspecialchars($_POST['email'] ?? ($user['email'] ?? '')) ?>"
                                placeholder="example@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nội dung <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="bi bi-chat-text input-icon" style="top:14px;transform:none"></i>
                            <textarea name="message" class="form-input"
                                    placeholder="Bạn cần hỗ trợ gì? Hãy mô tả chi tiết để chúng tôi có thể giúp bạn tốt nhất..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-send-fill"></i> Gửi tin nhắn
                    </button>
                </form>
            </div>

        </div>

        <!-- ══ GOOGLE MAPS ══ -->
        <div class="map-section">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.4245!2d106.7009!3d10.7769!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f3a9d8d1bb3%3A0x9c0d04be4a7e6f08!2sNguy%E1%BB%85n%20Hu%E1%BB%87%2C%20B%E1%BA%BFn%20Ngh%C3%A9%2C%20Qu%E1%BA%ADn%201%2C%20Th%C3%A0nh%20ph%E1%BB%91%20H%E1%BB%93%20Ch%C3%AD%20Minh!5e0!3m2!1svi!2svn!4v1234567890"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
            <div class="map-overlay">
                <div class="map-overlay-title">
                    <i class="bi bi-geo-alt-fill"></i> PhoneStore
                </div>
                <div class="map-overlay-addr">123 Nguyễn Huệ, Quận 1, TP.HCM</div>
            </div>
        </div>

    </div>

    <!-- ══ FOOTER ══ -->
    <footer style="margin-top:48px">
        <div class="container-main">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="footer-brand">Phone<span>Store</span></div>
                    <p class="footer-desc">Chuỗi cửa hàng điện thoại & phụ kiện chính hãng uy tín toàn quốc.</p>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="footer-heading">Sản phẩm</div>
                    <ul class="footer-links">
                        <li><a href="products.php?category=dien-thoai">Điện thoại</a></li>
                        <li><a href="products.php?category=tai-nghe">Tai nghe</a></li>
                        <li><a href="products.php?category=sac-cap">Sạc & Cáp</a></li>
                        <li><a href="products.php?category=op-lung">Ốp lưng</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="footer-heading">Hỗ trợ</div>
                    <ul class="footer-links">
                        <li><a href="policy.php">Chính sách đổi trả</a></li>
                        <li><a href="policy.php#warranty">Bảo hành</a></li>
                        <li><a href="policy.php#shipping">Vận chuyển</a></li>
                        <li><a href="contact.php">Liên hệ</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="footer-heading">Liên hệ</div>
                    <div class="footer-contact-item"><i class="bi bi-geo-alt-fill"></i><span>123 Nguyễn Huệ, Quận 1, TP. HCM</span></div>
                    <div class="footer-contact-item"><i class="bi bi-telephone-fill"></i><span>1800 2097</span></div>
                    <div class="footer-contact-item"><i class="bi bi-envelope-fill"></i><span>support@phonestore.com</span></div>
                    <div class="footer-contact-item"><i class="bi bi-clock-fill"></i><span>8:00 - 21:00, Thứ 2 - Chủ nhật</span></div>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© 2024 PhoneStore. All rights reserved.</span>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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