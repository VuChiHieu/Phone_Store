<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) { header('Location: manage_orders.php'); exit; }

// Xử lý xóa đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
    $conn->query("DELETE FROM orders WHERE id = $order_id");
    header('Location: manage_orders.php?deleted=1');
    exit;
}

// Xử lý xác nhận thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $now = date('Y-m-d H:i:s');
    $conn->query("UPDATE orders SET payment_status='paid', paid_at='$now' WHERE id=$order_id AND payment_method='bank_transfer'");
    header('Location: order_detail.php?id='.$order_id.'&paid=1');
    exit;
}

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = $_POST['status'];
    $valid  = ['pending','confirmed','shipping','delivered','cancelled'];
    if (in_array($status, $valid)) {
        if ($status === 'shipping') {
            // Tính estimated_delivery theo vùng
            $addr = mb_strtolower($conn->query("SELECT address FROM orders WHERE id=$order_id")->fetch_assoc()['address'] ?? '', 'UTF-8');
            $days = 4; // default
            $urban = ['hồ chí minh','tp.hcm','tp hcm','tphcm','quận 1','quận 2','quận 3','quận 4','quận 5','quận 6','quận 7','quận 8','quận 9','quận 10','quận 11','quận 12','bình thạnh','gò vấp','tân bình','tân phú','phú nhuận','bình tân','thủ đức','hà nội','ha noi','ba đình','hoàn kiếm','cầu giấy','thanh xuân'];
            $remote = ['phú quốc','côn đảo','cà mau','kiên giang','bạc liêu','sóc trăng','hà giang','đồng văn','lai châu','điện biên','sơn la','cao bằng','lạng sơn'];
            $nearby = ['bình dương','đồng nai','long an','vũng tàu','tây ninh','bình phước','cần thơ','tiền giang','đà nẵng','huế','khánh hòa','bắc ninh','hưng yên'];
            foreach ($urban  as $kw) if (mb_strpos($addr,$kw)!==false) { $days=2; break; }
            foreach ($remote as $kw) if (mb_strpos($addr,$kw)!==false) { $days=6; break; }
            foreach ($nearby as $kw) if (mb_strpos($addr,$kw)!==false) { $days=3; break; }
            $est = date('Y-m-d', strtotime("+$days days"));
            $conn->query("UPDATE orders SET status='shipping', estimated_delivery='$est' WHERE id=$order_id");
        } elseif ($status === 'delivered') {
            $o = $conn->query("SELECT payment_method, payment_status FROM orders WHERE id=$order_id")->fetch_assoc();
            if ($o['payment_method']==='cod' && $o['payment_status']==='unpaid') {
                $now = date('Y-m-d H:i:s');
                $conn->query("UPDATE orders SET status='delivered', payment_status='paid', paid_at='$now' WHERE id=$order_id");
            } else {
                $conn->query("UPDATE orders SET status='delivered' WHERE id=$order_id");
            }
        } else {
            $conn->query("UPDATE orders SET status='$status' WHERE id=$order_id");
        }
    }
    header('Location: order_detail.php?id='.$order_id);
    exit;
}

// Lấy đơn hàng
$order = $conn->query("
    SELECT o.*, u.email AS customer_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = $order_id
")->fetch_assoc();

if (!$order) { header('Location: manage_orders.php'); exit; }

// Lấy items
$items_q = $conn->query("
    SELECT oi.*, p.name, p.thumbnail, p.slug, b.name AS brand_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE oi.order_id = $order_id
");
$items = [];
while ($it = $items_q->fetch_assoc()) $items[] = $it;

$status_labels = [
    'pending'   => ['label'=>'Chờ xác nhận','color'=>'#D97706','bg'=>'#FFFBEB','icon'=>'⏳'],
    'confirmed' => ['label'=>'Đã xác nhận', 'color'=>'#2563EB','bg'=>'#EFF6FF','icon'=>'✅'],
    'shipping'  => ['label'=>'Đang giao',   'color'=>'#7C3AED','bg'=>'#F5F3FF','icon'=>'🚚'],
    'delivered' => ['label'=>'Đã giao',     'color'=>'#16A34A','bg'=>'#F0FDF4','icon'=>'📦'],
    'cancelled' => ['label'=>'Đã hủy',      'color'=>'#DC2626','bg'=>'#FEF2F2','icon'=>'❌'],
];
$s      = $status_labels[$order['status']] ?? $status_labels['pending'];
$is_cod = $order['payment_method'] === 'cod';
$is_paid= $order['payment_status'] === 'paid';

$subtotal = array_sum(array_map(fn($i)=>$i['price']*$i['quantity'], $items));
$ship_fee = $order['total_price'] - $subtotal;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng #<?= $order_id ?> - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary:#0057FF; --primary-dark:#0040CC;
            --dark:#0A0A0A; --gray:#6B7280;
            --light:#F8F8F8; --border:#E5E7EB;
            --sidebar-w:240px; --font:'Nunito',sans-serif;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:var(--font);background:#F3F4F6;color:var(--dark);}

        /* Sidebar */
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:#0A0A0A;display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
        .sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,0.08);}
        .sidebar-brand a{font-size:1.3rem;font-weight:800;color:#fff;text-decoration:none;}
        .sidebar-brand a span{color:var(--primary);}
        .sidebar-brand-sub{font-size:0.68rem;color:rgba(255,255,255,0.3);font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-top:2px;}
        .sidebar-nav{padding:12px 0;flex:1;}
        .sidebar-section{font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,0.25);padding:14px 20px 6px;}
        .sidebar-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,0.55);text-decoration:none;font-size:0.875rem;font-weight:600;transition:all 0.2s;border-left:3px solid transparent;}
        .sidebar-item:hover{background:rgba(255,255,255,0.05);color:#fff;}
        .sidebar-item.active{background:rgba(0,87,255,0.15);color:#fff;border-left-color:var(--primary);}
        .sidebar-item i{font-size:1rem;width:18px;}
        .sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08);}
        .sidebar-user{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
        .sidebar-avatar{width:34px;height:34px;background:rgba(0,87,255,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#60A5FA;font-size:0.85rem;font-weight:800;}
        .sidebar-user-name{font-size:0.82rem;font-weight:700;color:#fff;}
        .sidebar-user-role{font-size:0.68rem;color:rgba(255,255,255,0.35);}
        .btn-logout{display:flex;align-items:center;gap:8px;width:100%;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#F87171;border-radius:8px;padding:8px 12px;font-size:0.8rem;font-weight:700;font-family:var(--font);cursor:pointer;text-decoration:none;transition:all 0.2s;}
        .btn-logout:hover{background:rgba(239,68,68,0.2);color:#FCA5A5;}

        .main-content{margin-left:var(--sidebar-w);min-height:100vh;}
        .admin-topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
        .page-body{padding:24px 28px;}

        /* Cards */
        .detail-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px;}
        .detail-card-header{padding:14px 20px;border-bottom:1px solid var(--border);background:#FAFAFA;font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gray);display:flex;align-items:center;gap:8px;}
        .detail-card-body{padding:18px 20px;}
        .info-row{display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;font-size:0.875rem;border-bottom:1px solid #F9FAFB;}
        .info-row:last-child{border-bottom:none;}
        .info-row .lbl{color:var(--gray);flex-shrink:0;margin-right:16px;}
        .info-row .val{font-weight:700;color:var(--dark);text-align:right;}

        /* Action buttons */
        .btn-action{display:inline-flex;align-items:center;gap:6px;border-radius:9px;padding:8px 16px;font-size:0.82rem;font-weight:700;font-family:var(--font);cursor:pointer;border:none;transition:all 0.2s;text-decoration:none;}
        .btn-primary-a{background:var(--primary);color:#fff;}
        .btn-primary-a:hover{background:var(--primary-dark);color:#fff;}
        .btn-outline-a{background:#fff;color:var(--gray);border:1.5px solid var(--border);}
        .btn-outline-a:hover{border-color:var(--primary);color:var(--primary);}
        .btn-danger-a{background:#FEF2F2;color:#DC2626;border:1.5px solid #FECACA;}
        .btn-danger-a:hover{background:#DC2626;color:#fff;border-color:#DC2626;}
        .btn-success-a{background:#F0FDF4;color:#16A34A;border:1.5px solid #BBF7D0;}
        .btn-success-a:hover{background:#16A34A;color:#fff;border-color:#16A34A;}
        .btn-warn-a{background:#FFFBEB;color:#92400E;border:1.5px solid #FDE68A;}
        .btn-warn-a:hover{background:#D97706;color:#fff;border-color:#D97706;}
        .btn-print-a{background:#0A0A0A;color:#fff;}
        .btn-print-a:hover{background:#333;color:#fff;}

        /* Status select */
        .status-select{border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;font-size:0.82rem;font-weight:700;font-family:var(--font);outline:none;cursor:pointer;background:#fff;}

        /* Items */
        .item-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #F9FAFB;}
        .item-row:last-child{border-bottom:none;}
        .item-img{width:56px;height:56px;border-radius:8px;background:var(--light);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;overflow:hidden;}
        .item-img img{width:100%;height:100%;object-fit:cover;}

        /* Total */
        .total-box{background:#F8F8F8;border-radius:10px;padding:14px 16px;margin-top:12px;}
        .total-row{display:flex;justify-content:space-between;font-size:0.875rem;padding:4px 0;}
        .total-row.grand{padding-top:10px;margin-top:6px;border-top:1.5px solid var(--border);}
        .total-row.grand .lbl{font-weight:800;font-size:0.95rem;}
        .total-row.grand .val{font-size:1.1rem;font-weight:800;color:#EF4444;}

        /* ETA */
        .eta-box{display:flex;align-items:center;gap:12px;padding:14px 16px;background:linear-gradient(90deg,#F5F3FF,#EDE9FE);border:1px solid #DDD6FE;border-radius:10px;margin-bottom:14px;}
        .eta-box.delivered{background:linear-gradient(90deg,#F0FDF4,#DCFCE7);border-color:#BBF7D0;}

        /* Alert */
        .alert-s{background:#F0FDF4;border:1px solid #BBF7D0;color:#16A34A;border-radius:10px;padding:10px 16px;font-size:0.875rem;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-weight:600;}

        /* ════ PRINT STYLES ════ */
        @media print {
            .sidebar, .admin-topbar, .no-print { display:none !important; }
            .main-content { margin-left:0 !important; }
            .page-body { padding:0 !important; }
            body { background:#fff !important; }
            .detail-card { border:1px solid #ddd !important; box-shadow:none !important; break-inside:avoid; }
            .print-header { display:block !important; }
        }
        .print-header { display:none; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="index.php">Phone<span>Store</span></a>
        <div class="sidebar-brand-sub">Admin Panel</div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section">Tổng quan</div>
        <a href="index.php" class="sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-section">Quản lý</div>
        <a href="manage_products.php" class="sidebar-item"><i class="bi bi-phone"></i> Sản phẩm</a>
        <a href="manage_orders.php" class="sidebar-item active"><i class="bi bi-bag-check"></i> Đơn hàng</a>
        <a href="manage_users.php" class="sidebar-item"><i class="bi bi-people"></i> Khách hàng</a>
        <a href="manage_reviews.php" class="sidebar-item"><i class="bi bi-star"></i> Đánh giá</a>
        <div class="sidebar-section">Khác</div>
        <a href="../index.php" class="sidebar-item" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Xem website</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= mb_strtoupper(mb_substr($_SESSION['full_name'],0,1)) ?></div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="sidebar-user-role">Quản trị viên</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
    </div>
</aside>

<div class="main-content">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="manage_orders.php" style="color:var(--gray);font-size:1.1rem;text-decoration:none;"><i class="bi bi-arrow-left"></i></a>
            <span style="font-size:1rem;font-weight:800;">Chi tiết đơn #<?= $order_id ?></span>
            <span style="font-family:monospace;font-size:0.82rem;color:var(--primary);background:#EEF4FF;padding:3px 10px;border-radius:6px;"><?= $order['payment_code'] ?></span>
        </div>
        <!-- Action buttons -->
        <div class="no-print" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button onclick="window.print()" class="btn-action btn-print-a">
                <i class="bi bi-printer"></i> In đơn
            </button>
            <?php if (!$is_paid && !$is_cod): ?>
            <form method="POST" style="margin:0" onsubmit="return confirm('Xác nhận khách đã chuyển khoản?')">
                <button type="submit" name="confirm_payment" class="btn-action btn-warn-a">
                    <i class="bi bi-check-circle"></i> Xác nhận CK
                </button>
            </form>
            <?php endif; ?>
            <form method="POST" style="margin:0" onsubmit="return confirm('Xóa đơn hàng này? Hành động không thể hoàn tác!')">
                <button type="submit" name="delete_order" class="btn-action btn-danger-a">
                    <i class="bi bi-trash3"></i> Xóa đơn
                </button>
            </form>
        </div>
    </div>

    <div class="page-body">

        <?php if (isset($_GET['paid'])): ?>
        <div class="alert-s"><i class="bi bi-check-circle-fill"></i> Đã xác nhận thanh toán chuyển khoản!</div>
        <?php endif; ?>

        <!-- Print header (chỉ hiện khi in) -->
        <div class="print-header" style="margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid #000;">
            <h2 style="margin:0;font-size:1.4rem;">PhoneStore — Đơn hàng <?= $order['payment_code'] ?></h2>
            <p style="margin:4px 0 0;color:#555;font-size:0.85rem;">In lúc <?= date('H:i d/m/Y') ?> · 123 Nguyễn Huệ, Quận 1, TP.HCM · 1800 2097</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start;">

            <!-- LEFT -->
            <div>

                <!-- ETA -->
                <?php if ($order['status']==='shipping' && !empty($order['estimated_delivery'])): ?>
                <div class="eta-box">
                    <span style="font-size:1.8rem">🚚</span>
                    <div>
                        <div style="font-size:0.7rem;font-weight:700;color:#7C3AED;text-transform:uppercase;letter-spacing:0.5px">Dự kiến giao hàng</div>
                        <div style="font-size:0.95rem;font-weight:800;color:#5B21B6"><?php
                            $d = new DateTime($order['estimated_delivery']);
                            $days_vn = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
                            echo $days_vn[$d->format('w')] . ', ' . $d->format('d/m/Y');
                        ?></div>
                    </div>
                </div>
                <?php elseif ($order['status']==='delivered'): ?>
                <div class="eta-box delivered">
                    <span style="font-size:1.8rem">✅</span>
                    <div>
                        <div style="font-size:0.7rem;font-weight:700;color:#16A34A;text-transform:uppercase;letter-spacing:0.5px">Giao hàng thành công</div>
                        <div style="font-size:0.95rem;font-weight:800;color:#15803D">Đơn đã giao đến khách hàng</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sản phẩm -->
                <div class="detail-card">
                    <div class="detail-card-header"><i class="bi bi-bag"></i> Sản phẩm (<?= count($items) ?> loại)</div>
                    <div class="detail-card-body">
                        <?php foreach ($items as $item): ?>
                        <div class="item-row">
                            <div class="item-img">
                                <?php if ($item['thumbnail']): ?>
                                    <img src="../assets/images/products/<?= htmlspecialchars($item['thumbnail']) ?>" alt="">
                                <?php else: ?>📱<?php endif; ?>
                            </div>
                            <div style="flex:1">
                                <div style="font-size:0.875rem;font-weight:700"><?= htmlspecialchars($item['name']) ?></div>
                                <?php if ($item['brand_name']): ?>
                                <div style="font-size:0.72rem;color:var(--gray)"><?= htmlspecialchars($item['brand_name']) ?></div>
                                <?php endif; ?>
                                <div style="font-size:0.72rem;color:var(--gray);margin-top:2px">Đơn giá: <?= number_format($item['price'],0,',','.') ?>đ</div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:0.82rem;color:var(--gray)">x<?= $item['quantity'] ?></div>
                                <div style="font-size:0.92rem;font-weight:800"><?= number_format($item['price']*$item['quantity'],0,',','.') ?>đ</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="total-box">
                            <div class="total-row">
                                <span class="lbl" style="color:var(--gray)">Tạm tính</span>
                                <span class="val"><?= number_format($subtotal,0,',','.') ?>đ</span>
                            </div>
                            <div class="total-row">
                                <span class="lbl" style="color:var(--gray)">Phí vận chuyển</span>
                                <span class="val" style="color:<?= $ship_fee==0?'#16A34A':'var(--dark)' ?>"><?= $ship_fee==0?'Miễn phí':number_format($ship_fee,0,',','.').'đ' ?></span>
                            </div>
                            <div class="total-row grand">
                                <span class="lbl">Tổng cộng</span>
                                <span class="val"><?= number_format($order['total_price'],0,',','.') ?>đ</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ghi chú -->
                <?php if ($order['note']): ?>
                <div class="detail-card">
                    <div class="detail-card-header"><i class="bi bi-chat-left-text"></i> Ghi chú đơn hàng</div>
                    <div class="detail-card-body" style="font-size:0.875rem;color:var(--dark);">
                        <?= nl2br(htmlspecialchars($order['note'])) ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- RIGHT -->
            <div>

                <!-- Cập nhật trạng thái -->
                <div class="detail-card no-print">
                    <div class="detail-card-header"><i class="bi bi-arrow-repeat"></i> Cập nhật trạng thái</div>
                    <div class="detail-card-body">
                        <form method="POST">
                            <div style="display:flex;gap:8px;align-items:center;">
                                <select name="status" class="status-select" style="flex:1;color:<?= $s['color'] ?>">
                                    <?php foreach ($status_labels as $key => $sl): ?>
                                    <option value="<?= $key ?>" <?= $order['status']===$key?'selected':'' ?>>
                                        <?= $sl['icon'] ?> <?= $sl['label'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_status" class="btn-action btn-primary-a">
                                    Lưu
                                </button>
                            </div>
                        </form>
                        <div style="margin-top:10px;padding:10px 12px;background:<?= $s['bg'] ?>;border-radius:8px;font-size:0.78rem;font-weight:700;color:<?= $s['color'] ?>;text-align:center;">
                            <?= $s['icon'] ?> Trạng thái hiện tại: <?= $s['label'] ?>
                        </div>
                    </div>
                </div>

                <!-- Thông tin khách -->
                <div class="detail-card">
                    <div class="detail-card-header"><i class="bi bi-person"></i> Thông tin khách hàng</div>
                    <div class="detail-card-body">
                        <div class="info-row">
                            <span class="lbl">Họ tên</span>
                            <span class="val"><?= htmlspecialchars($order['full_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Số điện thoại</span>
                            <span class="val"><?= htmlspecialchars($order['phone']) ?></span>
                        </div>
                        <?php if ($order['customer_email']): ?>
                        <div class="info-row">
                            <span class="lbl">Email</span>
                            <span class="val" style="font-size:0.8rem"><?= htmlspecialchars($order['customer_email']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="lbl">Địa chỉ</span>
                            <span class="val"><?= htmlspecialchars($order['address']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Thanh toán -->
                <div class="detail-card">
                    <div class="detail-card-header"><i class="bi bi-credit-card"></i> Thanh toán</div>
                    <div class="detail-card-body">
                        <div class="info-row">
                            <span class="lbl">Phương thức</span>
                            <span class="val"><?= $is_cod?'💵 COD':'🏦 Chuyển khoản' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Trạng thái</span>
                            <span class="val" style="color:<?= $is_paid?'#16A34A':'#D97706' ?>">
                                <?= $is_paid?'✓ Đã thanh toán':'⏳ Chưa thanh toán' ?>
                            </span>
                        </div>
                        <?php if ($is_paid && $order['paid_at']): ?>
                        <div class="info-row">
                            <span class="lbl">Thời gian TT</span>
                            <span class="val"><?= date('H:i d/m/Y', strtotime($order['paid_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Thông tin đơn -->
                <div class="detail-card">
                    <div class="detail-card-header"><i class="bi bi-info-circle"></i> Thông tin đơn</div>
                    <div class="detail-card-body">
                        <div class="info-row">
                            <span class="lbl">Mã đơn</span>
                            <span class="val" style="font-family:monospace;color:var(--primary)"><?= $order['payment_code'] ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Ngày đặt</span>
                            <span class="val"><?= date('H:i d/m/Y', strtotime($order['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($order['estimated_delivery'])): ?>
                        <div class="info-row">
                            <span class="lbl">Dự kiến giao</span>
                            <span class="val" style="color:#7C3AED"><?= date('d/m/Y', strtotime($order['estimated_delivery'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
</body>
</html>