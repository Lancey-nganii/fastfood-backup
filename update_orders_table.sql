-- Add missing columns to the order table
ALTER TABLE `order` 
ADD COLUMN `payment_method` VARCHAR(50) AFTER `total_amount`,
ADD COLUMN `discount` DECIMAL(10,2) DEFAULT 0.00 AFTER `payment_method`;

-- Rename order_status to status if it exists, or add it if it doesn't
ALTER TABLE `order` 
CHANGE COLUMN `order_status` `status` VARCHAR(50) NOT NULL DEFAULT 'Pending';
