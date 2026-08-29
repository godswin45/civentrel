<?php

namespace App\Services;

use App\Repositories\TreasuryRepository;

class TreasuryService {
    private $treasuryRepo;
    private $db;

    public function __construct(TreasuryRepository $treasuryRepo, $db = null) {
        $this->treasuryRepo = $treasuryRepo;
        $this->db = $db;
    }

    /**
     * Format amount as Philippine Peso
     */
    public function formatPeso($amount): string {
        return '₱' . number_format((float) $amount, 2);
    }

    /**
     * Generate OR number
     */
    public function generateORNumber(): string {
        return 'OR-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /**
     * Generate DV number
     */
    public function generateDVNumber(): string {
        return 'DV-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /**
     * Generate Business Application number
     */
    public function generateBusinessAppNo(): string {
        return 'BA-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /**
     * Get all funds
     */
    public function getFunds(): array {
        return $this->treasuryRepo->getAllFunds();
    }

    /**
     * Get recent collections
     */
    public function getRecentCollections(int $limit = 8): array {
        return $this->treasuryRepo->getRecentCollections($limit);
    }

    /**
     * Get all collections
     */
    public function getAllCollections(): array {
        return $this->treasuryRepo->getAllCollections();
    }

    /**
     * Get all vouchers
     */
    public function getAllVouchers(): array {
        return $this->treasuryRepo->getAllVouchers();
    }

    /**
     * Resolve a fund identifier (numeric id or code) to tr_funds.code for FK columns.
     */
    private function resolveFundCode($fundIdentifier, string $default = 'GF'): string {
        $identifier = trim((string) ($fundIdentifier ?: $default));

        $aliases = [
            'general'  => 'GF',
            'business' => 'BSF',
            'market'   => 'MSF',
            'property' => 'PTF',
        ];
        $normalized = strtolower($identifier);
        if (isset($aliases[$normalized])) {
            $identifier = $aliases[$normalized];
        }

        $fund = $this->treasuryRepo->getFundById($identifier);
        if (empty($fund)) {
            throw new \Exception('Unknown fund.');
        }
        return $fund['code'];
    }

    /**
     * Record a collection and credit the fund
     */
    public function recordCollection(array $input): array {
        $fundCode = $this->resolveFundCode($input['fund_id'] ?? null);
        $fund = $this->treasuryRepo->getFundById($fundCode);
        if (empty($fund)) {
            throw new \Exception('Unknown fund.');
        }

        $rec = [
            'or_number'      => $this->generateORNumber(),
            'payer_name'     => $input['payer_name'],
            'revenue_source' => $input['revenue_source'],
            'fund_code'      => $fundCode,
            'amount'         => $input['amount'],
            'payment_mode'   => $input['payment_mode'],
            'collected_by'   => $input['collected_by'] ?? null,
        ];

        $collectionId = $this->treasuryRepo->insertCollection($rec);

        // Update fund balance
        $newBalance = $fund['balance'] + $input['amount'];
        $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);

        return $this->treasuryRepo->getCollectionById($collectionId);
    }

    /**
     * Update collection
     */
    public function updateCollection(int $id, array $data): bool {
        $collection = $this->treasuryRepo->getCollectionById($id);
        if (!$collection) {
            throw new \Exception('Collection not found.');
        }

        // Calculate the difference in amount
        $amountDiff = $data['amount'] - $collection['amount'];

        // Update collection
        $success = $this->treasuryRepo->updateCollection($id, $data);

        if ($success && $amountDiff != 0) {
            $fundCode = $collection['fund_code'] ?? null;
            if ($fundCode) {
                $fund = $this->treasuryRepo->getFundById($fundCode);
                if ($fund) {
                    $newBalance = $fund['balance'] + $amountDiff;
                    $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
                }
            }
        }

        return $success;
    }

    /**
     * Delete collection
     */
    public function deleteCollection(int $id): bool {
        $collection = $this->treasuryRepo->getCollectionById($id);
        if (!$collection) {
            throw new \Exception('Collection not found.');
        }

        // Delete collection
        $success = $this->treasuryRepo->deleteCollection($id);

        if ($success) {
            $fundCode = $collection['fund_code'] ?? null;
            if ($fundCode) {
                $fund = $this->treasuryRepo->getFundById($fundCode);
                if ($fund) {
                    $newBalance = $fund['balance'] - $collection['amount'];
                    $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
                }
            }
        }

        return $success;
    }

    /**
     * Create a disbursement voucher
     */
    public function createVoucher(array $input): array {
        $fundCode = $this->resolveFundCode($input['fund_id'] ?? null);

        $dv = [
            'dv_number'   => $this->generateDVNumber(),
            'reference_no' => $this->generateDVNumber(),
            'payee_name'  => $input['payee'],
            'purpose'     => $input['purpose'],
            'fund_code'   => $fundCode,
            'amount'      => $input['amount'],
            'status'      => 'pending',
            'prepared_by' => $input['created_by'] ?? null,
        ];

        $voucherId = $this->treasuryRepo->insertVoucher($dv);
        return $this->treasuryRepo->getVoucherById($voucherId);
    }

    /**
     * Release a voucher and debit the fund
     */
    public function releaseVoucher(int $dvId): array {
        $voucher = $this->treasuryRepo->getVoucherById($dvId);
        if (empty($voucher)) {
            throw new \Exception('Voucher not found.');
        }
        if (strtolower($voucher['status']) !== 'pending') {
            throw new \Exception('Voucher already processed.');
        }

        $fundCode = $voucher['fund_code'] ?? null;
        if (empty($fundCode)) {
            throw new \Exception('Voucher has no fund assigned.');
        }

        $fund = $this->treasuryRepo->getFundById($fundCode);
        if (empty($fund)) {
            throw new \Exception('Unknown fund.');
        }

        if ($fund['balance'] < $voucher['amount']) {
            throw new \Exception('Insufficient balance in ' . $fund['name'] . ' to release this voucher.');
        }

        // Debit fund
        $newBalance = $fund['balance'] - $voucher['amount'];
        $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);

        // Update voucher status
        $this->treasuryRepo->updateVoucherStatus($dvId, 'Released');

        return $this->treasuryRepo->getVoucherById($dvId);
    }

    /**
     * Get business checklist
     */
    public function getBusinessChecklist(string $type): array {
        if ($type === 'renewal') {
            return [
                'prev_permit'  => "Copy of Business/Mayor's Permit (preceding year)",
                'tax_bill_or'  => 'Tax Bill & Official Receipt (preceding year)',
                'brgy_clear'   => 'Barangay Clearance (current year)',
                'fire_cert'    => 'Fire Safety Inspection Certificate',
                'insurance'    => 'Public Legal Liability Insurance',
                'owner_id'     => "Owner's government-issued ID",
                'afs_itr'      => 'Audited Financial Statement / ITR (if gross receipts ≥ ₱500,000)',
            ];
        }
        // retirement / closure / "resignation"
        return [
            'app_form'     => 'Duly accomplished Retirement Application Form (notarized, w/ location map)',
            'tax_bill_or'  => 'Tax Bill & Official Receipts (past 3 years)',
            'latest_permit'=> 'Latest Business Permit',
            'closure_proof'=> 'Affidavit of Closure / Partnership Dissolution / Board Resolution (per business type)',
            'owner_id'     => "Government-issued ID of owner/representative",
            'brgy_cert'    => 'Barangay Certificate with actual date of closure',
            'bir_cor'      => 'BIR Certificate of Registration',
            'sales_breakdown' => 'Certified breakdown of sales (if multiple lines/branches)',
        ];
    }

    /**
     * List business applications
     */
    public function listBusinessApps(?string $type = null): array {
        return $this->treasuryRepo->listBusinessApps($type);
    }

    /**
     * Get business application by ID
     */
    public function getBusinessApp(int $id): ?array {
        return $this->treasuryRepo->getBusinessAppById($id);
    }

    /**
     * Get business application by application number
     */
    public function getBusinessAppByNo(string $applicationNo): ?array {
        return $this->treasuryRepo->getBusinessAppByNo($applicationNo);
    }

    /**
     * Save uploaded business documents
     */
    public function saveBusinessDocuments(string $applicationNo, array $files, array $labels): array {
        $baseDir = __DIR__ . '/../../pages/treasury/uploads/' . preg_replace('/[^A-Za-z0-9\-]/', '', $applicationNo);
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $documents = [];
        foreach ($labels as $field => $label) {
            if (empty($files[$field]) || $files[$field]['error'] !== UPLOAD_ERR_OK) continue;
            
            $original = basename($files[$field]['name']);
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $safeName = $field . '_' . date('His') . ($ext ? '.' . preg_replace('/[^A-Za-z0-9]/', '', $ext) : '');
            
            move_uploaded_file($files[$field]['tmp_name'], $baseDir . '/' . $safeName);
            
            $documents[] = [
                'field'       => $field,
                'label'       => $label,
                'filename'    => $safeName,
                'original'    => $original,
                'uploaded_at' => date('c'),
            ];
        }
        return $documents;
    }

    /**
     * Create business application
     */
    public function createBusinessApp(array $input): array {
        $applicationNo = $this->generateBusinessAppNo();
        $documents = $this->saveBusinessDocuments($applicationNo, $input['files'], $this->getBusinessChecklist($input['transaction_type']));

        $fundCode = $this->resolveFundCode($input['fund_id'] ?? null, 'BSF');

        $row = [
            'application_no'   => $applicationNo,
            'transaction_type' => $input['transaction_type'],
            'business_name'    => $input['business_name'],
            'owner_name'       => $input['owner_name'],
            'barangay'         => $input['barangay'] ?? null,
            'line_of_business' => $input['line_of_business'] ?? null,
            'permit_no'        => $input['permit_no'] ?? null,
            'fund_code'        => $fundCode,
            'status'           => 'Submitted',
            'documents'        => json_encode($documents),
            'submitted_by'     => $input['submitted_by'] ?? null,
        ];

        $appId = $this->treasuryRepo->insertBusinessApp($row);
        return $this->treasuryRepo->getBusinessAppById($appId);
    }

    /**
     * Set business application status
     */
    public function setBusinessAppStatus(int $id, string $status, array $extra = []): array {
        $patch = array_merge(['status' => $status], $extra);
        $this->treasuryRepo->updateBusinessApp($id, $patch);
        return $this->treasuryRepo->getBusinessAppById($id);
    }

    /**
     * Generate payment reference number
     */
    public function generatePaymentReference(): string {
        return 'PAY-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /**
     * Return the columns currently available on tr_online_payments.
     */
    private function getOnlinePaymentColumns(): array {
        if (!$this->db || !method_exists($this->db, 'getPdo')) {
            return [];
        }

        try {
            $pdo = $this->db->getPdo();
            $columns = $pdo->query('SHOW COLUMNS FROM tr_online_payments')->fetchAll();
            return array_map(fn($column) => $column['Field'], $columns);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Filter payment payload to only supported table columns.
     */
    private function filterPaymentColumns(array $payment): array {
        $columns = $this->getOnlinePaymentColumns();
        if (empty($columns)) {
            return $payment;
        }

        return array_filter($payment, fn($field) => in_array($field, $columns, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Create online payment
     */
    public function createOnlinePayment(array $input): array {
        $fundCode = $this->resolveFundCode($input['fund_id'] ?? null);

        $payment = [
            'payment_reference' => $this->generatePaymentReference(),
            'citizen_id'        => $input['citizen_id'] ?? null,
            'citizen_name'      => $input['citizen_name'],
            'citizen_email'     => $input['citizen_email'] ?? null,
            'citizen_phone'     => $input['citizen_phone'] ?? null,
            'payment_type'      => $input['payment_type'],
            'amount'            => $input['amount'],
            'payment_source'    => $input['payment_source'],
            'fund_code'         => $fundCode,
            'status'            => 'pending',
            'payment_gateway'   => $input['payment_gateway'] ?? 'gcash',
        ];

        $payment = $this->filterPaymentColumns($payment);
        $paymentId = $this->treasuryRepo->createOnlinePayment($payment);
        return $this->treasuryRepo->getOnlinePaymentById($paymentId);
    }

    /**
     * Process online payment (called by payment gateway callback)
     */
    public function processOnlinePayment(string $paymentReference, array $gatewayResponse): array {
        $payment = $this->treasuryRepo->getOnlinePaymentByReference($paymentReference);
        if (!$payment) {
            throw new \Exception('Payment not found.');
        }

        if ($payment['status'] !== 'pending') {
            throw new \Exception('Payment already processed.');
        }

        $columns = $this->getOnlinePaymentColumns();
        $updateData = [];

        if (in_array('gateway_reference', $columns, true)) {
            $updateData['gateway_reference'] = $gatewayResponse['reference'] ?? null;
        } elseif (in_array('gcash_reference', $columns, true) && (($payment['payment_gateway'] ?? 'gcash') === 'gcash' || ($gatewayResponse['gateway'] ?? 'gcash') === 'gcash')) {
            $updateData['gcash_reference'] = $gatewayResponse['reference'] ?? null;
        } elseif (in_array('maya_reference', $columns, true)) {
            $updateData['maya_reference'] = $gatewayResponse['reference'] ?? null;
        }

        if (in_array('callback_data', $columns, true)) {
            $updateData['callback_data'] = json_encode($gatewayResponse);
        }

        // Update payment with gateway response
        $this->treasuryRepo->updateOnlinePaymentStatus($payment['id'], 'processing', $updateData);

        // Check if payment was successful
        if (isset($gatewayResponse['status']) && $gatewayResponse['status'] === 'success') {
            // Generate OR number
            $orNumber = $this->generateORNumber();

            $fundCode = $payment['fund_code'] ?? $this->resolveFundCode($payment['fund_id'] ?? null);

            // Record in revenue collections
            $paymentMode = ($payment['payment_gateway'] ?? 'gcash') === 'maya' ? 'maya' : 'gcash';

            $this->treasuryRepo->insertCollection([
                'or_number'      => $orNumber,
                'payer_name'     => $payment['citizen_name'],
                'revenue_source' => $payment['payment_source'],
                'fund_code'      => $fundCode,
                'amount'         => $payment['amount'],
                'payment_mode'   => $paymentMode,
                'collected_by'   => 'Online Payment System',
            ]);

            // Update fund balance
            $fund = $this->treasuryRepo->getFundById($fundCode);
            if ($fund) {
                $newBalance = $fund['balance'] + $payment['amount'];
                $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
            }

            // Update payment status to completed
            $this->treasuryRepo->updateOnlinePaymentStatus($payment['id'], 'completed', [
                'or_number' => $orNumber,
            ]);

            return $this->treasuryRepo->getOnlinePaymentById($payment['id']);
        } else {
            // Payment failed
            $this->treasuryRepo->updateOnlinePaymentStatus($payment['id'], 'failed');
            return $this->treasuryRepo->getOnlinePaymentById($payment['id']);
        }
    }

    /**
     * Get citizen payment history
     */
    public function getCitizenPaymentHistory(int $citizenId): array {
        return $this->treasuryRepo->getCitizenOnlinePayments($citizenId);
    }

    /**
     * Get all online payments (admin view)
     */
    public function getAllOnlinePayments(): array {
        return $this->treasuryRepo->getAllOnlinePayments();
    }

    /**
     * Generate budget request number
     */
    public function generateBudgetRequestNumber(): string {
        return 'BR-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /**
     * Create budget request
     */
    public function createBudgetRequest(array $input): array {
        $fundCode = $this->resolveFundCode($input['fund_id'] ?? null);

        $request = [
            'request_no'  => $this->generateBudgetRequestNumber(),
            'department_name' => $input['department_name'],
            'department_code' => $input['department_code'] ?? '',
            'project_title'   => $input['project_title'] ?? '',
            'description'     => $input['description'] ?? '',
            'budget_type'     => $input['budget_type'] ?? 'operational',
            'requested_amount'=> $input['requested_amount'],
            'justification'   => $input['justification'] ?? '',
            'fund_code'       => $fundCode,
            'fiscal_year'     => $input['fiscal_year'] ?? date('Y'),
            'quarter'         => $input['quarter'] ?? null,
            'status'          => 'pending',
            'requested_by'    => $input['requested_by'] ?? '',
            'reviewed_by'     => null,
            'approved_by'     => null,
            'approved_at'     => null,
            'rejection_reason' => null
        ];

        $requestId = $this->treasuryRepo->createBudgetRequest($request);
        return $this->treasuryRepo->getBudgetRequestById($requestId);
    }

    /**
     * Get all budget requests
     */
    public function getAllBudgetRequests(): array {
        return $this->treasuryRepo->getAllBudgetRequests();
    }

    /**
     * Get budget requests by department
     */
    public function getBudgetRequestsByDepartment(string $departmentCode): array {
        return $this->treasuryRepo->getBudgetRequestsByDepartment($departmentCode);
    }

    /**
     * Approve budget request
     */
    public function approveBudgetRequest(int $requestId, string $approvedBy): array {
        $request = $this->treasuryRepo->getBudgetRequestById($requestId);
        if (!$request) {
            throw new \Exception('Budget request not found.');
        }
        if (strtolower($request['status']) !== 'pending') {
            throw new \Exception('Budget request already processed.');
        }

        // Check fund balance
        $fund = $this->treasuryRepo->getFundById($request['fund_code']);
        if (!$fund) {
            throw new \Exception('Fund not found.');
        }

        if ($fund['balance'] < $request['requested_amount']) {
            throw new \Exception('Insufficient balance in ' . $fund['name'] . ' to approve this budget request.');
        }

        // Update request status
        $this->treasuryRepo->updateBudgetRequestStatus($requestId, 'approved', [
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->treasuryRepo->getBudgetRequestById($requestId);
    }

    /**
     * Release budget
     */
    public function releaseBudget(int $requestId): array {
        $request = $this->treasuryRepo->getBudgetRequestById($requestId);
        if (!$request) {
            throw new \Exception('Budget request not found.');
        }
        if (strtolower($request['status']) !== 'approved') {
            throw new \Exception('Budget request must be approved before release.');
        }

        // Debit fund
        $fund = $this->treasuryRepo->getFundById($request['fund_code']);
        if ($fund) {
            $newBalance = $fund['balance'] - $request['requested_amount'];
            $this->treasuryRepo->updateFundBalance($request['fund_code'], $newBalance);
        }

        // Update request status
        $this->treasuryRepo->updateBudgetRequestStatus($requestId, 'released');

        return $this->treasuryRepo->getBudgetRequestById($requestId);
    }

    /**
     * Update budget request status
     */
    public function updateBudgetRequestStatus(int $requestId, string $status, array $data = []): array {
        $request = $this->treasuryRepo->getBudgetRequestById($requestId);
        if (!$request) {
            throw new \Exception('Budget request not found.');
        }

        $this->treasuryRepo->updateBudgetRequestStatus($requestId, strtolower($status), $data);
        return $this->treasuryRepo->getBudgetRequestById($requestId);
    }
}