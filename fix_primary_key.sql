-- First, create a temporary table with the same structure
CREATE TABLE `order_temp` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `discount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copy data from the old table to the new one
INSERT INTO `order_temp` 
SELECT * FROM `order`;

-- Drop the old table
DROP TABLE `order`;

-- Rename the new table to the original name
RENAME TABLE `order_temp` TO `order`;
