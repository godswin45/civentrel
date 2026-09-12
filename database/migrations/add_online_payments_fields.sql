-- Migration: Add fields to tr_online_payments for idempotency and detailed payment tracking
-- This migration enhances the payment table to support complete payment flow with duplicate protection

-- Alter tr_online_payments table
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `idempotency_key` VARCHAR(255) UNIQUE DEFAULT NULL AFTER `gateway_reference`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `taxpayer_name` VARCHAR(255) DEFAULT NULL AFTER `citizen_name`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `account_number` VARCHAR(100) DEFAULT NULL AFTER `taxpayer_name`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) DEFAULT NULL AFTER `account_number`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `payment_type` VARCHAR(150) DEFAULT NULL AFTER `payment_source`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `service_fee` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `total_amount` DECIMAL(14,2) DEFAULT 0.00 AFTER `service_fee`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(50) DEFAULT 'gcash' AFTER `payment_gateway`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL AFTER `payment_method`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `source` VARCHAR(100) DEFAULT 'web' AFTER `notes`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `municipality_code` VARCHAR(50) DEFAULT NULL AFTER `source`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `transaction_id` VARCHAR(100) UNIQUE DEFAULT NULL AFTER `payment_reference`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `reference_no` VARCHAR(100) UNIQUE DEFAULT NULL AFTER `transaction_id`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `receipt_no` VARCHAR(100) UNIQUE DEFAULT NULL AFTER `reference_no`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `gateway_response` JSON DEFAULT NULL AFTER `callback_data`;
ALTER TABLE `tr_online_payments` ADD COLUMN IF NOT EXISTS `settled_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`;

-- Create index for idempotency key
CREATE INDEX IF NOT EXISTS `idx_online_pay_idempotency` ON `tr_online_payments` (`idempotency_key`);

-- Create index for transaction_id
CREATE INDEX IF NOT EXISTS `idx_online_pay_txn_id` ON `tr_online_payments` (`transaction_id`);

-- Create index for citizen
CREATE INDEX IF NOT EXISTS `idx_online_pay_citizen` ON `tr_online_payments` (`citizen_id`);

-- Create index for status and created_at for efficient querying
CREATE INDEX IF NOT EXISTS `idx_online_pay_status_date` ON `tr_online_payments` (`status`, `created_at`);

-- Create Payment Gateway Responses Log table for audit trail
CREATE TABLE IF NOT EXISTS `tr_payment_gateway_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT NOT NULL,
  `gateway_name` VARCHAR(50) NOT NULL,
  `request_payload` JSON NOT NULL,
  `response_payload` JSON NOT NULL,
  `response_code` INT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`payment_id`) REFERENCES `tr_online_payments`(`id`) ON DELETE CASCADE,
  INDEX `idx_gateway_log_payment` (`payment_id`),
  INDEX `idx_gateway_log_gateway` (`gateway_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update existing online_payments records to have default values
UPDATE `tr_online_payments` SET `transaction_id` = CONCAT('TXN-', DATE_FORMAT(`created_at`, '%Y%m%d'), '-', LPAD(id, 6, '0')) WHERE `transaction_id` IS NULL;
UPDATE `tr_online_payments` SET `reference_no` = CONCAT('REF-', DATE_FORMAT(`created_at`, '%Y%m%d'), '-', LPAD(id, 6, '0')) WHERE `reference_no` IS NULL;
UPDATE `tr_online_payments` SET `receipt_no` = CONCAT('OR-', YEAR(`created_at`), '-', LPAD(id, 6, '0')) WHERE `receipt_no` IS NULL;
