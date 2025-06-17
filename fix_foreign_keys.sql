-- First, check the actual foreign key constraints on order_details
-- Run this query to see the constraint names:
-- SHOW CREATE TABLE `order_details`;

-- Then use the correct constraint name in the DROP statement
-- For example, if the constraint is named 'order_details_ibfk_1':
-- ALTER TABLE `order_details` DROP FOREIGN KEY `order_details_ibfk_1`;

-- Or to avoid errors, use a stored procedure to drop the constraint if it exists
DELIMITER //
CREATE PROCEDURE drop_foreign_key_if_exists()
BEGIN
    DECLARE constraint_name VARCHAR(255);
    
    -- Find the constraint name for the foreign key
    SELECT CONSTRAINT_NAME INTO constraint_name
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'order_details' 
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    LIMIT 1;
    
    -- If a constraint was found, drop it
    IF constraint_name IS NOT NULL THEN
        SET @sql = CONCAT('ALTER TABLE `order_details` DROP FOREIGN KEY `', constraint_name, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Call the procedure to drop the foreign key
CALL drop_foreign_keys_if_exists();

-- Drop the procedure after use
DROP PROCEDURE IF EXISTS drop_foreign_key_if_exists;

-- Now you can safely drop and recreate the order table
-- (Rest of your final_fix.sql script goes here)
