USE phone_store;

-- Danh mục
INSERT INTO categories (name, slug) VALUES
('Điện thoại', 'dien-thoai'),
('Tai nghe', 'tai-nghe');

-- Hãng
INSERT INTO brands (name) VALUES ('Apple'), ('Samsung');

-- Sản phẩm (không cần ảnh vẫn hiện được emoji 📱)
INSERT INTO products (name, slug, category_id, brand_id, price, old_price, discount_percent, stock, description, ram, storage, is_featured) VALUES
('iPhone 15 Pro Max 256GB', 'iphone-15-pro-max', 1, 1, 28990000, 32990000, 12, 50, 'iPhone 15 Pro Max chip A17 Pro, camera 48MP, màn hình 6.7 inch Super Retina XDR.', '8GB', '256GB', 1),
('Samsung Galaxy S24 Ultra', 'samsung-s24-ultra', 1, 2, 26990000, 29990000, 10, 30, 'Samsung Galaxy S24 Ultra bút S Pen tích hợp, camera 200MP, màn hình 6.8 inch.', '12GB', '256GB', 1);