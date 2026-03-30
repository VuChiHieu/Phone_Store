<?php
    session_start();
    require_once '../config.php';

    // Chưa đăng nhập → về login
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit;
    }

    $product_id = (int)($_GET['product_id'] ?? 0);
    $qty        = max(1, (int)($_GET['qty'] ?? 1));
    $uid        = $_SESSION['user_id'];
    $redirect   = $_SERVER['HTTP_REFERER'] ?? '../pages/products.php';

    if (!$product_id) {
        header("Location: $redirect");
        exit;
    }

    // Kiểm tra sản phẩm tồn tại & còn hàng
    $stmt = $conn->prepare("SELECT id, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product || $product['stock'] < 1) {
        header("Location: $redirect");
        exit;
    }

    // Kiểm tra đã có trong giỏ chưa
    $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $check->bind_param("ii", $uid, $product_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {
        // Đã có → tăng số lượng (không vượt stock)
        $new_qty = min($existing['quantity'] + $qty, $product['stock']);
        $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $upd->bind_param("ii", $new_qty, $existing['id']);
        $upd->execute();
    } else {
        // Chưa có → thêm mới
        $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $ins->bind_param("iii", $uid, $product_id, $qty);
        $ins->execute();
    }

    // Quay lại trang trước
    header("Location: $redirect");
    exit;
?>