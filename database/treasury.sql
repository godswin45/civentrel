-- ============================================================
-- CIVENTRAL TREASURY MODULE DATABASE SCHEMA
-- Excludes Central/Superadmin Tables (Handled via API)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Table: tr_funds (Treasury Fund Balances)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_funds` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tr_funds` (`id`, `code`, `name`, `balance`, `opening_balance`) VALUES
('general', 'GF', 'General Fund', 1000000.00, 1000000.00),
('business', 'BSF', 'Business Services Fund', 500000.00, 500000.00),
('market', 'MSF', 'Market Services Fund', 300000.00, 300000.00),
('property_tax', 'PTF', 'Property Tax Fund', 2000000.00, 2000000.00),
('roads_drainage', 'RDF', 'Roads and Drainage Fund', 750000.00, 750000.00),
('education', 'EDU', 'Education Fund', 500000.00, 500000.00),
('health', 'HLTH', 'Health Fund', 400000.00, 400000.00),
('infrastructure', 'INFRA', 'Infrastructure Fund', 1500000.00, 1500000.00)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ------------------------------------------------------------
-- Table: tr_collections (Revenue Collections)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_collections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `or_number` VARCHAR(100) NOT NULL UNIQUE,
  `payer_name` VARCHAR(255) NOT NULL,
  `revenue_source` VARCHAR(150) NOT NULL,
  `fund_id` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `fund_code` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `payment_mode` ENUM('cash', 'online', 'gcash', 'maya', 'bank_transfer') NOT NULL DEFAULT 'cash',
  `collected_by` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('valid', 'cancelled', 'refunded') NOT NULL DEFAULT 'valid',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_collections_or` (`or_number`),
  INDEX `idx_collections_fund` (`fund_code`),
  INDEX `idx_collections_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tr_disbursements (Vouchers & Disbursements)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_disbursements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dv_number` VARCHAR(100) DEFAULT NULL UNIQUE,
  `voucher_no` VARCHAR(100) NOT NULL UNIQUE,
  `payee` VARCHAR(255) NOT NULL,
  `purpose` TEXT NOT NULL,
  `purpose_document` VARCHAR(255) DEFAULT NULL,
  `fund_id` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `fund_code` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'approved', 'disbursed', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_by` VARCHAR(255) DEFAULT NULL,
  `disbursement_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_disbursements_dv` (`dv_number`),
  INDEX `idx_disbursements_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tr_budget_requests (Budget Requests & Allocations)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_budget_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_no` VARCHAR(100) NOT NULL UNIQUE,
  `request_number` VARCHAR(100) DEFAULT NULL,
  `department_name` VARCHAR(255) NOT NULL,
  `department_id` INT DEFAULT NULL,
  `department_code` VARCHAR(50) NOT NULL,
  `project_title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `budget_type` ENUM('operational', 'capital', 'special_project') NOT NULL DEFAULT 'operational',
  `requested_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `fund_id` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `fund_code` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `fiscal_year` INT NOT NULL,
  `quarter` ENUM('Q1','Q2','Q3','Q4') DEFAULT 'Q1',
  `status` ENUM('pending', 'under review', 'approved', 'rejected', 'released') NOT NULL DEFAULT 'pending',
  `requested_by` VARCHAR(255) DEFAULT NULL,
  `reviewed_by` VARCHAR(255) DEFAULT NULL,
  `approved_by` VARCHAR(255) DEFAULT NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `justification` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_budget_dept` (`department_code`),
  INDEX `idx_budget_status` (`status`),
  INDEX `idx_budget_fiscal` (`fiscal_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tr_business_apps (Business Permit Applications & Tax Assessments)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_business_apps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(100) NOT NULL UNIQUE,
  `business_name` VARCHAR(255) NOT NULL,
  `owner_name` VARCHAR(255) NOT NULL,
  `business_address` TEXT NOT NULL,
  `application_type` ENUM('new', 'renewal') NOT NULL DEFAULT 'new',
  `transaction_type` ENUM('renewal', 'retirement') NOT NULL DEFAULT 'renewal',
  `gross_essential` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `gross_non_essential` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `assessed_tax` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `regulatory_fees` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_due` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('submitted', 'assessed', 'paid', 'issued', 'rejected') NOT NULL DEFAULT 'submitted',
  `documents` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_biz_app_no` (`application_no`),
  INDEX `idx_biz_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tr_online_payments (Online Citizen Payments & Callbacks)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_online_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_reference` VARCHAR(100) NOT NULL UNIQUE,
  `citizen_id` INT DEFAULT NULL,
  `citizen_name` VARCHAR(255) NOT NULL,
  `payment_source` VARCHAR(150) NOT NULL,
  `fund_id` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `fund_code` VARCHAR(50) NOT NULL DEFAULT 'GF',
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `payment_gateway` ENUM('gcash', 'maya', 'card', 'bank') NOT NULL DEFAULT 'gcash',
  `gateway_reference` VARCHAR(100) DEFAULT NULL,
  `gcash_reference` VARCHAR(100) DEFAULT NULL,
  `maya_reference` VARCHAR(100) DEFAULT NULL,
  `or_number` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `callback_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_online_pay_ref` (`payment_reference`),
  INDEX `idx_online_pay_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tr_market_stalls (Market Stall Management)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_market_stalls` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `stall_number` VARCHAR(50) NOT NULL UNIQUE,
  `section` VARCHAR(100) NOT NULL,
  `vendor_name` VARCHAR(255) DEFAULT NULL,
  `monthly_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('occupied', 'vacant', 'maintenance') NOT NULL DEFAULT 'vacant',
  `last_payment_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tr_tax_assessments (Tax Assessments)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_tax_assessments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assessment_no` VARCHAR(100) NOT NULL UNIQUE,
  `taxpayer_name` VARCHAR(255) NOT NULL,
  `tax_type` VARCHAR(100) NOT NULL,
  `assessed_value` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `tax_due` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `due_date` DATE NOT NULL,
  `status` ENUM('unpaid', 'partially_paid', 'paid', 'overdue') NOT NULL DEFAULT 'unpaid',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tr_audit_log (Audit Logging for Treasury Operations)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tr_audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `username` VARCHAR(255) DEFAULT 'System',
  `module` VARCHAR(100) NOT NULL DEFAULT 'treasury',
  `action` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `record_id` INT DEFAULT NULL,
  `old_values` LONGTEXT DEFAULT NULL,
  `new_values` LONGTEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_module` (`module`),
  INDEX `idx_audit_table` (`table_name`),
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;