<?php

namespace App\Repositories;

class TreasuryRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
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
        return $this->db->insert('tr_collections', $data);
    }

    /**
     * Update collection
     */
    public function updateCollection(int $id, array $data): bool {
        return $this->db->update('tr_collections', $data, ['id' => $id]) > 0;
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
        return $this->db->insert('tr_disbursements', $data);
    }

    /**
     * Update voucher status
     */
    public function updateVoucherStatus(int $id, string $status): bool {
        $data = ['status' => strtolower($status)];
        if (strtolower($status) === 'disbursed') {
            $data['disbursement_date'] = date('Y-m-d');
        }
        return $this->db->update('tr_disbursements', $data, ['id' => $id]) > 0;
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
        return $this->db->insert('tr_business_apps', $data);
    }

    /**
     * Update business application
     */
    public function updateBusinessApp(int $id, array $data): bool {
        return $this->db->update('tr_business_apps', $data, ['id' => $id]) > 0;
    }

    /**
     * Create online payment
     */
    public function createOnlinePayment(array $data): int {
        return $this->db->insert('tr_online_payments', $data);
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
        $updateData = ['status' => $status];
        if (!empty($data)) {
            $updateData = array_merge($updateData, $data);
        }
        return $this->db->update('tr_online_payments', $updateData, ['id' => $id]) > 0;
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
     * Create budget request
     */
    public function createBudgetRequest(array $data): int {
        return $this->db->insert('tr_budget_requests', $data);
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
        $updateData = ['status' => strtolower($status)];
        if (!empty($data)) {
            $updateData = array_merge($updateData, $data);
        }
        return $this->db->update('tr_budget_requests', $updateData, ['id' => $id]) > 0;
    }

}