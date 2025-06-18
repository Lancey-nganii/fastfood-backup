-- Add address column to users table
ALTER TABLE users ADD COLUMN phone_number TEXT AFTER email;

-- Set a default empty string for existing users
UPDATE users SET phone_number = '' WHERE phone_number IS NULL;
