<?php
session_start();
require_once '../config.php';
include '../includes/navbar.php';

// Đếm giỏ hàng
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $r   = $conn->query("SELECT SUM(quantity) AS total FROM cart WHERE user_id = $uid");
    $cart_count = $r->fetch_assoc()['total'] ?? 0;
}

$active_tab = $_GET['tab'] ?? 'return';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chính sách - Phone Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .policy-wrap {
            max-width: 1000px;
            margin: 0 auto;
            padding: 36px 24px;
        }

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
        .page-header p { color: var(--gray); font-size: 0.9rem; }

        /* Policy highlights */
        .policy-highlights {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .highlight-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
        }
        .highlight-card:hover {
            border-color: var(--primary);
            box-shadow: 0 6px 20px rgba(0,87,255,0.08);
            transform: translateY(-2px);
        }
        .highlight-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 12px;
        }
        .highlight-title {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--dark);
            margin-bottom: 4px;
        }
        .highlight-desc {
            font-size: 0.78rem;
            color: var(--gray);
            line-height: 1.5;
        }

        /* Tab nav */
        .policy-tabs {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .tab-nav {
            display: flex;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            scrollbar-width: none;
        }
        .tab-nav::-webkit-scrollbar { display: none; }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px 24px;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--gray);
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-family: 'Nunito', sans-serif;
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
            margin-bottom: -1px;
        }
        .tab-btn:hover { color: var(--primary); }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-btn i { font-size: 1rem; }

        /* Tab content */
        .tab-content { padding: 32px; }

        /* Policy content styles */
        .policy-section {
            margin-bottom: 32px;
        }
        .policy-section:last-child { margin-bottom: 0; }
        .policy-section-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
        }
        .policy-section-title i { color: var(--primary); }

        .policy-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .policy-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.6;
            border-bottom: 1px solid #F9FAFB;
        }
        .policy-list li:last-child { border-bottom: none; }
        .policy-list li::before {
            content: '';
            width: 6px; height: 6px;
            background: var(--primary);
            border-radius: 50%;
            margin-top: 8px;
            flex-shrink: 0;
        }

        .policy-note {
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            border-left: 4px solid #F97316;
            border-radius: 0 10px 10px 0;
            padding: 14px 16px;
            font-size: 0.85rem;
            color: #92400E;
            margin: 16px 0;
            line-height: 1.6;
        }
        .policy-note strong { color: #C2410C; }

        .policy-success-note {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-left: 4px solid #22C55E;
            border-radius: 0 10px 10px 0;
            padding: 14px 16px;
            font-size: 0.85rem;
            color: #166534;
            margin: 16px 0;
            line-height: 1.6;
        }

        /* Table */
        .policy-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            margin: 16px 0;
        }
        .policy-table th {
            background: var(--light);
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            color: var(--dark);
            border: 1px solid var(--border);
        }
        .policy-table td {
            padding: 10px 14px;
            border: 1px solid var(--border);
            color: #374151;
            line-height: 1.5;
        }
        .policy-table tr:nth-child(even) td { background: #FAFAFA; }
        .badge-green {
            background: #F0FDF4; color: #16A34A;
            border: 1px solid #BBF7D0;
            font-size: 0.72rem; font-weight: 700;
            padding: 2px 8px; border-radius: 100px;
        }
        .badge-red {
            background: #FEF2F2; color: #EF4444;
            border: 1px solid #FECACA;
            font-size: 0.72rem; font-weight: 700;
            padding: 2px 8px; border-radius: 100px;
        }
        .badge-yellow {
            background: #FFFBEB; color: #D97706;
            border: 1px solid #FDE68A;
            font-size: 0.72rem; font-weight: 700;
            padding: 2px 8px; border-radius: 100px;
        }

        /* Steps */
        .policy-steps {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 16px 0;
        }
        .policy-step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .policy-step-num {
            width: 32px; height: 32px;
            background: var(--primary);
            color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.82rem;
            font-weight: 800;
            flex-shrink: 0;
        }
        .policy-step-content {}
        .policy-step-title {
            font-weight: 700;
            font-size: 0.875rem;
            color: var(--dark);
            margin-bottom: 3px;
        }
        .policy-step-desc {
            font-size: 0.82rem;
            color: var(--gray);
            line-height: 1.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .policy-highlights { grid-template-columns: 1fr; }
            .tab-content { padding: 20px 16px; }
            .policy-wrap { padding: 16px; }
        }
        @media (max-width: 480px) {
            .tab-btn { padding: 12px 16px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<!-- ══ BREADCRUMB ══ -->
<div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;">
    <div style="max-width:1000px;margin:0 auto;padding:0 24px;font-size:0.82rem;color:var(--gray);">
        <a href="../index.php" style="color:var(--gray);text-decoration:none;">Trang chủ</a>
        <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
        <span style="color:var(--dark);font-weight:600;">Chính sách</span>
    </div>
</div>

<div class="policy-wrap">

    <!-- Page header -->
    <div class="page-header">
        <span class="page-header-label">✦ Cam kết của chúng tôi</span>
        <h1>Chính sách & Điều khoản</h1>
        <p>Minh bạch, rõ ràng — chúng tôi cam kết bảo vệ quyền lợi của bạn</p>
    </div>

    <!-- Highlights -->
    <div class="policy-highlights">
        <div class="highlight-card">
            <div class="highlight-icon" style="background:#EEF4FF;">🔄</div>
            <div class="highlight-title">Đổi trả 30 ngày</div>
            <div class="highlight-desc">Đổi trả miễn phí trong 30 ngày nếu sản phẩm lỗi do nhà sản xuất</div>
        </div>
        <div class="highlight-card">
            <div class="highlight-icon" style="background:#F0FDF4;">🛡️</div>
            <div class="highlight-title">Bảo hành 12 tháng</div>
            <div class="highlight-desc">Bảo hành chính hãng 12 tháng, hỗ trợ kỹ thuật tận nơi</div>
        </div>
        <div class="highlight-card">
            <div class="highlight-icon" style="background:#FFFBEB;">🚚</div>
            <div class="highlight-title">Giao hàng toàn quốc</div>
            <div class="highlight-desc">Miễn phí vận chuyển cho đơn từ 500K, giao trong 2-5 ngày</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="policy-tabs">
        <div class="tab-nav">
            <a href="policy.php?tab=return"
               class="tab-btn <?= $active_tab === 'return' ? 'active' : '' ?>">
                <i class="bi bi-arrow-repeat"></i> Đổi trả hàng
            </a>
            <a href="policy.php?tab=warranty"
               class="tab-btn <?= $active_tab === 'warranty' ? 'active' : '' ?>"
               id="warranty">
                <i class="bi bi-shield-check"></i> Bảo hành
            </a>
            <a href="policy.php?tab=shipping"
               class="tab-btn <?= $active_tab === 'shipping' ? 'active' : '' ?>"
               id="shipping">
                <i class="bi bi-truck"></i> Vận chuyển
            </a>
            <a href="policy.php?tab=privacy"
               class="tab-btn <?= $active_tab === 'privacy' ? 'active' : '' ?>">
                <i class="bi bi-lock"></i> Bảo mật
            </a>
        </div>

        <div class="tab-content">

            <!-- ══ ĐỔI TRẢ HÀNG ══ -->
            <?php if ($active_tab === 'return'): ?>
            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-info-circle-fill"></i> Điều kiện đổi trả
                </div>
                <table class="policy-table">
                    <tr>
                        <th>Trường hợp</th>
                        <th>Thời hạn</th>
                        <th>Chi phí</th>
                        <th>Trạng thái</th>
                    </tr>
                    <tr>
                        <td>Sản phẩm lỗi do nhà sản xuất</td>
                        <td>30 ngày</td>
                        <td>Miễn phí</td>
                        <td><span class="badge-green">Được đổi</span></td>
                    </tr>
                    <tr>
                        <td>Giao sai sản phẩm / màu sắc</td>
                        <td>7 ngày</td>
                        <td>Miễn phí</td>
                        <td><span class="badge-green">Được đổi</span></td>
                    </tr>
                    <tr>
                        <td>Khách đổi ý (không lỗi)</td>
                        <td>7 ngày</td>
                        <td>Khách chịu phí ship</td>
                        <td><span class="badge-yellow">Có phí</span></td>
                    </tr>
                    <tr>
                        <td>Sản phẩm đã kích hoạt bảo hành</td>
                        <td>—</td>
                        <td>—</td>
                        <td><span class="badge-red">Không đổi</span></td>
                    </tr>
                    <tr>
                        <td>Sản phẩm đã qua sử dụng, trầy xước</td>
                        <td>—</td>
                        <td>—</td>
                        <td><span class="badge-red">Không đổi</span></td>
                    </tr>
                </table>
            </div>

            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-card-checklist"></i> Yêu cầu khi đổi trả
                </div>
                <ul class="policy-list">
                    <li>Sản phẩm còn nguyên vẹn, chưa qua sử dụng (trừ trường hợp lỗi)</li>
                    <li>Còn đầy đủ hộp, phụ kiện, tài liệu đi kèm theo sản phẩm</li>
                    <li>Có hóa đơn mua hàng hoặc mã đơn hàng</li>
                    <li>Không có dấu hiệu va đập, vào nước, tự ý sửa chữa</li>
                    <li>Liên hệ hotline 1800 2097 trước khi gửi sản phẩm về</li>
                </ul>
            </div>

            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-arrow-right-circle-fill"></i> Quy trình đổi trả
                </div>
                <div class="policy-steps">
                    <div class="policy-step">
                        <div class="policy-step-num">1</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Liên hệ hỗ trợ</div>
                            <div class="policy-step-desc">Gọi hotline 1800 2097 hoặc gửi email để thông báo yêu cầu đổi trả</div>
                        </div>
                    </div>
                    <div class="policy-step">
                        <div class="policy-step-num">2</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Xác nhận & đóng gói</div>
                            <div class="policy-step-desc">Nhân viên xác nhận điều kiện đổi trả, hướng dẫn đóng gói và gửi hàng</div>
                        </div>
                    </div>
                    <div class="policy-step">
                        <div class="policy-step-num">3</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Kiểm tra sản phẩm</div>
                            <div class="policy-step-desc">Kỹ thuật viên kiểm tra sản phẩm trong 1-2 ngày làm việc</div>
                        </div>
                    </div>
                    <div class="policy-step">
                        <div class="policy-step-num">4</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Hoàn tất đổi trả</div>
                            <div class="policy-step-desc">Giao sản phẩm mới hoặc hoàn tiền trong 3-5 ngày làm việc</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="policy-note">
                <strong>⚠️ Lưu ý:</strong> PhoneStore có quyền từ chối đổi trả nếu sản phẩm không đáp ứng các điều kiện trên. Mọi tranh chấp sẽ được giải quyết theo quy định của pháp luật Việt Nam.
            </div>

            <!-- ══ BẢO HÀNH ══ -->
            <?php elseif ($active_tab === 'warranty'): ?>
            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-shield-fill-check"></i> Thời hạn bảo hành
                </div>
                <table class="policy-table">
                    <tr>
                        <th>Loại sản phẩm</th>
                        <th>Bảo hành chính hãng</th>
                        <th>Bảo hành PhoneStore</th>
                    </tr>
                    <tr>
                        <td>Điện thoại iPhone</td>
                        <td>12 tháng Apple</td>
                        <td>+ 3 tháng hỗ trợ</td>
                    </tr>
                    <tr>
                        <td>Điện thoại Samsung</td>
                        <td>12 tháng Samsung</td>
                        <td>+ 3 tháng hỗ trợ</td>
                    </tr>
                    <tr>
                        <td>Điện thoại Xiaomi / OPPO</td>
                        <td>12 tháng hãng</td>
                        <td>+ 3 tháng hỗ trợ</td>
                    </tr>
                    <tr>
                        <td>Tai nghe chính hãng</td>
                        <td>12 tháng</td>
                        <td>+ 1 tháng hỗ trợ</td>
                    </tr>
                    <tr>
                        <td>Sạc, cáp, phụ kiện</td>
                        <td>6 tháng</td>
                        <td>—</td>
                    </tr>
                </table>
            </div>

            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-check-circle-fill"></i> Bảo hành áp dụng khi
                </div>
                <ul class="policy-list">
                    <li>Sản phẩm bị lỗi phần cứng do nhà sản xuất</li>
                    <li>Màn hình, pin, camera bị lỗi trong điều kiện sử dụng bình thường</li>
                    <li>Sản phẩm không khởi động được mà không do tác động bên ngoài</li>
                    <li>Còn trong thời hạn bảo hành và có phiếu bảo hành / hóa đơn</li>
                </ul>
            </div>

            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-x-circle-fill"></i> Không áp dụng bảo hành khi
                </div>
                <ul class="policy-list">
                    <li>Sản phẩm bị va đập, rơi vỡ, biến dạng ngoại hình</li>
                    <li>Sản phẩm bị vào nước, ẩm ướt không do lỗi sản xuất</li>
                    <li>Tự ý sửa chữa, thay thế linh kiện không chính hãng</li>
                    <li>Sử dụng không đúng hướng dẫn, nguồn điện không ổn định</li>
                    <li>Hết thời hạn bảo hành</li>
                    <li>Tem bảo hành bị rách, số IMEI bị thay đổi</li>
                </ul>
            </div>

            <div class="policy-success-note">
                <strong>✅ Cam kết:</strong> Tất cả sản phẩm tại PhoneStore đều là hàng chính hãng, có đầy đủ tem bảo hành và hóa đơn VAT. Chúng tôi sẽ hỗ trợ bạn trong suốt quá trình bảo hành.
            </div>

            <!-- ══ VẬN CHUYỂN ══ -->
            <?php elseif ($active_tab === 'shipping'): ?>
            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-truck"></i> Phí & Thời gian vận chuyển
                </div>
                <table class="policy-table">
                    <tr>
                        <th>Khu vực</th>
                        <th>Đơn dưới 500K</th>
                        <th>Đơn từ 500K</th>
                        <th>Thời gian</th>
                    </tr>
                    <tr>
                        <td>Nội thành TP.HCM & Hà Nội</td>
                        <td>30.000đ</td>
                        <td><span class="badge-green">Miễn phí</span></td>
                        <td>1 - 2 ngày</td>
                    </tr>
                    <tr>
                        <td>Tỉnh thành lân cận</td>
                        <td>35.000đ</td>
                        <td><span class="badge-green">Miễn phí</span></td>
                        <td>2 - 3 ngày</td>
                    </tr>
                    <tr>
                        <td>Các tỉnh còn lại</td>
                        <td>45.000đ</td>
                        <td><span class="badge-green">Miễn phí</span></td>
                        <td>3 - 5 ngày</td>
                    </tr>
                    <tr>
                        <td>Vùng sâu, vùng xa, hải đảo</td>
                        <td>60.000đ</td>
                        <td>30.000đ</td>
                        <td>5 - 7 ngày</td>
                    </tr>
                </table>
            </div>

            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-box-seam"></i> Quy trình giao hàng
                </div>
                <div class="policy-steps">
                    <div class="policy-step">
                        <div class="policy-step-num">1</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Xác nhận đơn hàng</div>
                            <div class="policy-step-desc">Nhân viên gọi xác nhận đơn trong vòng 30 phút (giờ hành chính)</div>
                        </div>
                    </div>
                    <div class="policy-step">
                        <div class="policy-step-num">2</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Đóng gói & bàn giao</div>
                            <div class="policy-step-desc">Sản phẩm được đóng gói cẩn thận và bàn giao cho đơn vị vận chuyển</div>
                        </div>
                    </div>
                    <div class="policy-step">
                        <div class="policy-step-num">3</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Theo dõi đơn hàng</div>
                            <div class="policy-step-desc">Bạn nhận mã vận đơn qua SMS/email để theo dõi trạng thái giao hàng</div>
                        </div>
                    </div>
                    <div class="policy-step">
                        <div class="policy-step-num">4</div>
                        <div class="policy-step-content">
                            <div class="policy-step-title">Nhận hàng & kiểm tra</div>
                            <div class="policy-step-desc">Kiểm tra sản phẩm trước khi ký nhận, liên hệ ngay nếu có vấn đề</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-info-circle-fill"></i> Lưu ý khi nhận hàng
                </div>
                <ul class="policy-list">
                    <li>Kiểm tra tình trạng kiện hàng trước khi ký nhận — từ chối nhận nếu hộp bị móp méo, ướt</li>
                    <li>Quay video unboxing để làm bằng chứng nếu có tranh chấp</li>
                    <li>Liên hệ ngay trong 24h nếu nhận sai sản phẩm hoặc thiếu phụ kiện</li>
                    <li>Đơn hàng không nhận sẽ bị hủy sau 3 lần giao không thành công</li>
                </ul>
            </div>

            <div class="policy-note">
                <strong>⚠️ Lưu ý:</strong> Thời gian giao hàng có thể thay đổi trong dịp lễ Tết hoặc do điều kiện thời tiết, thiên tai. PhoneStore hợp tác với GHN, GHTK và VNPost để đảm bảo giao hàng toàn quốc.
            </div>

            <!-- ══ BẢO MẬT ══ -->
            <?php elseif ($active_tab === 'privacy'): ?>
            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-lock-fill"></i> Thông tin chúng tôi thu thập
                </div>
                <ul class="policy-list">
                    <li>Họ tên, địa chỉ email, số điện thoại khi đăng ký tài khoản</li>
                    <li>Địa chỉ giao hàng khi đặt mua sản phẩm</li>
                    <li>Lịch sử mua hàng và sản phẩm đã xem</li>
                    <li>Thông tin thiết bị và địa chỉ IP khi truy cập website</li>
                </ul>
            </div>

            <div class="policy-section">
                <div class="policy-section-title">
                    <i class="bi bi-shield-lock-fill"></i> Cam kết bảo mật
                </div>
                <ul class="policy-list">
                    <li>Mật khẩu được mã hóa bằng thuật toán bcrypt, không ai có thể đọc được</li>
                    <li>Thông tin cá nhân không được chia sẻ cho bên thứ ba vì mục đích thương mại</li>
                    <li>Chỉ chia sẻ với đơn vị vận chuyển khi cần thiết để giao hàng</li>
                    <li>Bạn có quyền yêu cầu xóa tài khoản và dữ liệu cá nhân bất kỳ lúc nào</li>
                    <li>Website sử dụng HTTPS để mã hóa dữ liệu truyền tải</li>
                </ul>
            </div>

            <div class="policy-success-note">
                <strong>✅ Cam kết:</strong> PhoneStore tuân thủ Luật An toàn thông tin mạng của Việt Nam. Chúng tôi không bao giờ bán thông tin khách hàng cho bên thứ ba.
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- CTA -->
    <div style="text-align:center;margin-top:32px;padding:28px;background:#fff;border:1px solid var(--border);border-radius:16px;">
        <div style="font-size:1rem;font-weight:800;color:var(--dark);margin-bottom:6px;">Còn thắc mắc?</div>
        <p style="color:var(--gray);font-size:0.875rem;margin-bottom:16px;">Liên hệ với chúng tôi để được giải đáp nhanh nhất!</p>
        <a href="contact.php" style="background:var(--primary);color:#fff;border-radius:10px;padding:11px 28px;font-size:0.875rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
            <i class="bi bi-headset"></i> Liên hệ ngay
        </a>
    </div>

</div>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</script>
</body>
</html>