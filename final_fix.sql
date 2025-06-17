-- First, create a backup of the current order table
CREATE TABLE `order_backup` AS SELECT * FROM `order`;

-- Drop foreign key constraints that reference the order table
ALTER TABLE `order_details` DROP FOREIGN KEY `order_details_ibfk_1`;

-- Drop the original order table
DROP TABLE IF EXISTS `order`;

-- Create the new order table with auto-increment
CREATE TABLE `order` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Re-insert the data with explicit column names
SET @row_number = 0;
INSERT INTO `order` (
  `order_id`,
  `customer_id`,
  `employee_id`,
  `order_date`,
  `status`,
  `total_amount`,
  `payment_method`,
  `discount`
)
SELECT 
  (@row_number:=@row_number + 1) as new_id,
  `customer_id`,
  `employee_id`,
  `order_date`,
  `status`,
  `total_amount`,
  COALESCE(`payment_method`, 'cash') as `payment_method`,
  COALESCE(`discount`, 0) as `discount`
FROM `order_backup`
ORDER BY `order_id`;

-- Recreate the foreign key constraint
ALTER TABLE `order_details`
ADD CONSTRAINT `order_details_ibfk_1` 
FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`);

-- Drop the backup table if everything looks good
-- DROP TABLE IF EXISTS `order_backup`;  -- Uncomment this line after verifying the data
