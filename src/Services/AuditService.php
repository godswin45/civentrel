<?php

namespace App\Services;

use App\Repositories\TreasuryRepository;

class AuditService {
    private $treasuryRepo;
    private $db;
    
    public function __construct(TreasuryRepository $treasuryRepo, $db) {
        $this->treasuryRepo = $treasuryRepo;
        $this->db = $db;
    }
    
    /**
     * Log a transaction to the audit log
     */
    public function logTransaction(array $data): bool {
        $module = $data['module'] ?? $this->resolveModuleFromTable($data['table_name'] ?? '');

        $logData = [
            'user_id' => $data['user_id'] ?? null,
            'username' => $data['username'] ?? 'System',
            'module' => $module,
            'action' => $data['action'],
            'table_name' => $data['table_name'] ?? '',
            'record_id' => $data['record_id'] ?? null,
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'ip_address' => $data['ip_address'] ?? $this->getClientIP(),
        ];
        
        return $this->db->insert('tr_audit_log', $logData) > 0;
    }

    /**
     * Resolve audit module from table name for feature filtering.
     */
    private function resolveModuleFromTable(string $tableName): string {
        $table = strtolower(trim($tableName));

        $map = [
            'tr_collections' => 'collection',
            'tr_disbursements' => 'disbursement',
            'tr_budget_requests' => 'budget',
            'tr_business_apps' => 'business',
            'tr_online_payments' => 'online_payment',
            'tr_market_stalls' => 'market_stall',
            'tr_tax_assessments' => 'tax_assessment',
        ];

        return $map[$table] ?? 'treasury';
    }
    
    /**
     * Get recent transactions for a specific module
     */
    public function getRecentTransactions(string $module, int $limit = 10): array {
        $pdo = $this->db->getPdo();

        if ($module === 'treasury' || $module === 'all') {
            $query = "SELECT * FROM tr_audit_log ORDER BY created_at DESC LIMIT :limit";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':limit', (int) $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $query = "SELECT * FROM tr_audit_log 
                   WHERE module = :module 
                   OR (module IS NULL AND table_name LIKE :module_pattern)
                   ORDER BY created_at DESC 
                   LIMIT :limit";

        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':module', strtolower(trim($module)), \PDO::PARAM_STR);
        $stmt->bindValue(':module_pattern', "%" . strtolower(trim($module)) . "%", \PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int) $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get transaction history for a specific record
     */
    public function getRecordHistory(string $tableName, int $recordId): array {
        $pdo = $this->db->getPdo();
        
        $query = "SELECT * FROM tr_audit_log 
                   WHERE table_name = :table_name AND record_id = :record_id 
                   ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['table_name' => $tableName, 'record_id' => $recordId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user transaction history
     */
    public function getUserTransactions(int $userId, int $limit = 20): array {
        $pdo = $this->db->getPdo();
        
        $query = "SELECT * FROM tr_audit_log 
                   WHERE user_id = :user_id 
                   ORDER BY created_at DESC 
                   LIMIT :limit";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $userId, 'limit' => $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get transaction statistics
     */
    public function getTransactionStats(): array {
        $pdo = $this->db->getPdo();
        
        $stats = [
            'total_transactions' => 0,
            'today_transactions' => 0,
            'by_action' => [],
            'by_table' => []
        ];
        
        // Total transactions
        $total = $pdo->query("SELECT COUNT(*) as count FROM tr_audit_log")->fetch();
        $stats['total_transactions'] = $total['count'];
        
        // Today's transactions
        $today = date('Y-m-d');
        $todayQuery = $pdo->prepare("SELECT COUNT(*) as count FROM tr_audit_log WHERE DATE(created_at) = :today");
        $todayQuery->execute(['today' => $today]);
        $todayTotal = $todayQuery->fetch();
        $stats['today_transactions'] = $todayTotal['count'];
        
        // By action
        $byAction = $pdo->query("SELECT action, COUNT(*) as count FROM tr_audit_log GROUP BY action")->fetchAll();
        foreach ($byAction as $row) {
            $stats['by_action'][$row['action']] = $row['count'];
        }
        
        // By table
        $byTable = $pdo->query("SELECT table_name, COUNT(*) as count FROM tr_audit_log GROUP BY table_name")->fetchAll();
        foreach ($byTable as $row) {
            $stats['by_table'][$row['table_name']] = $row['count'];
        }
        
        return $stats;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }
}
?>