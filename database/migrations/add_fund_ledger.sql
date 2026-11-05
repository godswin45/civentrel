-- Migration: Add Fund Money In / Money Out Ledger (tr_fund_ledger)
-- Tracks every cash inflow (IN) and outflow (OUT) per fund with running balances.

CREATE TABLE IF NOT EXISTS `tr_fund_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fund_code` VARCHAR(20) NOT NULL,
  `direction` ENUM('IN', 'OUT') NOT NULL,
  `transaction_type` ENUM('collection', 'online_payment', 'disbursement', 'budget_release', 'adjustment', 'reversal') NOT NULL,
  `reference_no` VARCHAR(100) NOT NULL,
  `counterparty` VARCHAR(255) DEFAULT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `balance_before` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `performed_by` VARCHAR(255) DEFAULT 'System',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ledger_fund` (`fund_code`),
  INDEX `idx_ledger_dir` (`direction`),
  INDEX `idx_ledger_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
