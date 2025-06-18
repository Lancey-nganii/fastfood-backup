-- Add address component columns to customer table if they don't exist
ALTER TABLE customer 
ADD COLUMN IF NOT EXISTS street VARCHAR(255) AFTER email,
ADD COLUMN IF NOT EXISTS city VARCHAR(100) AFTER street,
ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20) AFTER city;

-- If there are existing addresses in a single 'address' column, you can split them here
-- This is just an example - adjust based on your actual address format
-- UPDATE customer 
-- SET street = SUBSTRING_INDEX(address, ',', 1),
--     city = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(address, ',', 2), ',', -1)),
--     postal_code = TRIM(SUBSTRING_INDEX(address, ',', -1))
-- WHERE address IS NOT NULL AND address != '';

-- After migrating data, you can drop the old address column if it exists
-- ALTER TABLE customer DROP COLUMN IF EXISTS address;
