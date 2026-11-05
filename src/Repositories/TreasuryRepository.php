<?php

namespace App\Repositories;

class TreasuryRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Return the actual columns for a table so invalid keys are not inserted/updated.
     */
    private function getTableColumns(string $table): array {
        if (!$this->db || !method_exists($this->db, 'getPdo')) {
            return [];
        }

        try {
            $pdo = $this->db->getPdo();
            $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll();
            return array_map(fn($column) => $column['Field'], $columns);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Filter payload to only supported table fields.
     */
    private function filterTableColumns(string $table, array $data): array {
        $columns = $this->getTableColumns($table);
        if (empty($columns)) {
            return $data;
        }

        return array_filter($data, fn($key) => in_array($key, $columns, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Get all funds
     */
    public function getAllFunds(): array {
        return $this->db->select('tr_funds', [], '*', 'id ASC');
    }

    /**
     * Get fund by ID or code
     */
    public function getFundById(string $id): ?array {
        // Try to find by code first (most common use case)
        $funds = $this->db->select('tr_funds', ['code' => $id]);
        if (!empty($funds)) {
            return $funds[0];
        }
        // Try to find by ID as fallback
        $funds = $this->db->select('tr_funds', ['id' => $id]);
        return $funds[0] ?? null;
    }

    /**
     * Update fund balance
     */
    public function updateFundBalance(string $fundId, float $newBalance): bool {
        // Try to update by code first (most common use case)
        $result = $this->db->update('tr_funds', 
            ['balance' => $newBalance], 
            ['code' => $fundId]
        );
        if ($result > 0) return true;
        
        // Try to update by ID as fallback
        return $this->db->update('tr_funds', 
            ['balance' => $newBalance], 
            ['id' => $fundId]
        ) > 0;
    }

    /**
     * Get recent collections
     */
    public function getRecentCollections(int $limit = 8): array {
        $all = $this->db->select('tr_collections', [], '*', 'created_at DESC');
        return array_slice($all, 0, $limit);
    }

    /**
     * Get all collections
     */
    public function getAllCollections(): array {
        $all = $this->db->select('tr_collections', [], '*', 'created_at DESC');
        return array_slice($all, 0, 500);
    }

    /**
     * Get collection by ID
     */
    public function getCollectionById(int $id): ?array {
        $collections = $this->db->select('tr_collections', ['id' => $id]);
        return $collections[0] ?? null;
    }

    /**
     * Insert collection
     */
    public function insertCollection(array $data): int {
        $this->ensureCollectionBreakdownColumns();
        $filtered = $this->filterTableColumns('tr_collections', $data);
        return $this->db->insert('tr_collections', $filtered);
    }

    /**
     * Update collection
     */
    public function updateCollection(int $id, array $data): bool {
        $this->ensureCollectionBreakdownColumns();
        $filtered = $this->filterTableColumns('tr_collections', $data);
        if (empty($filtered)) {
            return false;
        }
        return $this->db->update('tr_collections', $filtered, ['id' => $id]) > 0;
    }

    /**
     * Delete collection
     */
    public function deleteCollection(int $id): bool {
        return $this->db->delete('tr_collections', ['id' => $id]) > 0;
    }

    /**
     * Get all vouchers
     */
    public function getAllVouchers(): array {
        $all = $this->db->select('tr_disbursements', [], '*', 'created_at DESC');
        return array_slice($all, 0, 500);
    }

    /**
     * Get voucher by ID
     */
    public function getVoucherById(int $id): ?array {
        $vouchers = $this->db->select('tr_disbursements', ['id' => $id]);
        return $vouchers[0] ?? null;
    }

    /**
     * Insert voucher
     */
    public function insertVoucher(array $data): int {
        $filtered = $this->filterTableColumns('tr_disbursements', $data);
        return $this->db->insert('tr_disbursements', $filtered);
    }

    /**
     * Update voucher status
     */
    public function updateVoucherStatus(int $id, string $status, array $extra = []): bool {
        // Ensure the status ENUM includes all workflow values and rejection_reason column exists
        try {
            $pdo = $this->db->getPdo();
            // Expand status ENUM to support full workflow
            $pdo->exec("ALTER TABLE tr_disbursements MODIFY COLUMN status ENUM('pending','approved','rejected','disbursed') NOT NULL DEFAULT 'pending'");
            // Add rejection_reason column if missing
            $cols = $this->getTableColumns('tr_disbursements');
            if (!in_array('rejection_reason', $cols)) {
                $pdo->exec("ALTER TABLE tr_disbursements ADD COLUMN rejection_reason TEXT NULL");
            }
        } catch (\Throwable $e) {}

        $normalizedStatus = strtolower(trim($status));
        $data = array_merge(['status' => $normalizedStatus], $extra);
        if ($normalizedStatus === 'disbursed') {
            $data['disbursement_date'] = date('Y-m-d');
        }
        $filtered = $this->filterTableColumns('tr_disbursements', $data);
        return $this->db->update('tr_disbursements', $filtered, ['id' => $id]) > 0;
    }

    /**
     * List business applications
     */
    public function listBusinessApps(?string $type = null): array {
        if ($type) {
            $all = $this->db->select('tr_business_apps', ['transaction_type' => $type], '*', 'created_at DESC');
        } else {
            $all = $this->db->select('tr_business_apps', [], '*', 'created_at DESC');
        }
        return array_slice($all, 0, 300);
    }

    /**
     * Get business application by ID
     */
    public function getBusinessAppById(int $id): ?array {
        $apps = $this->db->select('tr_business_apps', ['id' => $id]);
        if (!empty($apps)) {
            $apps[0]['documents'] = json_decode($apps[0]['documents'] ?? '[]', true);
            return $apps[0];
        }
        return null;
    }

    /**
     * Get business application by application number
     */
    public function getBusinessAppByNo(string $applicationNo): ?array {
        $apps = $this->db->select('tr_business_apps', ['application_no' => $applicationNo]);
        if (!empty($apps)) {
            $apps[0]['documents'] = json_decode($apps[0]['documents'] ?? '[]', true);
            return $apps[0];
        }
        return null;
    }

    /**
     * Insert business application
     */
    public function insertBusinessApp(array $data): int {
        $filtered = $this->filterTableColumns('tr_business_apps', $data);
        return $this->db->insert('tr_business_apps', $filtered);
    }

    /**
     * Update business application
     */
    public function updateBusinessApp(int $id, array $data): bool {
        $filtered = $this->filterTableColumns('tr_business_apps', $data);
        if (empty($filtered)) {
            return false;
        }
        return $this->db->update('tr_business_apps', $filtered, ['id' => $id]) > 0;
    }

    /**
     * Create online payment
     */
    public function createOnlinePayment(array $data): int {
        $filtered = $this->filterTableColumns('tr_online_payments', $data);
        return $this->db->insert('tr_online_payments', $filtered);
    }

    /**
     * Get online payment by reference
     */
    public function getOnlinePaymentByReference(string $reference): ?array {
        $payments = $this->db->select('tr_online_payments', ['payment_reference' => $reference]);
        return $payments[0] ?? null;
    }

    /**
     * Get online payment by ID
     */
    public function getOnlinePaymentById(int $id): ?array {
        $payments = $this->db->select('tr_online_payments', ['id' => $id]);
        return $payments[0] ?? null;
    }

    /**
     * Update online payment status
     */
    public function updateOnlinePaymentStatus(int $id, string $status, array $data = []): bool {
        $updateData = ['status' => strtolower(trim($status))];
        if (!empty($data)) {
            $updateData = array_merge($updateData, $data);
        }
        $filtered = $this->filterTableColumns('tr_online_payments', $updateData);
        return $this->db->update('tr_online_payments', $filtered, ['id' => $id]) > 0;
    }

    /**
     * Get citizen online payments
     */
    public function getCitizenOnlinePayments(int $citizenId): array {
        return $this->db->select('tr_online_payments', ['citizen_id' => $citizenId], '*', 'created_at DESC');
    }

    /**
     * Get all online payments
     */
    public function getAllOnlinePayments(): array {
        return $this->db->select('tr_online_payments', [], '*', 'created_at DESC');
    }

    /**
     * Ensure callback/webhook tracking columns exist on tr_budget_requests.
     * Self-healing so the live database does not need a manual migration.
     */
    public function ensureBudgetCallbackColumns(): void {
        static $checked = false;
        if ($checked || !$this->db || !method_exists($this->db, 'getPdo')) {
            return;
        }
        $checked = true;

        $existing = $this->getTableColumns('tr_budget_requests');
        if (empty($existing)) {
            return;
        }

        $definitions = [
            'callback_url'          => 'VARCHAR(500) NULL DEFAULT NULL',
            'callback_status'       => 'VARCHAR(30) NULL DEFAULT NULL',
            'callback_response'     => 'TEXT NULL',
            'callback_attempted_at' => 'DATETIME NULL DEFAULT NULL',
        ];

        $pdo = $this->db->getPdo();
        foreach ($definitions as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                try {
                    $pdo->exec("ALTER TABLE `tr_budget_requests` ADD COLUMN `{$column}` {$definition}");
                } catch (\Throwable $e) {
                    error_log('[Budget Callback] Could not add column ' . $column . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Create budget request
     */
    public function createBudgetRequest(array $data): int {
        $this->ensureBudgetCallbackColumns();
        $filtered = $this->filterTableColumns('tr_budget_requests', $data);
        return $this->db->insert('tr_budget_requests', $filtered);
    }

    /**
     * Get budget request by ID
     */
    public function getBudgetRequestById(int $id): ?array {
        $requests = $this->db->select('tr_budget_requests', ['id' => $id]);
        return $requests[0] ?? null;
    }

    /**
     * Get budget request by request number
     */
    public function getBudgetRequestByNumber(string $requestNumber): ?array {
        // Try request_no first (for tr_budget_requests table)
        $requests = $this->db->select('tr_budget_requests', ['request_no' => $requestNumber]);
        if (!empty($requests)) {
            return $requests[0];
        }
        // Fallback to request_number (for budget_requests table)
        $requests = $this->db->select('budget_requests', ['request_number' => $requestNumber]);
        return $requests[0] ?? null;
    }

    /**
     * Get all budget requests
     */
    public function getAllBudgetRequests(): array {
        return $this->db->select('tr_budget_requests', [], '*', 'created_at DESC');
    }

    /**
     * Get budget requests by department
     */
    public function getBudgetRequestsByDepartment(string $departmentCode): array {
        return $this->db->select('tr_budget_requests', ['department_code' => $departmentCode], '*', 'created_at DESC');
    }

    /**
     * Update budget request status
     */
    public function updateBudgetRequestStatus(int $id, string $status, array $data = []): bool {
        $this->ensureBudgetCallbackColumns();
        $updateData = ['status' => strtolower($status)];
        if (!empty($data)) {
            $updateData = array_merge($updateData, $data);
        }
        $filtered = $this->filterTableColumns('tr_budget_requests', $updateData);
        return $this->db->update('tr_budget_requests', $filtered, ['id' => $id]) > 0;
    }

    /**
     * Record the result of a callback (webhook) delivery to the requesting module.
     */
    public function recordBudgetCallbackResult(int $id, string $callbackStatus, ?string $response): bool {
        $this->ensureBudgetCallbackColumns();
        $filtered = $this->filterTableColumns('tr_budget_requests', [
            'callback_status'       => $callbackStatus,
            'callback_response'     => $response !== null ? mb_substr($response, 0, 2000) : null,
            'callback_attempted_at' => date('Y-m-d H:i:s'),
        ]);
        if (empty($filtered)) {
            return false;
        }
        return $this->db->update('tr_budget_requests', $filtered, ['id' => $id]) > 0;
    }

    /**
     * Ensure tr_fund_ledger table exists and backfill historical transactions if empty.
     */
    public function ensureFundLedgerTable(): void {
        static $ensured = false;
        if ($ensured || !$this->db || !method_exists($this->db, 'getPdo')) {
            return;
        }
        $ensured = true;

        try {
            $pdo = $this->db->getPdo();
            $pdo->exec("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // One-time automatic backfill from existing collections, disbursements, and released budgets
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `tr_fund_ledger`")->fetchColumn();
            if ($count === 0) {
                $this->backfillFundLedger($pdo);
            }
        } catch (\Throwable $e) {
            error_log('[Fund Ledger] ensureFundLedgerTable error: ' . $e->getMessage());
        }
    }

    /**
     * Backfill tr_fund_ledger from existing tr_collections, disbursed tr_disbursements, and released tr_budget_requests.
     */
    private function backfillFundLedger(\PDO $pdo): void {
        try {
            $funds = $this->getAllFunds();
            $runningBalances = [];
            foreach ($funds as $f) {
                $code = strtoupper((string) ($f['code'] ?? 'GF'));
                $runningBalances[$code] = (float) ($f['opening_balance'] ?? $f['balance'] ?? 0);
            }

            $events = [];

            // 1. Collections (Money IN)
            $collections = $this->db->select('tr_collections', [], '*', 'created_at ASC, id ASC');
            foreach ($collections as $c) {
                $mode = strtolower((string) ($c['payment_mode'] ?? 'cash'));
                $collectedBy = (string) ($c['collected_by'] ?? '');
                $isOnline = in_array($mode, ['online', 'gcash', 'maya'], true) || stripos($collectedBy, 'online') !== false || stripos($collectedBy, 'paymongo') !== false;
                $events[] = [
                    'fund_code'        => strtoupper((string) ($c['fund_code'] ?? $c['fund_id'] ?? 'GF')),
                    'direction'        => 'IN',
                    'transaction_type' => $isOnline ? 'online_payment' : 'collection',
                    'reference_no'     => (string) ($c['or_number'] ?? ('COL-' . ($c['id'] ?? ''))),
                    'counterparty'     => (string) ($c['payer_name'] ?? 'Taxpayer'),
                    'description'      => (string) ($c['revenue_source'] ?? 'Revenue Collection') . ' (' . strtoupper($mode ?: 'CASH') . ')',
                    'amount'           => (float) ($c['amount'] ?? 0),
                    'performed_by'     => $collectedBy !== '' ? $collectedBy : 'Treasury Staff',
                    'created_at'       => (string) ($c['created_at'] ?? date('Y-m-d H:i:s')),
                ];
            }

            // 2. Disbursed Vouchers (Money OUT)
            $vouchers = $this->db->select('tr_disbursements', [], '*', 'created_at ASC, id ASC');
            foreach ($vouchers as $v) {
                if (strtolower((string) ($v['status'] ?? '')) !== 'disbursed') {
                    continue;
                }
                $events[] = [
                    'fund_code'        => strtoupper((string) ($v['fund_code'] ?? $v['fund_id'] ?? 'GF')),
                    'direction'        => 'OUT',
                    'transaction_type' => 'disbursement',
                    'reference_no'     => (string) ($v['dv_number'] ?? $v['voucher_no'] ?? ('DV-' . ($v['id'] ?? ''))),
                    'counterparty'     => (string) ($v['payee'] ?? 'Payee'),
                    'description'      => (string) ($v['purpose'] ?? 'Disbursement Voucher'),
                    'amount'           => (float) ($v['amount'] ?? 0),
                    'performed_by'     => !empty($v['created_by']) ? (string) $v['created_by'] : 'Treasury Staff',
                    'created_at'       => !empty($v['disbursement_date'])
                        ? $v['disbursement_date'] . ' ' . substr((string) ($v['created_at'] ?? '12:00:00'), 11, 8)
                        : (string) ($v['created_at'] ?? date('Y-m-d H:i:s')),
                ];
            }

            // 3. Released Budget Requests (Money OUT)
            $budgets = $this->db->select('tr_budget_requests', [], '*', 'created_at ASC, id ASC');
            foreach ($budgets as $b) {
                if (strtolower((string) ($b['status'] ?? '')) !== 'released') {
                    continue;
                }
                $events[] = [
                    'fund_code'        => strtoupper((string) ($b['fund_code'] ?? $b['fund_id'] ?? 'GF')),
                    'direction'        => 'OUT',
                    'transaction_type' => 'budget_release',
                    'reference_no'     => (string) ($b['request_no'] ?? $b['request_number'] ?? ('BR-' . ($b['id'] ?? ''))),
                    'counterparty'     => (string) ($b['department_name'] ?? 'Department'),
                    'description'      => 'Budget Release: ' . (string) ($b['project_title'] ?? 'Departmental Budget'),
                    'amount'           => (float) ($b['requested_amount'] ?? 0),
                    'performed_by'     => !empty($b['approved_by']) ? (string) $b['approved_by'] : 'Treasury Admin',
                    'created_at'       => !empty($b['approved_at']) ? (string) $b['approved_at'] : (string) ($b['created_at'] ?? date('Y-m-d H:i:s')),
                ];
            }

            if (empty($events)) {
                return;
            }

            usort($events, static fn(array $a, array $b): int => strcmp($a['created_at'], $b['created_at']));

            $stmt = $pdo->prepare("
                INSERT INTO `tr_fund_ledger`
                  (`fund_code`, `direction`, `transaction_type`, `reference_no`, `counterparty`, `description`, `amount`, `balance_before`, `balance_after`, `performed_by`, `created_at`)
                VALUES
                  (:fund_code, :direction, :transaction_type, :reference_no, :counterparty, :description, :amount, :balance_before, :balance_after, :performed_by, :created_at)
            ");

            foreach ($events as $ev) {
                $fc = $ev['fund_code'] ?: 'GF';
                if (!isset($runningBalances[$fc])) {
                    $runningBalances[$fc] = 0.0;
                }
                $before = $runningBalances[$fc];
                $after = $ev['direction'] === 'IN'
                    ? $before + $ev['amount']
                    : $before - $ev['amount'];
                $runningBalances[$fc] = $after;

                $stmt->execute([
                    ':fund_code'        => $fc,
                    ':direction'        => $ev['direction'],
                    ':transaction_type' => $ev['transaction_type'],
                    ':reference_no'     => $ev['reference_no'],
                    ':counterparty'     => $ev['counterparty'],
                    ':description'      => mb_substr($ev['description'], 0, 500),
                    ':amount'           => $ev['amount'],
                    ':balance_before'   => $before,
                    ':balance_after'    => $after,
                    ':performed_by'     => $ev['performed_by'],
                    ':created_at'       => $ev['created_at'],
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[Fund Ledger] Backfill failed: ' . $e->getMessage());
        }
    }

    /**
     * Record a fund movement (Money IN or Money OUT) in tr_fund_ledger.
     */
    public function recordFundMovement(array $entry): int {
        $this->ensureFundLedgerTable();
        $row = [
            'fund_code'        => strtoupper(trim((string) ($entry['fund_code'] ?? 'GF'))),
            'direction'        => strtoupper(trim((string) ($entry['direction'] ?? 'IN'))) === 'OUT' ? 'OUT' : 'IN',
            'transaction_type' => $entry['transaction_type'] ?? 'collection',
            'reference_no'     => trim((string) ($entry['reference_no'] ?? 'N/A')),
            'counterparty'     => isset($entry['counterparty']) ? mb_substr(trim((string) $entry['counterparty']), 0, 255) : null,
            'description'      => isset($entry['description']) ? mb_substr(trim((string) $entry['description']), 0, 500) : null,
            'amount'           => round(abs((float) ($entry['amount'] ?? 0)), 2),
            'balance_before'   => round((float) ($entry['balance_before'] ?? 0), 2),
            'balance_after'    => round((float) ($entry['balance_after'] ?? 0), 2),
            'performed_by'     => !empty($entry['performed_by']) ? mb_substr(trim((string) $entry['performed_by']), 0, 255) : 'System',
        ];
        if (!empty($entry['created_at'])) {
            $row['created_at'] = $entry['created_at'];
        }
        $filtered = $this->filterTableColumns('tr_fund_ledger', $row);
        return (int) $this->db->insert('tr_fund_ledger', $filtered);
    }

    /**
     * Query the fund Money In / Money Out ledger with optional filters.
     */
    public function getFundLedger(?string $fundCode = null, ?string $direction = null, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 300): array {
        $this->ensureFundLedgerTable();
        if (!$this->db || !method_exists($this->db, 'getPdo')) {
            return [];
        }

        try {
            $pdo = $this->db->getPdo();
            $where = [];
            $params = [];

            if ($fundCode !== null && trim($fundCode) !== '') {
                $where[] = 'l.fund_code = :fund_code';
                $params[':fund_code'] = strtoupper(trim($fundCode));
            }
            if ($direction !== null && in_array(strtoupper(trim($direction)), ['IN', 'OUT'], true)) {
                $where[] = 'l.direction = :direction';
                $params[':direction'] = strtoupper(trim($direction));
            }
            if ($dateFrom !== null && trim($dateFrom) !== '') {
                $where[] = 'DATE(l.created_at) >= :date_from';
                $params[':date_from'] = trim($dateFrom);
            }
            if ($dateTo !== null && trim($dateTo) !== '') {
                $where[] = 'DATE(l.created_at) <= :date_to';
                $params[':date_to'] = trim($dateTo);
            }

            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            $safeLimit = max(1, min(2000, $limit));

            $sql = "
                SELECT l.*, COALESCE(f.name, l.fund_code) AS fund_name
                FROM `tr_fund_ledger` l
                LEFT JOIN `tr_funds` f ON f.code = l.fund_code
                {$whereSql}
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT {$safeLimit}
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[Fund Ledger] getFundLedger error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get per-fund cashflow summary (Opening Balance, Money In, Money Out, Net Flow, Current Balance).
     */
    public function getFundCashflowSummary(?string $dateFrom = null, ?string $dateTo = null): array {
        $this->ensureFundLedgerTable();
        $funds = $this->getAllFunds();
        if (empty($funds)) {
            return [];
        }

        $aggByFund = [];
        if ($this->db && method_exists($this->db, 'getPdo')) {
            try {
                $pdo = $this->db->getPdo();
                $where = [];
                $params = [];
                if ($dateFrom !== null && trim($dateFrom) !== '') {
                    $where[] = 'DATE(created_at) >= :date_from';
                    $params[':date_from'] = trim($dateFrom);
                }
                if ($dateTo !== null && trim($dateTo) !== '') {
                    $where[] = 'DATE(created_at) <= :date_to';
                    $params[':date_to'] = trim($dateTo);
                }
                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
                $sql = "
                    SELECT
                        UPPER(fund_code) AS fund_code,
                        COALESCE(SUM(CASE WHEN direction = 'IN'  THEN amount ELSE 0 END), 0) AS money_in,
                        COALESCE(SUM(CASE WHEN direction = 'OUT' THEN amount ELSE 0 END), 0) AS money_out,
                        COALESCE(SUM(CASE WHEN direction = 'IN'  THEN 1 ELSE 0 END), 0) AS tx_in_count,
                        COALESCE(SUM(CASE WHEN direction = 'OUT' THEN 1 ELSE 0 END), 0) AS tx_out_count
                    FROM `tr_fund_ledger`
                    {$whereSql}
                    GROUP BY UPPER(fund_code)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $aggByFund[$row['fund_code']] = $row;
                }
            } catch (\Throwable $e) {
                error_log('[Fund Ledger] getFundCashflowSummary error: ' . $e->getMessage());
            }
        }

        $result = [];
        foreach ($funds as $f) {
            $code = strtoupper((string) ($f['code'] ?? ''));
            $agg = $aggByFund[$code] ?? null;
            $moneyIn = (float) ($agg['money_in'] ?? 0);
            $moneyOut = (float) ($agg['money_out'] ?? 0);
            $txInCount = (int) ($agg['tx_in_count'] ?? 0);
            $txOutCount = (int) ($agg['tx_out_count'] ?? 0);

            $f['money_in']     = $moneyIn;
            $f['money_out']    = $moneyOut;
            $f['tx_in_count']  = $txInCount;
            $f['tx_out_count'] = $txOutCount;
            $f['net_flow']     = $moneyIn - $moneyOut;
            $result[] = $f;
        }
        return $result;
    }

    /**
     * Self-healing schema check: ensure itemized breakdown columns exist on tr_collections.
     */
    public function ensureCollectionBreakdownColumns(): void {
        static $checked = false;
        if ($checked || !$this->db || !method_exists($this->db, 'getPdo')) {
            return;
        }
        $checked = true;

        $existing = $this->getTableColumns('tr_collections');
        if (empty($existing)) {
            return;
        }

        $definitions = [
            'basic_amount'     => 'DECIMAL(14,2) NOT NULL DEFAULT 0.00',
            'surcharge_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0.00',
            'penalty_amount'   => 'DECIMAL(14,2) NOT NULL DEFAULT 0.00',
            'assessment_no'    => 'VARCHAR(100) NULL DEFAULT NULL',
            'stall_number'     => 'VARCHAR(50) NULL DEFAULT NULL',
        ];

        try {
            $pdo = $this->db->getPdo();
            $addedAny = false;
            foreach ($definitions as $col => $def) {
                if (!in_array($col, $existing, true)) {
                    $pdo->exec("ALTER TABLE `tr_collections` ADD COLUMN `{$col}` {$def}");
                    $addedAny = true;
                }
            }
            if ($addedAny) {
                $pdo->exec("UPDATE `tr_collections` SET `basic_amount` = `amount` WHERE `basic_amount` = 0 AND `surcharge_amount` = 0 AND `penalty_amount` = 0");
            }
        } catch (\Throwable $e) {
            error_log('[Treasury] ensureCollectionBreakdownColumns error: ' . $e->getMessage());
        }
    }

    /**
     * Ensure tr_tax_assessments and tr_market_stalls exist and seed realistic Caloocan City records if empty.
     */
    public function ensureTaxAssessmentsAndMarketStalls(): void {
        static $ensured = false;
        if ($ensured || !$this->db || !method_exists($this->db, 'getPdo')) {
            return;
        }
        $ensured = true;

        try {
            $pdo = $this->db->getPdo();

            $pdo->exec("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `tr_tax_assessments` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `assessment_no` VARCHAR(100) NOT NULL UNIQUE,
                  `taxpayer_name` VARCHAR(255) NOT NULL,
                  `taxpayer_email` VARCHAR(255) DEFAULT NULL,
                  `tax_type` VARCHAR(100) NOT NULL,
                  `assessed_value` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                  `tax_due` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                  `due_date` DATE NOT NULL,
                  `status` ENUM('unpaid', 'partially_paid', 'paid', 'overdue') NOT NULL DEFAULT 'unpaid',
                  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $taCols = $this->getTableColumns('tr_tax_assessments');
            if (!empty($taCols) && !in_array('taxpayer_email', $taCols, true)) {
                try {
                    $pdo->exec("ALTER TABLE `tr_tax_assessments` ADD COLUMN `taxpayer_email` VARCHAR(255) DEFAULT NULL AFTER `taxpayer_name`");
                } catch (\Throwable $e) {}
            }

            // Seed tr_market_stalls if empty
            $stallCount = (int) $pdo->query("SELECT COUNT(*) FROM `tr_market_stalls`")->fetchColumn();
            if ($stallCount === 0) {
                $stalls = [
                    ['A-01', 'Wet Section (Meat & Fish) - Grace Park Public Market', 'Santos, Roberto', 3500.00, 'occupied', date('Y-m-d', strtotime('-5 days'))],
                    ['A-02', 'Wet Section (Meat & Fish) - Grace Park Public Market', 'Dela Cruz, Marites', 3500.00, 'occupied', date('Y-m-d', strtotime('-42 days'))],
                    ['B-01', 'Dry Goods & Grocery - Maypajo Public Market', 'Reyes, Teresita', 2800.00, 'occupied', date('Y-m-d', strtotime('-48 days'))],
                    ['B-02', 'Dry Goods & Grocery - Maypajo Public Market', 'Mendoza, Danilo', 2800.00, 'occupied', date('Y-m-d', strtotime('-8 days'))],
                    ['C-01', 'Vegetables & Fruits - Bagong Silang Market', 'Villanueva,Elena', 2200.00, 'occupied', date('Y-m-d', strtotime('-65 days'))],
                    ['C-02', 'Vegetables & Fruits - Bagong Silang Market', null, 2200.00, 'vacant', null],
                    ['D-01', 'Carinderia / Food Court - Sangandaan Market', 'Bautista, Lorna', 4200.00, 'occupied', date('Y-m-d', strtotime('-38 days'))],
                    ['D-02', 'Carinderia / Food Court - Sangandaan Market', null, 4200.00, 'maintenance', null],
                ];
                $stmt = $pdo->prepare("INSERT INTO `tr_market_stalls` (`stall_number`, `section`, `vendor_name`, `monthly_fee`, `status`, `last_payment_date`) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($stalls as $s) {
                    $stmt->execute($s);
                }
            }

            // Seed tr_tax_assessments if empty
            $assessCount = (int) $pdo->query("SELECT COUNT(*) FROM `tr_tax_assessments`")->fetchColumn();
            if ($assessCount === 0) {
                $assessments = [
                    ['AS-2026-RPT001', 'Dela Cruz, Juan (TCT-104920 Brgy. 86)', 'balcogodwin5@gmail.com', 'Real Property Tax', 1850000.00, 18500.00, date('Y-m-d', strtotime('-65 days')), 'overdue'],
                    ['AS-2026-RPT002', 'Golden Metro Realty Corp. (Monumento)', 'balcogodwin5@gmail.com', 'Real Property Tax', 6400000.00, 64000.00, date('Y-m-d', strtotime('-35 days')), 'overdue'],
                    ['AS-2026-BIZ003', 'North Caloocan Hardware & Supply', 'balcogodwin5@gmail.com', 'Business Tax', 950000.00, 14250.00, date('Y-m-d', strtotime('-45 days')), 'overdue'],
                    ['AS-2026-BIZ004', 'Grace Park Auto Parts & Trading', 'balcogodwin5@gmail.com', 'Business Tax', 1200000.00, 18000.00, date('Y-m-d', strtotime('+12 days')), 'unpaid'],
                    ['AS-2026-RPT005', 'Reyes, Maria Clara (Camarin Res. Lot)', 'balcogodwin5@gmail.com', 'Real Property Tax', 780000.00, 7800.00, date('Y-m-d', strtotime('+20 days')), 'unpaid'],
                    ['AS-2026-BIZ006', 'Sangandaan Bakeshop & Cafe', 'balcogodwin5@gmail.com', 'Mayor\'s Permit Fee', 320000.00, 4800.00, date('Y-m-d', strtotime('-15 days')), 'paid'],
                ];
                $stmt = $pdo->prepare("INSERT INTO `tr_tax_assessments` (`assessment_no`, `taxpayer_name`, `taxpayer_email`, `tax_type`, `assessed_value`, `tax_due`, `due_date`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($assessments as $a) {
                    $stmt->execute($a);
                }
            }
        } catch (\Throwable $e) {
            error_log('[Treasury] ensureTaxAssessmentsAndMarketStalls error: ' . $e->getMessage());
        }
    }

    /**
     * Get all tax assessments (optionally filtered by status).
     */
    public function getAllTaxAssessments(?string $status = null): array {
        $this->ensureTaxAssessmentsAndMarketStalls();
        if ($status !== null && trim($status) !== '') {
            return $this->db->select('tr_tax_assessments', ['status' => strtolower(trim($status))], '*', 'due_date ASC, id DESC');
        }
        return $this->db->select('tr_tax_assessments', [], '*', 'FIELD(status, "overdue", "unpaid", "partially_paid", "paid"), due_date ASC');
    }

    /**
     * Get tax assessment by ID.
     */
    public function getTaxAssessmentById(int $id): ?array {
        $this->ensureTaxAssessmentsAndMarketStalls();
        $rows = $this->db->select('tr_tax_assessments', ['id' => $id]);
        return $rows[0] ?? null;
    }

    /**
     * Get tax assessment by assessment number.
     */
    public function getTaxAssessmentByNo(string $assessmentNo): ?array {
        $this->ensureTaxAssessmentsAndMarketStalls();
        $rows = $this->db->select('tr_tax_assessments', ['assessment_no' => trim($assessmentNo)]);
        return $rows[0] ?? null;
    }

    /**
     * Insert a new tax assessment.
     */
    public function insertTaxAssessment(array $data): int {
        $this->ensureTaxAssessmentsAndMarketStalls();
        $filtered = $this->filterTableColumns('tr_tax_assessments', $data);
        return (int) $this->db->insert('tr_tax_assessments', $filtered);
    }

    /**
     * Update tax assessment status.
     */
    public function updateTaxAssessmentStatus(int $id, string $status): bool {
        $this->ensureTaxAssessmentsAndMarketStalls();
        return $this->db->update('tr_tax_assessments', ['status' => strtolower(trim($status))], ['id' => $id]) > 0;
    }

    /**
     * Get all market stalls.
     */
    public function getAllMarketStalls(): array {
        $this->ensureTaxAssessmentsAndMarketStalls();
        return $this->db->select('tr_market_stalls', [], '*', 'stall_number ASC');
    }

    /**
     * Get market stall by stall number.
     */
    public function getMarketStallByNumber(string $stallNumber): ?array {
        $this->ensureTaxAssessmentsAndMarketStalls();
        $rows = $this->db->select('tr_market_stalls', ['stall_number' => trim($stallNumber)]);
        return $rows[0] ?? null;
    }

    /**
     * Update market stall last payment date and status.
     */
    public function updateMarketStallPaymentDate(string $stallNumber, string $date, ?string $vendorName = null): bool {
        $this->ensureTaxAssessmentsAndMarketStalls();
        $update = [
            'last_payment_date' => $date,
            'status'            => 'occupied',
        ];
        if ($vendorName !== null && trim($vendorName) !== '') {
            $update['vendor_name'] = trim($vendorName);
        }
        return $this->db->update('tr_market_stalls', $update, ['stall_number' => trim($stallNumber)]) > 0;
    }

}