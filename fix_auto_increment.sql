-- First, drop the primary key constraint if it exists
ALTER TABLE `order` DROP PRIMARY KEY;

-- Then modify the order_id column to be auto-increment
ALTER TABLE `order` 
MODIFY COLUMN `order_id` INT AUTO_INCREMENT,
ADD PRIMARY KEY (`order_id`);
