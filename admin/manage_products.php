<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$success = '';
$error   = '';
$action  = $_GET['action'] ?? 'list'; // list | add | edit
$edit_id = (int)($_GET['id'] ?? 0);

// ── XỬ LÝ FORM ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // XÓA SẢN PHẨM
    if (isset($_POST['delete_product'])) {
        $del_id = (int)$_POST['product_id'];
        $conn->query("DELETE FROM products WHERE id = $del_id");
        $success = 'Đã xóa sản phẩm!';
        $action  = 'list';
    }

    // THÊM / SỬA SẢN PHẨM
    elseif (isset($_POST['save_product'])) {
        $name             = trim($_POST['name']);
        $category_id      = (int)$_POST['category_id'];
        $brand_id         = (int)$_POST['brand_id'];
        $price            = (int)$_POST['price'];
        $old_price        = (int)($_POST['old_price'] ?: 0);
        $discount_percent = $old_price > 0 ? round(($old_price - $price) / $old_price * 100) : 0;
        $stock            = (int)$_POST['stock'];
        $ram              = trim($_POST['ram']);
        $storage          = trim($_POST['storage']);
        $description      = trim($_POST['description']);
        $is_featured      = isset($_POST['is_featured']) ? 1 : 0;

        // Tạo slug
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-',
            iconv('UTF-8', 'ASCII//TRANSLIT', $name))));
        $slug = trim($slug, '-');

        // Upload ảnh
        $thumbnail = trim($_POST['thumbnail_current'] ?? '');
        if (!empty($_FILES['thumbnail']['name'])) {
            $ext       = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $filename  = $slug . '-' . time() . '.' . strtolower($ext);
            $dest      = '../assets/images/products/' . $filename;
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dest)) {
                $thumbnail = $filename;
            }
        }

        if (empty($name)) {
            $error = 'Vui lòng nhập tên sản phẩm!';
        } else {
            $pid = (int)($_POST['product_id'] ?? 0);
            if ($pid > 0) {
                // SỬA
                $stmt = $conn->prepare("
                    UPDATE products SET
                        name=?, slug=?, category_id=?, brand_id=?,
                        price=?, old_price=?, discount_percent=?,
                        stock=?, ram=?, storage=?, description=?,
                        thumbnail=?, is_featured=?
                    WHERE id=?
                ");
                $stmt->bind_param("ssiiiiiissssii",
                    $name, $slug, $category_id, $brand_id,
                    $price, $old_price, $discount_percent,
                    $stock, $ram, $storage, $description,
                    $thumbnail, $is_featured, $pid
                );
                $stmt->execute();
                $success = 'Cập nhật sản phẩm thành công!';
            } else {
                // THÊM MỚI
                $stmt = $conn->prepare("
                    INSERT INTO products
                        (name, slug, category_id, brand_id, price, old_price,
                         discount_percent, stock, ram, storage, description,
                         thumbnail, is_featured)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->bind_param("ssiiiiiissssi",
                    $name, $slug, $category_id, $brand_id,
                    $price, $old_price, $discount_percent,
                    $stock, $ram, $storage, $description,
                    $thumbnail, $is_featured
                );
                $stmt->execute();
                $success = 'Thêm sản phẩm thành công!';
            }
            $action = 'list';
        }
    }
}

// ── DỮ LIỆU ─────────────────────────────────────────
$categories = $conn->query("SELECT * FROM categories ORDER BY name");
$brands     = $conn->query("SELECT * FROM brands ORDER BY name");

// Lọc + tìm kiếm danh sách
$search  = trim($_GET['q'] ?? '');
$f_cat   = (int)($_GET['cat'] ?? 0);
$f_brand = (int)($_GET['brand'] ?? 0);

$where = "WHERE 1=1";
if ($search)  $where .= " AND p.name LIKE '%".  $conn->real_escape_string($search)."%'";
if ($f_cat)   $where .= " AND p.category_id = $f_cat";
if ($f_brand) $where .= " AND p.brand_id = $f_brand";

$products = $conn->query("
    SELECT p.*, b.name AS brand_name, c.name AS cat_name
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    $where
    ORDER BY p.created_at DESC
");

// Sản phẩm đang sửa
$edit_product = null;
if ($action === 'edit' && $edit_id > 0) {
    $edit_product = $conn->query("SELECT * FROM products WHERE id = $edit_id")->fetch_assoc();
    if (!$edit_product) { $action = 'list'; }
}

// Pending orders cho badge
$pending_orders = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
            --sidebar-w: 240px;
            --font: 'Nunito', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font); background: #F3F4F6; color: var(--dark); }

        /* Sidebar — same as dashboard */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: #0A0A0A;
            display: flex; flex-direction: column;
            z-index: 100; overflow-y: auto;
        }
        .sidebar-brand { padding: 20px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-brand a { font-size: 1.3rem; font-weight: 800; color: #fff; text-decoration: none; }
        .sidebar-brand a span { color: var(--primary); }
        .sidebar-brand-sub { font-size: 0.68rem; color: rgba(255,255,255,0.3); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
        .sidebar-nav { padding: 12px 0; flex: 1; }
        .sidebar-section { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: rgba(255,255,255,0.25); padding: 14px 20px 6px; }
        .sidebar-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; color: rgba(255,255,255,0.55); text-decoration: none; font-size: 0.875rem; font-weight: 600; transition: all 0.2s; border-left: 3px solid transparent; }
        .sidebar-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-item.active { background: rgba(0,87,255,0.15); color: #fff; border-left-color: var(--primary); }
        .sidebar-item i { font-size: 1rem; width: 18px; }
        .sidebar-badge { margin-left: auto; background: #EF4444; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 100px; }
        .sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-user { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .sidebar-avatar { width: 34px; height: 34px; background: rgba(0,87,255,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #60A5FA; font-size: 0.85rem; font-weight: 800; }
        .sidebar-user-name { font-size: 0.82rem; font-weight: 700; color: #fff; }
        .sidebar-user-role { font-size: 0.68rem; color: rgba(255,255,255,0.35); }
        .btn-logout { display: flex; align-items: center; gap: 8px; width: 100%; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #F87171; border-radius: 8px; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; font-family: var(--font); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(239,68,68,0.2); color: #FCA5A5; }

        .main-content { margin-left: var(--sidebar-w); min-height: 100vh; }
        .admin-topbar { background: #fff; border-bottom: 1px solid var(--border); padding: 0 28px; height: 60px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .admin-topbar-title { font-size: 1rem; font-weight: 800; color: var(--dark); }
        .page-body { padding: 24px 28px; }

        /* Alerts */
        .alert-success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #16A34A; border-radius: 10px; padding: 12px 16px; font-size: 0.875rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-weight: 600; }
        .alert-error   { background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; border-radius: 10px; padding: 12px 16px; font-size: 0.875rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-input {
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 0.875rem;
            font-family: var(--font);
            outline: none;
            width: 240px;
            transition: border-color 0.2s;
        }
        .search-input:focus { border-color: var(--primary); }
        .filter-select {
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 0.875rem;
            font-family: var(--font);
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .filter-select:focus { border-color: var(--primary); }
        .btn-primary {
            background: var(--primary); color: #fff;
            border: none; border-radius: 10px;
            padding: 9px 18px; font-size: 0.875rem;
            font-weight: 700; font-family: var(--font);
            cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: var(--primary-dark); color: #fff; }
        .btn-secondary {
            background: #fff; color: var(--gray);
            border: 1.5px solid var(--border); border-radius: 10px;
            padding: 9px 16px; font-size: 0.875rem;
            font-weight: 600; font-family: var(--font);
            cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s;
        }
        .btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

        /* Table */
        .table-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { padding: 10px 16px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gray); background: var(--light); border-bottom: 1px solid var(--border); white-space: nowrap; }
        .admin-table td { padding: 12px 16px; font-size: 0.85rem; border-bottom: 1px solid #F9FAFB; vertical-align: middle; }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: #FAFAFA; }

        .product-img-cell {
            width: 48px; height: 48px;
            border-radius: 8px;
            background: var(--light);
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }
        .product-img-cell img { width: 100%; height: 100%; object-fit: cover; }
        .product-name-cell { font-weight: 700; color: var(--dark); }
        .product-brand-cell { font-size: 0.72rem; color: var(--gray); margin-top: 2px; }

        .stock-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.72rem; font-weight: 700;
            padding: 3px 8px; border-radius: 100px;
        }
        .stock-ok     { background: #F0FDF4; color: #16A34A; }
        .stock-low    { background: #FFFBEB; color: #D97706; }
        .stock-empty  { background: #FEF2F2; color: #DC2626; }

        .featured-badge { background: #EEF4FF; color: var(--primary); font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 100px; }

        .btn-edit { background: #EEF4FF; color: var(--primary); border: none; border-radius: 7px; padding: 6px 12px; font-size: 0.78rem; font-weight: 700; font-family: var(--font); cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
        .btn-edit:hover { background: var(--primary); color: #fff; }
        .btn-delete { background: #FEF2F2; color: #EF4444; border: none; border-radius: 7px; padding: 6px 12px; font-size: 0.78rem; font-weight: 700; font-family: var(--font); cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
        .btn-delete:hover { background: #EF4444; color: #fff; }

        /* Form */
        .form-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        .form-card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); font-weight: 800; font-size: 1rem; color: var(--dark); display: flex; align-items: center; gap: 8px; }
        .form-card-header i { color: var(--primary); }
        .form-card-body { padding: 24px; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 0; }
        .form-label { display: block; font-size: 0.82rem; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
        .form-label .req { color: #EF4444; }
        .form-control {
            width: 100%;
            background: var(--light);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.875rem;
            font-family: var(--font);
            color: var(--dark);
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus { border-color: var(--primary); background: #fff; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .form-check { display: flex; align-items: center; gap: 8px; }
        .form-check input { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
        .form-check label { font-size: 0.875rem; font-weight: 600; cursor: pointer; }

        .img-preview {
            width: 100px; height: 100px;
            border: 2px dashed var(--border);
            border-radius: 10px;
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
            background: var(--light);
            margin-top: 8px;
        }
        .img-preview img { width: 100%; height: 100%; object-fit: cover; }

        .form-divider { height: 1px; background: var(--border); margin: 20px 0; }
        .form-section-title { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--gray); margin-bottom: 14px; }

        @media (max-width: 900px) {
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="admin-topbar">
        <div class="admin-topbar-title">
            <?= $action === 'list' ? '📦 Quản lý sản phẩm' : ($action === 'add' ? '➕ Thêm sản phẩm' : '✏️ Sửa sản phẩm') ?>
        </div>
        <div style="display:flex;gap:8px">
            <?php if ($action !== 'list'): ?>
            <a href="manage_products.php" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
            <?php else: ?>
            <a href="manage_products.php?action=add" class="btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm sản phẩm
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-body">

        <?php if ($success): ?>
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
        <!-- ══════ DANH SÁCH ══════ -->

        <!-- Toolbar -->
        <form method="GET" action="manage_products.php">
            <div class="toolbar">
                <input type="text" name="q" class="search-input"
                       placeholder="🔍 Tìm tên sản phẩm..."
                       value="<?= htmlspecialchars($search) ?>">
                <select name="cat" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tất cả danh mục</option>
                    <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $f_cat == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <select name="brand" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tất cả hãng</option>
                    <?php $brands->data_seek(0); while ($b = $brands->fetch_assoc()): ?>
                    <option value="<?= $b['id'] ?>" <?= $f_brand == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Tìm</button>
                <?php if ($search || $f_cat || $f_brand): ?>
                <a href="manage_products.php" class="btn-secondary"><i class="bi bi-x"></i> Xóa lọc</a>
                <?php endif; ?>
                <span style="margin-left:auto;font-size:0.82rem;color:var(--gray)">
                    <?= $products->num_rows ?> sản phẩm
                </span>
            </div>
        </form>

        <div class="table-card">
            <div style="overflow-x:auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px">Ảnh</th>
                            <th>Sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Giá</th>
                            <th>Tồn kho</th>
                            <th>Nổi bật</th>
                            <th style="width:120px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products->num_rows > 0): ?>
                            <?php while ($p = $products->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="product-img-cell">
                                        <?php if ($p['thumbnail']): ?>
                                            <img src="../assets/images/products/<?= htmlspecialchars($p['thumbnail']) ?>" alt="">
                                        <?php else: ?>📱<?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="product-name-cell"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="product-brand-cell"><?= htmlspecialchars($p['brand_name'] ?? '') ?></div>
                                </td>
                                <td style="color:var(--gray);font-size:0.82rem"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                                <td>
                                    <div style="font-weight:800;color:#EF4444"><?= number_format($p['price'],0,',','.') ?>đ</div>
                                    <?php if ($p['old_price']): ?>
                                    <div style="font-size:0.72rem;color:var(--gray);text-decoration:line-through"><?= number_format($p['old_price'],0,',','.') ?>đ</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $s = $p['stock'];
                                    $cls = $s > 5 ? 'stock-ok' : ($s > 0 ? 'stock-low' : 'stock-empty');
                                    ?>
                                    <span class="stock-badge <?= $cls ?>"><?= $s ?></span>
                                </td>
                                <td>
                                    <?php if ($p['is_featured']): ?>
                                    <span class="featured-badge">⭐ Nổi bật</span>
                                    <?php else: ?>
                                    <span style="color:var(--gray);font-size:0.78rem">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px">
                                        <a href="manage_products.php?action=edit&id=<?= $p['id'] ?>" class="btn-edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Xóa sản phẩm này?')">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="delete_product" class="btn-delete">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px;color:var(--gray)">
                                    <div style="font-size:2.5rem;margin-bottom:8px">📦</div>
                                    Không tìm thấy sản phẩm nào
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- ══════ FORM THÊM / SỬA ══════ -->

        <form method="POST" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?>">
                <input type="hidden" name="thumbnail_current" value="<?= htmlspecialchars($edit_product['thumbnail'] ?? '') ?>">
            <?php endif; ?>

            <div class="form-card">
                <div class="form-card-header">
                    <i class="bi bi-info-circle-fill"></i> Thông tin cơ bản
                </div>
                <div class="form-card-body">

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label">Tên sản phẩm <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="<?= htmlspecialchars($edit_product['name'] ?? '') ?>"
                               placeholder="VD: iPhone 15 Pro Max 256GB" required>
                    </div>

                    <div class="form-grid-2" style="margin-bottom:16px">
                        <div class="form-group">
                            <label class="form-label">Danh mục <span class="req">*</span></label>
                            <select name="category_id" class="form-control" required>
                                <option value="">-- Chọn danh mục --</option>
                                <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= ($edit_product['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hãng <span class="req">*</span></label>
                            <select name="brand_id" class="form-control" required>
                                <option value="">-- Chọn hãng --</option>
                                <?php $brands->data_seek(0); while ($b = $brands->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>"
                                    <?= ($edit_product['brand_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-divider"></div>
                    <div class="form-section-title">Giá & Tồn kho</div>

                    <div class="form-grid-3" style="margin-bottom:16px">
                        <div class="form-group">
                            <label class="form-label">Giá bán <span class="req">*</span></label>
                            <input type="number" name="price" class="form-control"
                                   value="<?= $edit_product['price'] ?? '' ?>"
                                   placeholder="28990000" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Giá gốc (để trống nếu không giảm)</label>
                            <input type="number" name="old_price" class="form-control"
                                   value="<?= $edit_product['old_price'] ?? '' ?>"
                                   placeholder="32990000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tồn kho</label>
                            <input type="number" name="stock" class="form-control"
                                   value="<?= $edit_product['stock'] ?? 0 ?>"
                                   placeholder="50">
                        </div>
                    </div>

                    <div class="form-divider"></div>
                    <div class="form-section-title">Thông số kỹ thuật</div>

                    <div class="form-grid-2" style="margin-bottom:16px">
                        <div class="form-group">
                            <label class="form-label">RAM</label>
                            <input type="text" name="ram" class="form-control"
                                   value="<?= htmlspecialchars($edit_product['ram'] ?? '') ?>"
                                   placeholder="VD: 8GB">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bộ nhớ trong</label>
                            <input type="text" name="storage" class="form-control"
                                   value="<?= htmlspecialchars($edit_product['storage'] ?? '') ?>"
                                   placeholder="VD: 256GB">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label">Mô tả sản phẩm</label>
                        <textarea name="description" class="form-control"
                                  placeholder="Mô tả chi tiết về sản phẩm..."><?= htmlspecialchars($edit_product['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-divider"></div>
                    <div class="form-section-title">Hình ảnh & Hiển thị</div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Ảnh đại diện</label>
                            <input type="file" name="thumbnail" class="form-control"
                                   accept="image/*" onchange="previewImg(this)">
                            <div class="img-preview" id="imgPreview">
                                <?php if (!empty($edit_product['thumbnail'])): ?>
                                    <img src="../assets/images/products/<?= htmlspecialchars($edit_product['thumbnail']) ?>"
                                         alt="" id="previewEl">
                                <?php else: ?>
                                    <span id="previewEl">📱</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.72rem;color:var(--gray);margin-top:4px">
                                JPG, PNG — tối đa 2MB. File sẽ lưu vào <code>assets/images/products/</code>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tùy chọn</label>
                            <div class="form-check" style="margin-top:8px">
                                <input type="checkbox" name="is_featured" id="is_featured"
                                       <?= !empty($edit_product['is_featured']) ? 'checked' : '' ?>>
                                <label for="is_featured">⭐ Hiển thị ở mục "Sản phẩm nổi bật" trang chủ</label>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:16px">
                <button type="submit" name="save_product" class="btn-primary" style="padding:12px 28px">
                    <i class="bi bi-check-lg"></i>
                    <?= $action === 'edit' ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm' ?>
                </button>
                <a href="manage_products.php" class="btn-secondary" style="padding:12px 20px">
                    Hủy
                </a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('previewEl');
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.id  = 'previewEl';
                preview.replaceWith(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>