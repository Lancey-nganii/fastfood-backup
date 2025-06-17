-- Step 1: Create a backup of the current order table
CREATE TABLE `order_backup` AS SELECT * FROM `order`;

-- Step 2: Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Step 3: Drop and recreate the order table with auto-increment
DROP TABLE IF EXISTS `order`;

CREATE TABLE `order` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 4: Re-insert the data with new auto-incremented IDs
INSERT INTO `order` (
  `customer_id`,
  `employee_id`,
  `order_date`,
  `status`,
  `total_amount`,
  `payment_method`,
  `discount`
)
SELECT 
  `customer_id`,
  `employee_id`,
  `order_date`,
  `status`,
  `total_amount`,
  COALESCE(`payment_method`, 'cash') as `payment_method`,
  COALESCE(`discount`, 0) as `discount`
FROM `order_backup`;

-- Step 5: Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Step 6: Verify the table structure
-- SHOW CREATE TABLE `order`;

-- Step 7: Drop the backup table if everything looks good
-- DROP TABLE IF EXISTS `order_backup`;  -- Uncomment this line after verifying the data
