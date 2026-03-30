<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$success = '';

// ── XÓA ĐÁNH GIÁ ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    $rid = (int)$_POST['review_id'];
    $conn->query("DELETE FROM reviews WHERE id = $rid");
    $success = 'Đã xóa đánh giá!';
}

// ── LỌC & TÌM KIẾM ──────────────────────────────────
$f_rating = (int)($_GET['rating'] ?? 0);
$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

$where = "WHERE 1=1";
if ($f_rating > 0) $where .= " AND r.rating = $f_rating";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (u.full_name LIKE '%$s%' OR p.name LIKE '%$s%' OR r.comment LIKE '%$s%')";
}

$total_rows  = $conn->query("
    SELECT COUNT(*) AS c FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    $where
")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$reviews = $conn->query("
    SELECT r.*, u.full_name, p.name AS product_name, p.thumbnail
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    $where
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// Thống kê
$stats = $conn->query("SELECT rating, COUNT(*) AS cnt FROM reviews GROUP BY rating ORDER BY rating DESC");
$rating_counts = [];
$total_reviews = 0;
$total_rating  = 0;
while ($s = $stats->fetch_assoc()) {
    $rating_counts[$s['rating']] = $s['cnt'];
    $total_reviews += $s['cnt'];
    $total_rating  += $s['rating'] * $s['cnt'];
}
$avg_rating     = $total_reviews > 0 ? round($total_rating / $total_reviews, 1) : 0;
$pending_orders = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đánh giá - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:var(--font); background:#F3F4F6; color:var(--dark); }

        .sidebar { position:fixed; top:0; left:0; width:var(--sidebar-w); height:100vh; background:#0A0A0A; display:flex; flex-direction:column; z-index:100; overflow-y:auto; }
        .sidebar-brand { padding:20px 20px 16px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .sidebar-brand a { font-size:1.3rem; font-weight:800; color:#fff; text-decoration:none; }
        .sidebar-brand a span { color:var(--primary); }
        .sidebar-brand-sub { font-size:0.68rem; color:rgba(255,255,255,0.3); font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-top:2px; }
        .sidebar-nav { padding:12px 0; flex:1; }
        .sidebar-section { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:rgba(255,255,255,0.25); padding:14px 20px 6px; }
        .sidebar-item { display:flex; align-items:center; gap:10px; padding:10px 20px; color:rgba(255,255,255,0.55); text-decoration:none; font-size:0.875rem; font-weight:600; transition:all 0.2s; border-left:3px solid transparent; }
        .sidebar-item:hover { background:rgba(255,255,255,0.05); color:#fff; }
        .sidebar-item.active { background:rgba(0,87,255,0.15); color:#fff; border-left-color:var(--primary); }
        .sidebar-item i { font-size:1rem; width:18px; }
        .sidebar-badge { margin-left:auto; background:#EF4444; color:#fff; font-size:0.65rem; font-weight:700; padding:2px 6px; border-radius:100px; }
        .sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,0.08); }
        .sidebar-user { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
        .sidebar-avatar { width:34px; height:34px; background:rgba(0,87,255,0.3); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#60A5FA; font-size:0.85rem; font-weight:800; }
        .sidebar-user-name { font-size:0.82rem; font-weight:700; color:#fff; }
        .sidebar-user-role { font-size:0.68rem; color:rgba(255,255,255,0.35); }
        .btn-logout { display:flex; align-items:center; gap:8px; width:100%; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#F87171; border-radius:8px; padding:8px 12px; font-size:0.8rem; font-weight:700; font-family:var(--font); cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .btn-logout:hover { background:rgba(239,68,68,0.2); color:#FCA5A5; }

        .main-content { margin-left:var(--sidebar-w); min-height:100vh; }
        .admin-topbar { background:#fff; border-bottom:1px solid var(--border); padding:0 28px; height:60px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .admin-topbar-title { font-size:1rem; font-weight:800; color:var(--dark); }
        .page-body { padding:24px 28px; }

        .alert-success { background:#F0FDF4; border:1px solid #BBF7D0; color:#16A34A; border-radius:10px; padding:12px 16px; font-size:0.875rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; font-weight:600; }

        /* Stats */
        .stats-wrap {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 24px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 20px;
            align-items: center;
        }
        .avg-rating-box { text-align:center; padding-right:24px; border-right:1px solid var(--border); }
        .avg-num { font-size:2.8rem; font-weight:800; color:var(--dark); line-height:1; }
        .avg-stars { color:#F59E0B; font-size:1rem; margin:6px 0; }
        .avg-total { font-size:0.78rem; color:var(--gray); }
        .rating-bars { display:flex; flex-direction:column; gap:6px; }
        .rating-bar-row { display:flex; align-items:center; gap:8px; font-size:0.8rem; cursor:pointer; }
        .rating-bar-row:hover .rbl { color:var(--primary); }
        .rbl { color:var(--gray); white-space:nowrap; width:20px; text-align:right; font-weight:700; }
        .rb-track { flex:1; height:8px; background:var(--border); border-radius:4px; overflow:hidden; }
        .rb-fill { height:100%; background:#F59E0B; border-radius:4px; transition:width 0.3s; }
        .rb-cnt { color:var(--gray); width:28px; font-size:0.75rem; }

        /* Filter tabs */
        .filter-tabs { display:flex; gap:6px; margin-bottom:16px; flex-wrap:wrap; }
        .filter-tab { display:flex; align-items:center; gap:5px; padding:7px 14px; border-radius:100px; font-size:0.8rem; font-weight:700; text-decoration:none; border:1.5px solid var(--border); color:var(--gray); background:#fff; transition:all 0.2s; }
        .filter-tab:hover { border-color:#F59E0B; color:#D97706; }
        .filter-tab.active { background:#F59E0B; border-color:#F59E0B; color:#fff; }
        .filter-tab .stars { color:#F59E0B; font-size:0.75rem; }
        .filter-tab.active .stars { color:#fff; }

        /* Toolbar */
        .toolbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
        .search-input { background:#fff; border:1.5px solid var(--border); border-radius:10px; padding:9px 14px; font-size:0.875rem; font-family:var(--font); outline:none; width:300px; transition:border-color 0.2s; }
        .search-input:focus { border-color:var(--primary); }
        .btn-primary { background:var(--primary); color:#fff; border:none; border-radius:10px; padding:9px 16px; font-size:0.875rem; font-weight:700; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s; }
        .btn-primary:hover { background:var(--primary-dark); color:#fff; }
        .btn-secondary { background:#fff; color:var(--gray); border:1.5px solid var(--border); border-radius:10px; padding:9px 14px; font-size:0.875rem; font-weight:600; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
        .btn-secondary:hover { border-color:var(--primary); color:var(--primary); }

        /* Review cards */
        .reviews-list { display:flex; flex-direction:column; gap:12px; }
        .review-card {
            background:#fff;
            border:1px solid var(--border);
            border-radius:12px;
            padding:16px 20px;
            display:grid;
            grid-template-columns: 52px 1fr auto;
            gap:14px;
            align-items:start;
            transition:box-shadow 0.2s;
        }
        .review-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.06); }

        .review-product-img {
            width:52px; height:52px;
            border-radius:8px;
            background:var(--light);
            border:1px solid var(--border);
            overflow:hidden;
            display:flex; align-items:center; justify-content:center;
            font-size:1.5rem;
            flex-shrink:0;
        }
        .review-product-img img { width:100%; height:100%; object-fit:cover; }

        .review-content {}
        .review-meta {
            display:flex;
            align-items:center;
            gap:8px;
            margin-bottom:4px;
            flex-wrap:wrap;
        }
        .reviewer-name { font-weight:800; font-size:0.875rem; color:var(--dark); }
        .review-arrow { color:var(--gray); font-size:0.7rem; }
        .review-product-name {
            font-size:0.82rem;
            font-weight:600;
            color:var(--primary);
        }
        .review-stars { color:#F59E0B; font-size:0.82rem; }
        .review-date { font-size:0.72rem; color:var(--gray); margin-left:auto; }
        .review-comment {
            font-size:0.875rem;
            color:#374151;
            line-height:1.6;
            margin-top:4px;
        }

        .btn-delete-review {
            background:#FEF2F2; color:#EF4444;
            border:none; border-radius:8px;
            padding:7px 12px; font-size:0.78rem;
            font-weight:700; font-family:var(--font);
            cursor:pointer; display:inline-flex;
            align-items:center; gap:4px;
            transition:all 0.2s; flex-shrink:0;
        }
        .btn-delete-review:hover { background:#EF4444; color:#fff; }

        /* Empty */
        .empty-state { text-align:center; padding:48px 20px; background:#fff; border:1px solid var(--border); border-radius:14px; }

        /* Pagination */
        .pagination-wrap { display:flex; justify-content:center; gap:6px; margin-top:20px; flex-wrap:wrap; }
        .page-btn { min-width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:1.5px solid var(--border); border-radius:8px; font-size:0.82rem; font-weight:700; color:var(--dark); text-decoration:none; transition:all 0.2s; padding:0 10px; }
        .page-btn:hover { border-color:var(--primary); color:var(--primary); }
        .page-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .page-btn.disabled { opacity:0.4; pointer-events:none; }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
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
        <a href="manage_orders.php" class="sidebar-item">
            <i class="bi bi-bag-check"></i> Đơn hàng
            <?php if ($pending_orders > 0): ?><span class="sidebar-badge"><?= $pending_orders ?></span><?php endif; ?>
        </a>
        <a href="manage_users.php" class="sidebar-item"><i class="bi bi-people"></i> Khách hàng</a>
        <a href="manage_reviews.php" class="sidebar-item active"><i class="bi bi-star"></i> Đánh giá</a>
        <div class="sidebar-section">Khác</div>
        <a href="../index.php" class="sidebar-item" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Xem website</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= mb_strtoupper(mb_substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="sidebar-user-role">Quản trị viên</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="admin-topbar">
        <div class="admin-topbar-title">⭐ Quản lý đánh giá</div>
        <span style="font-size:0.78rem;color:var(--gray)">Tổng: <?= $total_reviews ?> đánh giá</span>
    </div>

    <div class="page-body">

        <?php if ($success): ?>
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
        <?php endif; ?>

        <!-- Rating stats -->
        <div class="stats-wrap">
            <div class="avg-rating-box">
                <div class="avg-num"><?= $avg_rating ?: '—' ?></div>
                <div class="avg-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star<?= $i <= round($avg_rating) ? '-fill' : '' ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="avg-total"><?= $total_reviews ?> đánh giá</div>
            </div>
            <div class="rating-bars">
                <?php for ($star = 5; $star >= 1; $star--):
                    $cnt = $rating_counts[$star] ?? 0;
                    $pct = $total_reviews > 0 ? round($cnt / $total_reviews * 100) : 0;
                ?>
                <a href="manage_reviews.php?rating=<?= $star ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                   class="rating-bar-row" style="text-decoration:none">
                    <span class="rbl"><?= $star ?>★</span>
                    <div class="rb-track">
                        <div class="rb-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="rb-cnt"><?= $cnt ?></span>
                </a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <a href="manage_reviews.php<?= $search ? '?q='.urlencode($search) : '' ?>"
               class="filter-tab <?= $f_rating===0?'active':'' ?>">
                Tất cả <span style="background:rgba(0,0,0,0.08);padding:1px 6px;border-radius:100px;font-size:0.7rem"><?= $total_reviews ?></span>
            </a>
            <?php for ($star = 5; $star >= 1; $star--): ?>
            <a href="manage_reviews.php?rating=<?= $star ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="filter-tab <?= $f_rating===$star?'active':'' ?>">
                <span class="stars"><?= str_repeat('★', $star) ?></span>
                <?= $star ?> sao
                <span style="background:rgba(0,0,0,0.08);padding:1px 5px;border-radius:100px;font-size:0.7rem"><?= $rating_counts[$star] ?? 0 ?></span>
            </a>
            <?php endfor; ?>
        </div>

        <!-- Search -->
        <form method="GET" action="manage_reviews.php">
            <?php if ($f_rating > 0): ?>
            <input type="hidden" name="rating" value="<?= $f_rating ?>">
            <?php endif; ?>
            <div class="toolbar">
                <input type="text" name="q" class="search-input"
                       placeholder="🔍 Tìm tên khách, sản phẩm, nội dung..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Tìm</button>
                <?php if ($search): ?>
                <a href="manage_reviews.php<?= $f_rating ? '?rating='.$f_rating : '' ?>" class="btn-secondary">
                    <i class="bi bi-x"></i> Xóa
                </a>
                <?php endif; ?>
                <span style="margin-left:auto;font-size:0.82rem;color:var(--gray)"><?= $total_rows ?> kết quả</span>
            </div>
        </form>

        <!-- Reviews list -->
        <?php if ($reviews->num_rows > 0): ?>
        <div class="reviews-list">
            <?php while ($rv = $reviews->fetch_assoc()): ?>
            <div class="review-card">

                <!-- Ảnh sản phẩm -->
                <div class="review-product-img">
                    <?php if ($rv['thumbnail']): ?>
                        <img src="../assets/images/products/<?= htmlspecialchars($rv['thumbnail']) ?>" alt="">
                    <?php else: ?>📱<?php endif; ?>
                </div>

                <!-- Nội dung -->
                <div class="review-content">
                    <div class="review-meta">
                        <span class="reviewer-name"><?= htmlspecialchars($rv['full_name']) ?></span>
                        <i class="bi bi-arrow-right review-arrow"></i>
                        <span class="review-product-name"><?= htmlspecialchars($rv['product_name']) ?></span>
                        <span class="review-date">
                            <?= date('d/m/Y H:i', strtotime($rv['created_at'])) ?>
                        </span>
                    </div>
                    <div class="review-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= $rv['rating'] ? '-fill' : '' ?>"></i>
                        <?php endfor; ?>
                        <span style="font-size:0.78rem;color:var(--gray);margin-left:4px"><?= $rv['rating'] ?>/5</span>
                    </div>
                    <?php if ($rv['comment']): ?>
                    <div class="review-comment"><?= nl2br(htmlspecialchars($rv['comment'])) ?></div>
                    <?php else: ?>
                    <div class="review-comment" style="color:var(--gray);font-style:italic">Không có nội dung</div>
                    <?php endif; ?>
                </div>

                <!-- Xóa -->
                <form method="POST" onsubmit="return confirm('Xóa đánh giá này?')">
                    <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                    <button type="submit" name="delete_review" class="btn-delete-review">
                        <i class="bi bi-trash3"></i> Xóa
                    </button>
                </form>

            </div>
            <?php endwhile; ?>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <div style="font-size:3rem;margin-bottom:12px">⭐</div>
            <h3 style="font-weight:800;color:var(--dark);margin-bottom:6px">Chưa có đánh giá nào</h3>
            <p style="color:var(--gray);font-size:0.875rem">
                <?= $f_rating > 0 ? "Không có đánh giá $f_rating sao nào." : 'Chưa có khách hàng nào đánh giá sản phẩm.' ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1):
            $base = '?rating='.$f_rating.($search?'&q='.urlencode($search):'');
        ?>
        <div class="pagination-wrap">
            <a href="<?= $base ?>&page=<?= $page-1 ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <a href="<?= $base ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>&page=<?= $page+1 ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>