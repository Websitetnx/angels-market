-- Add generated catalog images to the existing beauty products.
-- Import this file once in phpMyAdmin after database/clothing_ordering.sql.
USE clothing_ordering;

START TRANSACTION;

DELETE pi
FROM product_images pi
INNER JOIN products p ON p.id = pi.product_id
WHERE p.product_name IN (
    'Hyaluronic Acid Hydrating Serum 30ml',
    'Vitamin C Brightening Moisturizer SPF30',
    'Velvet Matte Liquid Lipstick Set (6 Shades)',
    '16-Color Shimmer & Matte Eyeshadow Palette',
    'Keratin Repair Shampoo & Conditioner Set',
    'Bloom Garden Eau de Parfum 50ml',
    'Gel Nail Polish Collection (12 Colors)',
    'Rose Petal Lip Sleeping Mask',
    'Shea Butter Whipped Body Cream 200ml',
    'Rose Gold Makeup Brush Set (15 Pieces)',
    'Tea Tree Oil Acne Clearing Spot Treatment',
    'Full Coverage Cushion Foundation SPF50'
);

INSERT INTO product_images (product_id, image, is_primary)
SELECT
    p.id,
    CASE p.product_name
        WHEN 'Hyaluronic Acid Hydrating Serum 30ml' THEN 'beauty-hyaluronic-serum.webp'
        WHEN 'Vitamin C Brightening Moisturizer SPF30' THEN 'beauty-vitamin-c-moisturizer.webp'
        WHEN 'Velvet Matte Liquid Lipstick Set (6 Shades)' THEN 'beauty-matte-lipstick-set.webp'
        WHEN '16-Color Shimmer & Matte Eyeshadow Palette' THEN 'beauty-eyeshadow-palette.webp'
        WHEN 'Keratin Repair Shampoo & Conditioner Set' THEN 'beauty-keratin-hair-set.webp'
        WHEN 'Bloom Garden Eau de Parfum 50ml' THEN 'beauty-bloom-perfume.webp'
        WHEN 'Gel Nail Polish Collection (12 Colors)' THEN 'beauty-gel-nail-polish-set.webp'
        WHEN 'Rose Petal Lip Sleeping Mask' THEN 'beauty-rose-lip-mask.webp'
        WHEN 'Shea Butter Whipped Body Cream 200ml' THEN 'beauty-shea-body-cream.webp'
        WHEN 'Rose Gold Makeup Brush Set (15 Pieces)' THEN 'beauty-makeup-brush-set.webp'
        WHEN 'Tea Tree Oil Acne Clearing Spot Treatment' THEN 'beauty-tea-tree-treatment.webp'
        WHEN 'Full Coverage Cushion Foundation SPF50' THEN 'beauty-cushion-foundation.webp'
    END,
    1
FROM products p
WHERE p.product_name IN (
    'Hyaluronic Acid Hydrating Serum 30ml',
    'Vitamin C Brightening Moisturizer SPF30',
    'Velvet Matte Liquid Lipstick Set (6 Shades)',
    '16-Color Shimmer & Matte Eyeshadow Palette',
    'Keratin Repair Shampoo & Conditioner Set',
    'Bloom Garden Eau de Parfum 50ml',
    'Gel Nail Polish Collection (12 Colors)',
    'Rose Petal Lip Sleeping Mask',
    'Shea Butter Whipped Body Cream 200ml',
    'Rose Gold Makeup Brush Set (15 Pieces)',
    'Tea Tree Oil Acne Clearing Spot Treatment',
    'Full Coverage Cushion Foundation SPF50'
);

COMMIT;
