# Step-by-Step Implementation Plan: Paper Alignment + Fund Money In/Out Ledger

> **Self-Contained Recovery Blueprint:** This file is saved directly in your workspace (`d:\phpintalls\htdocs\civentrel\MISSING_PARTS_IMPLEMENTATION_PLAN.md`) so that if your session or token limit ever drops to 0%, you can simply tell the AI: *"Read `MISSING_PARTS_IMPLEMENTATION_PLAN.md` and execute from Step 1."*

---

## 1. Fund Money In / Money Out Ledger (`tr_fund_ledger`)

Currently, `tr_funds` only stores `opening_balance` and the current `balance`. Whenever `TreasuryRepository::updateFundBalance()` runs, it overwrites `balance` without recording a unified per-fund cashflow statement.

### Step 1.1: Database Table (`tr_fund_ledger`)
Add to `database/treasury.sql` and auto-create in `TreasuryRepository::ensureFundLedgerTable()`:
```sql
CREATE TABLE IF NOT EXISTS `tr_fund_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fund_code` VARCHAR(50) NOT NULL,
  `direction` ENUM('IN', 'OUT') NOT NULL,
  `transaction_type` ENUM('collection', 'online_payment', 'disbursement', 'budget_release', 'adjustment', 'reversal') NOT NULL,
  `reference_no` VARCHAR(100) NOT NULL,
  `counterparty` VARCHAR(255) DEFAULT NULL COMMENT 'Payer name, Payee, or Department',
  `description` VARCHAR(500) DEFAULT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `balance_before` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `performed_by` VARCHAR(255) DEFAULT 'System',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ledger_fund_date` (`fund_code`, `created_at`),
  INDEX `idx_ledger_direction` (`direction`),
  INDEX `idx_ledger_ref` (`reference_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 1.2: Repository & Service Hooks
1. In `src/Repositories/TreasuryRepository.php`:
   - `ensureFundLedgerTable(): void` (self-healing DDL + automatic backfill from existing `tr_collections`, disbursed `tr_disbursements`, and released `tr_budget_requests` if `tr_fund_ledger` is empty).
   - `recordFundMovement(array $entry): int`
   - `getFundLedger(?string $fundCode = null, ?string $direction = null, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 300): array`
   - `getFundCashflowSummary(?string $dateFrom = null, ?string $dateTo = null): array` (returns per-fund `opening_balance`, `money_in`, `money_out`, `balance`, `tx_in_count`, `tx_out_count`).
2. In `src/Services/TreasuryService.php` & `src/Services/CitizenPaymentService.php`:
   - Hook `recordCollection()` -> logs `IN` (`collection`)
   - Hook `updateCollection()` -> logs `IN` or `OUT` (`adjustment`)
   - Hook `deleteCollection()` -> logs `OUT` (`reversal`)
   - Hook `releaseVoucher()` -> logs `OUT` (`disbursement`)
   - Hook `processOnlinePayment()` & `CitizenPaymentService::processPayment()` -> logs `IN` (`online_payment`)
   - Hook `releaseBudget()` -> logs `OUT` (`budget_release`)

### Step 1.3: UI Updates for Fund Money In / Out
1. `pages/treasury/index.php`: Show `+ Money In`, `- Money Out`, `Opening Balance`, and `Current Balance` per fund, plus an interactive **Fund Money In/Out Ledger Modal** filterable by fund and direction (`IN` / `OUT`).
2. `pages/treasury/disbursement.php`: Show `+ In` and `- Out` totals on each Fund Position card.
3. `pages/treasury/reports.php`: Add a **Fund Cashflow (Money In / Money Out) Ledger** section that respects the `date_from` and `date_to` filters.

---

## 2. Gemini AI-Powered Taxpayer Inquiry Assistance (Objective 6, Sec. 2.4.2, Sprint 6 `SB6-04`)

1. **`src/Services/GeminiAssistantService.php`**:
   - RAG-style knowledge base grounded in the **Caloocan City Revenue Code & Citizen's Charter** (RPT rates, discounts, 2%/month delinquency interest, Business Permit checklists for New/Renewal/Retirement, Market Stall rental rules, Community Tax/Cedula, and GCash/PayMongo payment procedures).
   - Live status lookup for Application No. (`BA-...`), Assessment No. (`AS-...`), Official Receipt (`OR-...`), or Payment Reference (`PAY-...`).
   - Calls Google Gemini API (`GEMINI_API_KEY` in `.env`) with automatic bilingual (English / Tagalog / Taglish) fallback when offline or without an API key.
2. **`api/citizen/gemini-assistant.php`**:
   - JSON POST endpoint for chat queries.
3. **`includes/gemini_chat_widget.php`**:
   - Floating chat assistant widget included in `index.php`, `pages/citizen/online-payment.php`, and `includes/footer.php`.

---

## 3. Itemized Payment Breakdowns, RPT/Delinquency Monitor & Market Stall Ledger (Objectives 2, 3, 4, 7)

1. **Itemized Tax Breakdown (`Basic Tax`, `Surcharge`, `Penalty/Interest`):**
   - Self-healing columns on `tr_collections`: `basic_amount`, `surcharge_amount`, `penalty_amount`, `assessment_no`.
   - Update `pages/treasury/collection.php` and `pages/treasury/business-tax.php` to support itemized inputs and print the itemized breakdown on the Official Receipt (`#or-print-area`).
2. **Market Stall Occupancy & Delinquency Ledger (`tr_market_stalls`):**
   - Seed `tr_market_stalls` if empty and display the interactive Stall Occupancy & Rental Delinquency table in `pages/treasury/market-stall.php` with 1-click "Collect Rental" and automatic `last_payment_date` update.
3. **Unpaid & Delinquent Tax Assessments (`tr_tax_assessments`) + Taxpayer Email Reminders:**
   - Seed `tr_tax_assessments` (RPT & Business Tax unpaid/overdue records) if empty.
   - Surface the **Unpaid & Delinquent Taxes Monitor** in `pages/treasury/index.php` and `pages/treasury/reports.php` with a **"Send Reminder"** button (dispatches SMTP email via `config/mailer.php` and logs to audit trail) and a **"Collect"** button that pre-fills `collection.php` with the computed basic tax + penalty/surcharge.
4. **Metabase BI Analytics Integration:**
   - Add Metabase BI Analytics header badge, forecast line dataset on the 12-Month Trend chart, and optional `METABASE_EMBED_URL` iframe switcher in `pages/treasury/index.php` and `pages/treasury/reports.php`.

