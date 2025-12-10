-- Update payments table to add missing columns
-- Run this script if you have an existing database

-- Add missing columns to payments table
ALTER TABLE payments 
ADD COLUMN payer_name VARCHAR(100) NULL AFTER transaction_id,
ADD COLUMN payer_phone VARCHAR(15) NULL AFTER payer_name;

-- Update payment_method enum to include new payment methods
ALTER TABLE payments 
MODIFY COLUMN payment_method ENUM('cash', 'card', 'bkash', 'nagad', 'rocket', 'debit_card', 'one_card') DEFAULT 'cash';

-- Update existing sample payments with payer information
UPDATE payments SET 
    payer_name = 'Deluwar Hosen',
    payer_phone = '+880186182502'
WHERE id = 1;

UPDATE payments SET 
    payer_name = 'makib',
    payer_phone = '+8801846182502'
WHERE id = 2;

UPDATE payments SET 
    payer_name = 'Sisir',
    payer_phone = '+8801912345678'
WHERE id = 3;

UPDATE payments SET 
    payer_name = 'Talha',
    payer_phone = '+8801612345678'
WHERE id = 4;
