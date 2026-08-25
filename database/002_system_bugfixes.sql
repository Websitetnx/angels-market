-- System bug fixes for existing Angel's Beauty Co. databases.
-- Import this file once in phpMyAdmin after the earlier database scripts.
USE clothing_ordering;

ALTER TABLE cart
    MODIFY size VARCHAR(100) DEFAULT NULL,
    MODIFY color VARCHAR(100) DEFAULT NULL;

ALTER TABLE order_items
    MODIFY size VARCHAR(100) DEFAULT NULL,
    MODIFY color VARCHAR(100) DEFAULT NULL;
