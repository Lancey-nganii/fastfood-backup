-- Add auto-increment to order_id in the order table
ALTER TABLE `order` MODIFY COLUMN `order_id` INT AUTO_INCREMENT PRIMARY KEY;
