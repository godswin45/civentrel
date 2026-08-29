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
        $logData = [
            'user_id' => $data['user_id'] ?? null,
            'username' => $data['username'] ?? 'System',
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
     * Get recent transactions for a specific module
     */
    public function getRecentTransactions(string $module, int $limit = 10): array {
        $pdo = $this->db->getPdo();
        
        $query = "SELECT * FROM tr_audit_log 
                   WHERE table_name LIKE :module 
                   ORDER BY created_at DESC 
                   LIMIT :limit";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['module' => "%$module%", 'limit' => $limit]);
        
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