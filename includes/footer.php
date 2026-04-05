<?php
// Đường dẫn base (giống navbar.php)
$base = (basename(dirname($_SERVER['PHP_SELF'])) === 'pages') ? '../' : '';
?>

<footer>
    <div class="container-main">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="footer-brand">Phone<span>Store</span></div>
                <p class="footer-desc">Chuỗi cửa hàng điện thoại & phụ kiện chính hãng uy tín toàn quốc.</p>
                <div class="footer-badges">
                    <span class="footer-badge">✓ Chính hãng</span>
                    <span class="footer-badge">✓ Bảo hành</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">Sản phẩm</div>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>pages/products.php?category=dien-thoai">Điện thoại</a></li>
                    <li><a href="<?= $base ?>pages/products.php?category=tai-nghe">Tai nghe</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">Chính sách</div>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>pages/policy.php">Đổi trả hàng</a></li>
                    <li><a href="<?= $base ?>pages/policy.php#warranty">Bảo hành</a></li>
                    <li><a href="<?= $base ?>pages/policy.php#shipping">Vận chuyển</a></li>
                    <li><a href="<?= $base ?>pages/contact.php">Liên hệ</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">Hỗ trợ</div>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>auth/login.php">Đăng nhập</a></li>
                    <li><a href="<?= $base ?>auth/register.php">Đăng ký</a></li>
                    <li><a href="<?= $base ?>pages/cart.php">Giỏ hàng</a></li>
                    <li><a href="<?= $base ?>pages/contact.php">Feedback</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="footer-heading">Liên hệ</div>
                <div class="footer-contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>123 Nguyễn Huệ, Quận 1, TP. HCM</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <span>1800 2097 (Miễn phí)</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <span>support@phonestore.com</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-clock-fill"></i>
                    <span>8:00 - 21:00, Thứ 2 - Chủ nhật</span>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© 2024 PhoneStore. All rights reserved.</span>
            <span>Made with ❤️ by Nhóm bạn</span>
        </div>
    </div>
</footer>