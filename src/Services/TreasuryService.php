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
     * Generate voucher number for tr_disbursements.voucher_no
     */
    public function generateVoucherNumber(): string {
        return 'V-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
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
     * Get per-fund cashflow summary (Opening Balance, Money In, Money Out, Net Flow, Current Balance)
     */
    public function getFundCashflowSummary(?string $dateFrom = null, ?string $dateTo = null): array {
        return $this->treasuryRepo->getFundCashflowSummary($dateFrom, $dateTo);
    }

    /**
     * Get fund Money In / Money Out ledger entries
     */
    public function getFundLedger(?string $fundCode = null, ?string $direction = null, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 300): array {
        return $this->treasuryRepo->getFundLedger($fundCode, $direction, $dateFrom, $dateTo, $limit);
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
     * Import disbursement vouchers from normalized rows.
     */
    public function importVouchers(array $rows, ?string $createdBy = null): array {
        $result = ['imported' => 0, 'errors' => []];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            try {
                $payee = trim((string) ($row['payee'] ?? $row['payee_name'] ?? ''));
                $purpose = trim((string) ($row['purpose'] ?? ''));
                $amount = (float) ($row['amount'] ?? 0);
                if ($payee === '' || $purpose === '' || $amount <= 0) {
                    throw new \Exception('payee, purpose, and a positive amount are required');
                }

                $voucher = $this->createVoucher([
                    'payee' => $payee,
                    'purpose' => $purpose,
                    'fund_id' => $row['fund_id'] ?? $row['fund_code'] ?? 'GF',
                    'amount' => $amount,
                    'created_by' => $createdBy,
                ]);
                $result['imported']++;
            } catch (\Throwable $e) {
                $result['errors'][] = 'Row ' . $line . ': ' . $e->getMessage();
            }
        }
        return $result;
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
        $paymentMode = $this->normalizePaymentMode($input['payment_mode'] ?? 'cash');

        $totalAmount = round((float) ($input['amount'] ?? 0), 2);
        $surchargeAmount = round(max(0, (float) ($input['surcharge_amount'] ?? 0)), 2);
        $penaltyAmount = round(max(0, (float) ($input['penalty_amount'] ?? 0)), 2);
        $basicAmount = isset($input['basic_amount']) && (float) $input['basic_amount'] > 0
            ? round((float) $input['basic_amount'], 2)
            : round(max(0, $totalAmount - $surchargeAmount - $penaltyAmount), 2);
        $assessmentNo = !empty($input['assessment_no']) ? trim((string) $input['assessment_no']) : null;
        $stallNumber = !empty($input['stall_number']) ? trim((string) $input['stall_number']) : null;

        $rec = [
            'or_number'        => $this->generateORNumber(),
            'payer_name'       => $input['payer_name'],
            'revenue_source'   => $input['revenue_source'],
            'fund_id'          => $input['fund_id'] ?? $fundCode,
            'fund_code'        => $fundCode,
            'basic_amount'     => $basicAmount,
            'surcharge_amount' => $surchargeAmount,
            'penalty_amount'   => $penaltyAmount,
            'amount'           => $totalAmount,
            'assessment_no'    => $assessmentNo,
            'stall_number'     => $stallNumber,
            'payment_mode'     => $paymentMode,
            'collected_by'     => $input['collected_by'] ?? null,
        ];

        $collectionId = $this->treasuryRepo->insertCollection($rec);

        // If linked to a tax assessment, mark it as paid
        if ($assessmentNo !== null && $assessmentNo !== '') {
            $assessment = $this->treasuryRepo->getTaxAssessmentByNo($assessmentNo);
            if ($assessment) {
                $this->treasuryRepo->updateTaxAssessmentStatus((int) $assessment['id'], 'paid');
            }
        }

        // If linked to a market stall, update last_payment_date and status
        if ($stallNumber !== null && $stallNumber !== '') {
            $this->treasuryRepo->updateMarketStallPaymentDate($stallNumber, date('Y-m-d'), $input['payer_name'] ?? null);
        }

        // Update fund balance & record Money IN
        $balanceBefore = (float) ($fund['balance'] ?? 0);
        $newBalance = $balanceBefore + $totalAmount;
        $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
        $this->treasuryRepo->recordFundMovement([
            'fund_code'        => $fundCode,
            'direction'        => 'IN',
            'transaction_type' => 'collection',
            'reference_no'     => $rec['or_number'],
            'counterparty'     => $input['payer_name'],
            'description'      => $input['revenue_source'] . ' (' . strtoupper($paymentMode) . ')' . ($assessmentNo ? ' [Assessment: ' . $assessmentNo . ']' : '') . ($stallNumber ? ' [Stall: ' . $stallNumber . ']' : ''),
            'amount'           => $totalAmount,
            'balance_before'   => $balanceBefore,
            'balance_after'    => $newBalance,
            'performed_by'     => $input['collected_by'] ?? 'Treasury Staff',
        ]);

        return $this->treasuryRepo->getCollectionById($collectionId);
    }

    /**
     * Get all tax assessments (Real Property Tax, Business Tax, etc.)
     */
    public function getAllTaxAssessments(?string $status = null): array {
        return $this->treasuryRepo->getAllTaxAssessments($status);
    }

    /**
     * Get one tax assessment by ID
     */
    public function getTaxAssessmentById(int $id): ?array {
        return $this->treasuryRepo->getTaxAssessmentById($id);
    }

    /**
     * Create a new tax assessment
     */
    public function createTaxAssessment(array $input): int {
        $dueDate = trim((string) ($input['due_date'] ?? date('Y-m-d')));
        $status = $dueDate < date('Y-m-d') ? 'overdue' : 'unpaid';
        $prefix = stripos((string) ($input['tax_type'] ?? ''), 'Property') !== false ? 'RPT' : 'BIZ';
        $assessmentNo = !empty($input['assessment_no'])
            ? trim((string) $input['assessment_no'])
            : ('AS-' . date('Y') . '-' . $prefix . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4)));

        return $this->treasuryRepo->insertTaxAssessment([
            'assessment_no'  => $assessmentNo,
            'taxpayer_name'  => trim((string) ($input['taxpayer_name'] ?? '')),
            'taxpayer_email' => !empty($input['taxpayer_email']) ? trim((string) $input['taxpayer_email']) : null,
            'tax_type'       => trim((string) ($input['tax_type'] ?? 'Real Property Tax')),
            'assessed_value' => (float) ($input['assessed_value'] ?? 0),
            'tax_due'        => (float) ($input['tax_due'] ?? 0),
            'due_date'       => $dueDate,
            'status'         => $status,
        ]);
    }

    /**
     * Get all market stalls
     */
    public function getAllMarketStalls(): array {
        return $this->treasuryRepo->getAllMarketStalls();
    }

    /**
     * Dispatch an official Billing / Overdue Payment Reminder Notice to a taxpayer via SMTP.
     */
    public function sendTaxpayerReminder(int $assessmentId, ?string $sentBy = null): array {
        $assessment = $this->treasuryRepo->getTaxAssessmentById($assessmentId);
        if (!$assessment) {
            throw new \Exception('Tax assessment not found.');
        }

        $recipientEmail = !empty($assessment['taxpayer_email']) ? $assessment['taxpayer_email'] : 'balcogodwin5@gmail.com';
        $taxpayerName = $assessment['taxpayer_name'] ?? 'Valued Taxpayer';
        $assessmentNo = $assessment['assessment_no'] ?? ('AS-' . $assessmentId);
        $taxType = $assessment['tax_type'] ?? 'Local Government Tax';
        $basicDue = (float) ($assessment['basic_amount'] ?? $assessment['tax_due'] ?? 0);
        $dueDate = $assessment['due_date'] ?? date('Y-m-d');
        $dueTs = strtotime($dueDate);
        $statusLower = strtolower((string) ($assessment['status'] ?? ''));
        $isOverdue = in_array($statusLower, ['overdue', 'delinquent'], true) || $dueTs < strtotime(date('Y-m-d'));
        $monthsLate = $isOverdue ? max(1, min(36, (int) ceil((time() - $dueTs) / (30 * 86400)))) : 0;
        $surcharge = isset($assessment['surcharge_amount']) && (float) $assessment['surcharge_amount'] > 0
            ? (float) $assessment['surcharge_amount']
            : ($isOverdue ? round($basicDue * 0.25, 2) : 0.0);
        $interest = isset($assessment['penalty_amount']) && (float) $assessment['penalty_amount'] > 0
            ? (float) $assessment['penalty_amount']
            : ($isOverdue ? round(($basicDue + $surcharge) * (0.02 * $monthsLate), 2) : 0.0);
        $totalDue = $basicDue + $surcharge + $interest;

        $subject = ($isOverdue ? '[OVERDUE TAX NOTICE] ' : '[BILLING REMINDER] ') . $assessmentNo . ' — Caloocan City Treasurer\'s Office';
        $body = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
          <div style="background:linear-gradient(135deg,#176B87,#0f172a);padding:24px 30px;color:#fff;">
            <p style="margin:0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#86B6F6;">Republic of the Philippines &bull; City of Caloocan</p>
            <h2 style="margin:6px 0 0;font-size:18px;">Office of the City Treasurer — ' . ($isOverdue ? 'Delinquency &amp; Overdue Notice' : 'Payment Reminder Notice') . '</h2>
          </div>
          <div style="padding:26px 30px;background:#ffffff;color:#1e293b;font-size:13px;line-height:1.6;">
            <p>Dear <strong>' . htmlspecialchars($taxpayerName) . '</strong>,</p>
            <p>This is an official reminder from the <strong>Caloocan City Treasurer\'s Office (Civentral Revenue Portal)</strong> regarding your tax assessment below:</p>
            <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;border:1px solid #e2e8f0;">
              <tr style="background:#f8fafc;"><td style="padding:9px 12px;color:#64748b;font-weight:bold;">Assessment No.</td><td style="padding:9px 12px;font-family:monospace;font-weight:bold;">' . htmlspecialchars($assessmentNo) . '</td></tr>
              <tr><td style="padding:9px 12px;color:#64748b;font-weight:bold;">Tax Type</td><td style="padding:9px 12px;">' . htmlspecialchars($taxType) . '</td></tr>
              <tr style="background:#f8fafc;"><td style="padding:9px 12px;color:#64748b;font-weight:bold;">Due Date</td><td style="padding:9px 12px;">' . htmlspecialchars($dueDate) . '</td></tr>
              <tr><td style="padding:9px 12px;color:#64748b;font-weight:bold;">Basic Tax Due</td><td style="padding:9px 12px;">&#8369;' . number_format($basicDue, 2) . '</td></tr>
              <tr style="background:#f8fafc;"><td style="padding:9px 12px;color:#b45309;font-weight:bold;">Surcharge (25%)</td><td style="padding:9px 12px;color:#b45309;">&#8369;' . number_format($surcharge, 2) . '</td></tr>
              <tr><td style="padding:9px 12px;color:#be123c;font-weight:bold;">Delinquency Interest (2%/mo)</td><td style="padding:9px 12px;color:#be123c;">&#8369;' . number_format($interest, 2) . '</td></tr>
              <tr style="background:#eef5ff;"><td style="padding:11px 12px;color:#176B87;font-weight:bold;">TOTAL AMOUNT PAYABLE</td><td style="padding:11px 12px;color:#176B87;font-weight:bold;font-size:16px;">&#8369;' . number_format($totalDue, 2) . '</td></tr>
            </table>
            <p>You may settle your account at the City Treasurer\'s Office or online via the Civentral Citizen Payment Portal (GCash / Maya / Card).</p>
          </div>
          <div style="background:#f8fafc;padding:14px 30px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e2e8f0;">
            Dispatched by ' . htmlspecialchars($sentBy ?: 'Treasury Notification Service') . ' &bull; Caloocan City Revenue &amp; Treasury System
          </div>
        </div>';

        $sent = false;
        try {
            require_once __DIR__ . '/../../config/mailer.php';
            if (function_exists('sendSystemEmail')) {
                $sent = (bool) sendSystemEmail($recipientEmail, $taxpayerName, $subject, $body);
            }
        } catch (\Throwable $e) {
            error_log('[Taxpayer Reminder] Email error: ' . $e->getMessage());
        }

        return [
            'sent'          => $sent,
            'assessment_no' => $assessmentNo,
            'taxpayer_name' => $taxpayerName,
            'email'         => $recipientEmail,
            'total_due'     => $totalDue,
        ];
    }

    /**
     * Update collection
     */
    public function updateCollection(int $id, array $data, ?string $performedBy = null): bool {
        $collection = $this->treasuryRepo->getCollectionById($id);
        if (!$collection) {
            throw new \Exception('Collection not found.');
        }

        if (array_key_exists('payment_mode', $data)) {
            $data['payment_mode'] = $this->normalizePaymentMode($data['payment_mode']);
        }

        $oldFundCode = $collection['fund_code'] ?? $collection['fund_id'] ?? 'GF';
        $newFundCode = !empty($data['fund_id']) ? $this->resolveFundCode($data['fund_id'], $oldFundCode) : $oldFundCode;
        if (!empty($data['fund_id'])) {
            $data['fund_code'] = $newFundCode;
        }

        $oldAmount = (float) ($collection['amount'] ?? 0);
        $newAmount = isset($data['amount']) ? (float) $data['amount'] : $oldAmount;
        $amountDiff = $newAmount - $oldAmount;
        $actor = $performedBy ?: ($_SESSION['username'] ?? 'Treasury Staff');
        $noteSuffix = !empty($data['audit_note']) ? ' — ' . trim((string) $data['audit_note']) : '';

        $success = $this->treasuryRepo->updateCollection($id, $data);

        if ($success) {
            if ($newFundCode !== $oldFundCode) {
                $oldFund = $this->treasuryRepo->getFundById($oldFundCode);
                if ($oldFund) {
                    $oldBefore = (float) ($oldFund['balance'] ?? 0);
                    $oldAfter = $oldBefore - $oldAmount;
                    $this->treasuryRepo->updateFundBalance($oldFundCode, $oldAfter);
                    $this->treasuryRepo->recordFundMovement([
                        'fund_code' => $oldFundCode, 'direction' => 'OUT', 'transaction_type' => 'adjustment',
                        'reference_no' => $collection['or_number'] ?? ('COL-' . $id),
                        'counterparty' => $data['payer_name'] ?? $collection['payer_name'] ?? '',
                        'description' => 'Reassigned receipt from ' . $oldFundCode . ' to ' . $newFundCode . $noteSuffix,
                        'amount' => $oldAmount, 'balance_before' => $oldBefore, 'balance_after' => $oldAfter, 'performed_by' => $actor,
                    ]);
                }
                $newFund = $this->treasuryRepo->getFundById($newFundCode);
                if ($newFund) {
                    $newBefore = (float) ($newFund['balance'] ?? 0);
                    $newAfter = $newBefore + $newAmount;
                    $this->treasuryRepo->updateFundBalance($newFundCode, $newAfter);
                    $this->treasuryRepo->recordFundMovement([
                        'fund_code' => $newFundCode, 'direction' => 'IN', 'transaction_type' => 'adjustment',
                        'reference_no' => $collection['or_number'] ?? ('COL-' . $id),
                        'counterparty' => $data['payer_name'] ?? $collection['payer_name'] ?? '',
                        'description' => 'Reassigned receipt from ' . $oldFundCode . ' to ' . $newFundCode . $noteSuffix,
                        'amount' => $newAmount, 'balance_before' => $newBefore, 'balance_after' => $newAfter, 'performed_by' => $actor,
                    ]);
                }
            } elseif (abs($amountDiff) >= 0.01) {
                $fund = $this->treasuryRepo->getFundById($oldFundCode);
                if ($fund) {
                    $balanceBefore = (float) ($fund['balance'] ?? 0);
                    $newBalance = $balanceBefore + $amountDiff;
                    $this->treasuryRepo->updateFundBalance($oldFundCode, $newBalance);
                    $this->treasuryRepo->recordFundMovement([
                        'fund_code' => $oldFundCode, 'direction' => $amountDiff > 0 ? 'IN' : 'OUT', 'transaction_type' => 'adjustment',
                        'reference_no' => $collection['or_number'] ?? ('COL-' . $id),
                        'counterparty' => $data['payer_name'] ?? $collection['payer_name'] ?? '',
                        'description' => 'Receipt Amount Adjustment (' . ($data['revenue_source'] ?? $collection['revenue_source'] ?? '') . ')' . $noteSuffix,
                        'amount' => abs($amountDiff), 'balance_before' => $balanceBefore, 'balance_after' => $newBalance, 'performed_by' => $actor,
                    ]);
                }
            }
        }

        return $success;
    }

    /**
     * Delete collection
     */
    public function deleteCollection(int $id, ?string $performedBy = null): bool {
        $collection = $this->treasuryRepo->getCollectionById($id);
        if (!$collection) {
            throw new \Exception('Collection not found.');
        }

        $success = $this->treasuryRepo->deleteCollection($id);

        if ($success) {
            $fundCode = $collection['fund_code'] ?? $collection['fund_id'] ?? null;
            if ($fundCode) {
                $fund = $this->treasuryRepo->getFundById($fundCode);
                if ($fund) {
                    $balanceBefore = (float) ($fund['balance'] ?? 0);
                    $amount = (float) ($collection['amount'] ?? 0);
                    $newBalance = $balanceBefore - $amount;
                    $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
                    $this->treasuryRepo->recordFundMovement([
                        'fund_code' => $fund['code'] ?? $fundCode, 'direction' => 'OUT', 'transaction_type' => 'reversal',
                        'reference_no' => $collection['or_number'] ?? ('COL-' . $id),
                        'counterparty' => $collection['payer_name'] ?? '',
                        'description' => 'Receipt Deleted / Reversed: ' . ($collection['revenue_source'] ?? ''),
                        'amount' => $amount, 'balance_before' => $balanceBefore, 'balance_after' => $newBalance,
                        'performed_by' => $performedBy ?: ($_SESSION['username'] ?? 'Treasury Staff'),
                    ]);
                }
            }
        }

        return $success;
    }

    /**
     * Validate payment modes accepted for new or edited collections.
     */
    private function normalizePaymentMode($paymentMode): string {
        $mode = strtolower(trim((string) ($paymentMode ?: 'cash')));
        $allowedModes = ['cash', 'online', 'gcash', 'maya', 'bank_transfer'];
        if (!in_array($mode, $allowedModes, true)) {
            throw new \Exception('Unsupported payment mode.');
        }
        return $mode;
    }

    /**
     * Create a disbursement voucher
     */
    public function createVoucher(array $input): array {
        $fundCode = $this->resolveFundCode($input['fund_id'] ?? null);

        $purpose = trim((string) ($input['purpose'] ?? ''));
        $purposeDocument = $input['purpose_document'] ?? null;
        if ($purpose === '' && empty($purposeDocument)) {
            throw new \Exception('Enter a purpose or upload a purpose document.');
        }

        $dv = [
            'dv_number'   => $this->generateDVNumber(),
            'voucher_no'  => $this->generateVoucherNumber(),
            'payee'       => $input['payee'],
            'purpose'     => $purpose !== '' ? $purpose : 'See attached purpose document',
            'purpose_document' => $purposeDocument,
            'fund_id'     => $input['fund_id'] ?? 'GF',
            'fund_code'   => $fundCode,
            'amount'      => $input['amount'],
            'status'      => 'pending',
            'created_by'  => $input['created_by'] ?? null,
        ];

        $voucherId = $this->treasuryRepo->insertVoucher($dv);
        return $this->treasuryRepo->getVoucherById($voucherId);
    }

    /**
     * Store a supporting purpose document and return its relative public path.
     */
    public function saveVoucherPurposeDocument(array $file): ?string {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \Exception('The purpose document could not be uploaded.');
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new \Exception('The purpose document must be 10 MB or smaller.');
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $extension = strtolower((string) pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \Exception('Purpose document must be PDF, DOC, DOCX, JPG, JPEG, or PNG.');
        }

        $directory = __DIR__ . '/../../pages/treasury/uploads/vouchers';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \Exception('Unable to prepare the purpose document storage folder.');
        }

        $filename = 'purpose_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
            throw new \Exception('Unable to save the purpose document.');
        }

        return 'uploads/vouchers/' . $filename;
    }

    public function saveBudgetPurposeDocument(array $file): ?string {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \Exception('The supporting document could not be uploaded.');
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new \Exception('The supporting document must be 10 MB or smaller.');
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $extension = strtolower((string) pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \Exception('Supporting document must be PDF, DOC, DOCX, JPG, JPEG, or PNG.');
        }

        $directory = __DIR__ . '/../../pages/treasury/uploads/budget';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \Exception('Unable to prepare the supporting document storage folder.');
        }

        $filename = 'budget_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
            throw new \Exception('Unable to save the supporting document.');
        }

        return 'uploads/budget/' . $filename;
    }

    /**
     * Validate the designated code required before releasing funds.
     */
    public function verifyReleaseCode(string $releaseCode): bool {
        $configuredCode = getenv('TREASURY_RELEASE_CODE') ?: (getenv('DISBURSEMENT_PASSWORD') ?: '12345678');
        return hash_equals((string) $configuredCode, trim($releaseCode));
    }

    /**
     * Release a voucher and debit the fund
     */
    public function releaseVoucher(int $dvId, ?string $performedBy = null): array {
        $voucher = $this->treasuryRepo->getVoucherById($dvId);
        if (empty($voucher)) {
            throw new \Exception('Voucher not found.');
        }
        if (!in_array(strtolower($voucher['status']), ['pending', 'approved'])) {
            throw new \Exception('Only pending or approved vouchers can be released.');
        }

        $fundCode = $voucher['fund_code'] ?? $voucher['fund_id'] ?? null;
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

        // Debit fund & record Money OUT in ledger
        $balanceBefore = (float) ($fund['balance'] ?? 0);
        $amount = (float) ($voucher['amount'] ?? 0);
        $newBalance = $balanceBefore - $amount;
        $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
        $this->treasuryRepo->recordFundMovement([
            'fund_code'        => $fund['code'] ?? $fundCode,
            'direction'        => 'OUT',
            'transaction_type' => 'disbursement',
            'reference_no'     => $voucher['dv_number'] ?? $voucher['voucher_no'] ?? ('DV-' . $dvId),
            'counterparty'     => $voucher['payee'] ?? '',
            'description'      => $voucher['purpose'] ?? 'Disbursement Voucher',
            'amount'           => $amount,
            'balance_before'   => $balanceBefore,
            'balance_after'    => $newBalance,
            'performed_by'     => $performedBy ?: ($voucher['created_by'] ?? ($_SESSION['username'] ?? 'Treasury Staff')),
        ]);

        // Update voucher status
        $this->treasuryRepo->updateVoucherStatus($dvId, 'disbursed');

        return $this->treasuryRepo->getVoucherById($dvId);
    }

    /**
     * Update a voucher's status (approve, reject, etc.)
     */
    public function updateVoucherStatus(int $dvId, string $status, array $extra = []): bool {
        return $this->treasuryRepo->updateVoucherStatus($dvId, $status, $extra);
    }

    /**
     * Get business checklist
     */
    public function getBusinessChecklist(string $type): array {
        if ($type === 'new') {
            return [
                'brgy_clear'   => 'Barangay Clearance (current year)',
                'dti_sec'      => 'DTI/SEC/CDA Registration',
                'contract'     => 'Contract of Lease / Transfer of Certificate of Title (TCT)',
                'locational'   => 'Locational Clearance',
                'fire_cert'    => 'Fire Safety Inspection Certificate',
                'owner_id'     => "Owner's government-issued ID",
            ];
        }
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
            'business_address' => $input['barangay'] ?? null,
            'barangay'         => trim((string) ($input['barangay'] ?? '')),
            'line_of_business' => trim((string) ($input['line_of_business'] ?? '')),
            'permit_no'        => trim((string) ($input['permit_no'] ?? '')),
            'application_type' => 'new',
            'status'           => 'submitted',
            'documents'        => json_encode($documents),
        ];

        if (!empty($input['barangay'] ?? null)) {
            $row['business_address'] = trim((string) $input['barangay']);
        }

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

        if (!in_array(strtolower((string) ($payment['status'] ?? '')), ['pending', 'processing'], true)) {
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
            $orNumber = !empty($payment['receipt_no']) ? $payment['receipt_no'] : $this->generateORNumber();

            $fundCode = $payment['fund_code'] ?? $this->resolveFundCode($payment['fund_id'] ?? null);
            $payerName = $payment['citizen_name'] ?? $payment['taxpayer_name'] ?? 'Citizen';
            $revSource = $payment['payment_source'] ?? $payment['payment_type'] ?? 'Online Payment';

            // Record in revenue collections
            $paymentMode = ($payment['payment_gateway'] ?? $payment['payment_method'] ?? 'gcash') === 'maya' ? 'maya' : 'gcash';

            $this->treasuryRepo->insertCollection([
                'or_number'      => $orNumber,
                'payer_name'     => $payerName,
                'revenue_source' => $revSource,
                'fund_code'      => $fundCode,
                'amount'         => $payment['amount'],
                'payment_mode'   => $paymentMode,
                'collected_by'   => 'Online Payment System',
            ]);

            // Update fund balance & record Money IN in ledger
            $fund = $this->treasuryRepo->getFundById($fundCode);
            if ($fund) {
                $balanceBefore = (float) ($fund['balance'] ?? 0);
                $amount = (float) ($payment['amount'] ?? 0);
                $newBalance = $balanceBefore + $amount;
                $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
                $this->treasuryRepo->recordFundMovement([
                    'fund_code'        => $fund['code'] ?? $fundCode,
                    'direction'        => 'IN',
                    'transaction_type' => 'online_payment',
                    'reference_no'     => $orNumber,
                    'counterparty'     => $payerName,
                    'description'      => $revSource . ' via ' . strtoupper($paymentMode) . ' (Ref: ' . $paymentReference . ')',
                    'amount'           => $amount,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $newBalance,
                    'performed_by'     => 'Online Payment System',
                ]);
            }

            // Update payment status to completed
            $this->treasuryRepo->updateOnlinePaymentStatus($payment['id'], 'completed', [
                'or_number'  => $orNumber,
                'receipt_no' => $orNumber,
                'settled_at' => date('Y-m-d H:i:s'),
            ]);

            // Phase 4: Dispatch automated Electronic Official Receipt (e-OR) email confirmation to citizen
            $citizenEmail = trim((string) ($payment['citizen_email'] ?? $payment['email'] ?? ''));
            if ($citizenEmail !== '') {
                try {
                    require_once __DIR__ . '/../../config/mailer.php';
                    if (function_exists('sendSystemEmail')) {
                        $subject = '[OFFICIAL RECEIPT ' . $orNumber . '] Payment Confirmation — Caloocan City Treasury';
                        $body = '<div style="font-family:Arial,sans-serif;max-width:580px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">'
                            . '<div style="background:#176B87;padding:20px 26px;color:#fff;">'
                            . '<h2 style="margin:0;font-size:17px;">Caloocan City Treasurer\'s Office — Electronic Official Receipt</h2>'
                            . '</div>'
                            . '<div style="padding:24px 26px;font-size:13px;color:#1e293b;line-height:1.6;">'
                            . '<p>Dear <strong>' . htmlspecialchars($payerName) . '</strong>,</p>'
                            . '<p>Your online payment has been verified and posted to the Treasury Fund Ledger.</p>'
                            . '<p><strong>Official Receipt No.:</strong> ' . htmlspecialchars($orNumber) . '<br>'
                            . '<strong>Payment Reference:</strong> ' . htmlspecialchars($paymentReference) . '<br>'
                            . '<strong>Particulars:</strong> ' . htmlspecialchars($revSource) . '<br>'
                            . '<strong>Total Amount Paid:</strong> &#8369;' . number_format((float) $payment['amount'], 2) . '</p>'
                            . '</div></div>';
                        sendSystemEmail($citizenEmail, $payerName, $subject, $body);
                    }
                } catch (\Throwable $e) {
                    error_log('[Online Payment Receipt Email] ' . $e->getMessage());
                }
            }

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
            'supporting_document' => $input['supporting_document'] ?? null,
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
            'rejection_reason' => null,
            'callback_url'    => $input['callback_url'] ?? null,
            'callback_status' => !empty($input['callback_url']) ? 'waiting' : null,
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
     * Get one budget request for approvals and document generation.
     */
    public function getBudgetRequestById(int $requestId): ?array {
        return $this->treasuryRepo->getBudgetRequestById($requestId);
    }

    /**
     * Get budget requests by department
     */
    public function getBudgetRequestsByDepartment(string $departmentCode): array {
        return $this->treasuryRepo->getBudgetRequestsByDepartment($departmentCode);
    }

    /**
     * Import budget requests from normalized rows.
     */
    public function importBudgetRequests(array $rows, ?string $requestedBy = null): array {
        $result = ['imported' => 0, 'errors' => []];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            try {
                $department = trim((string) ($row['department_name'] ?? $row['department'] ?? ''));
                $project = trim((string) ($row['project_title'] ?? $row['project'] ?? ''));
                $amount = (float) ($row['requested_amount'] ?? $row['amount'] ?? 0);
                if ($department === '' || $project === '' || $amount <= 0) {
                    throw new \Exception('department, project, and a positive amount are required');
                }

                $request = $this->createBudgetRequest([
                    'department_name' => $department,
                    'department_code' => trim((string) ($row['department_code'] ?? '')),
                    'project_title' => $project,
                    'description' => trim((string) ($row['description'] ?? $project)),
                    'requested_amount' => $amount,
                    'fund_id' => $row['fund_id'] ?? $row['fund_code'] ?? 'GF',
                    'fiscal_year' => (int) ($row['fiscal_year'] ?? date('Y')),
                    'quarter' => $row['quarter'] ?? 'Q1',
                    'requested_by' => $requestedBy,
                    'justification' => trim((string) ($row['justification'] ?? 'Imported request')),
                ]);
                $result['imported']++;
            } catch (\Throwable $e) {
                $result['errors'][] = 'Row ' . $line . ': ' . $e->getMessage();
            }
        }
        return $result;
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

        $updated = $this->treasuryRepo->getBudgetRequestById($requestId);
        $this->notifyBudgetRequester($updated, 'budget.approved');
        return $updated;
    }

    /**
     * Release budget
     */
    public function releaseBudget(int $requestId, ?string $performedBy = null): array {
        $request = $this->treasuryRepo->getBudgetRequestById($requestId);
        if (!$request) {
            throw new \Exception('Budget request not found.');
        }
        if (strtolower($request['status']) !== 'approved') {
            throw new \Exception('Budget request must be approved before release.');
        }

        // Debit fund & record Money OUT in ledger
        $fundCode = $request['fund_code'] ?? $request['fund_id'] ?? 'GF';
        $fund = $this->treasuryRepo->getFundById($fundCode);
        if ($fund) {
            $balanceBefore = (float) ($fund['balance'] ?? 0);
            $amount = (float) ($request['requested_amount'] ?? 0);
            $newBalance = $balanceBefore - $amount;
            $this->treasuryRepo->updateFundBalance($fundCode, $newBalance);
            $this->treasuryRepo->recordFundMovement([
                'fund_code'        => $fund['code'] ?? $fundCode,
                'direction'        => 'OUT',
                'transaction_type' => 'budget_release',
                'reference_no'     => $request['request_no'] ?? $request['request_number'] ?? ('BR-' . $requestId),
                'counterparty'     => $request['department_name'] ?? 'Department',
                'description'      => 'Budget Release: ' . ($request['project_title'] ?? 'Departmental Budget'),
                'amount'           => $amount,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $newBalance,
                'performed_by'     => $performedBy ?: ($request['approved_by'] ?? ($_SESSION['username'] ?? 'Treasury Admin')),
            ]);
        }

        // Update request status
        $this->treasuryRepo->updateBudgetRequestStatus($requestId, 'released');

        $updated = $this->treasuryRepo->getBudgetRequestById($requestId);
        $this->notifyBudgetRequester($updated, 'budget.released');
        return $updated;
    }

    /**
     * Update budget request status
     */
    public function updateBudgetRequestStatus(int $requestId, string $status, array $data = []): array {
        $request = $this->treasuryRepo->getBudgetRequestById($requestId);
        if (!$request) {
            throw new \Exception('Budget request not found.');
        }

        $normalized = strtolower($status);
        $this->treasuryRepo->updateBudgetRequestStatus($requestId, $normalized, $data);
        $updated = $this->treasuryRepo->getBudgetRequestById($requestId);

        if (in_array($normalized, ['approved', 'rejected', 'released', 'under review'], true)) {
            $this->notifyBudgetRequester($updated, 'budget.' . str_replace(' ', '_', $normalized));
        }
        return $updated;
    }

    /**
     * Notify the requesting module (e.g. Logistics) about the decision on its budget request.
     *
     * If the request was submitted with a `callback_url`, Treasury POSTs a JSON payload to it.
     * The payload is signed with HMAC-SHA256 (header X-Civentral-Signature) using
     * BUDGET_WEBHOOK_SECRET (or BUDGET_API_KEY as fallback) so the receiver can verify it.
     *
     * Never throws: a failed delivery must not undo or block the approval itself.
     */
    public function notifyBudgetRequester(?array $request, string $event): array {
        if (!$request) {
            return ['delivered' => false, 'reason' => 'Request not found'];
        }

        $url = trim((string) ($request['callback_url'] ?? ''));
        if ($url === '') {
            return ['delivered' => false, 'reason' => 'No callback_url registered'];
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            $this->treasuryRepo->recordBudgetCallbackResult((int) $request['id'], 'failed', 'Invalid callback_url');
            return ['delivered' => false, 'reason' => 'Invalid callback_url'];
        }

        $payload = [
            'event'            => $event,
            'request_id'       => (int) $request['id'],
            'request_no'       => $request['request_no'] ?? $request['request_number'] ?? null,
            'status'           => strtolower((string) ($request['status'] ?? '')),
            'department_name'  => $request['department_name'] ?? null,
            'department_code'  => $request['department_code'] ?? null,
            'project_title'    => $request['project_title'] ?? null,
            'requested_amount' => (float) ($request['requested_amount'] ?? 0),
            'fund_code'        => $request['fund_code'] ?? null,
            'approved_by'      => $request['approved_by'] ?? null,
            'approved_at'      => $request['approved_at'] ?? null,
            'rejection_reason' => $request['rejection_reason'] ?? null,
            'timestamp'        => date('c'),
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: CIVENTRAL-Treasury-Webhook/1.0',
            'X-Civentral-Event: ' . $event,
        ];
        $secret = getenv('BUDGET_WEBHOOK_SECRET') ?: (getenv('BUDGET_API_KEY') ?: '');
        if ($secret !== '') {
            $headers[] = 'X-Civentral-Signature: sha256=' . hash_hmac('sha256', $body, $secret);
        }

        $responseBody = null;
        $httpCode = 0;
        $error = null;
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseBody === false) {
                $error = curl_error($ch);
                $responseBody = null;
            }
            curl_close($ch);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $delivered = $httpCode >= 200 && $httpCode < 300;
        $logText = $delivered
            ? 'HTTP ' . $httpCode . ' ' . (string) $responseBody
            : ($error ? 'Error: ' . $error : 'HTTP ' . $httpCode . ' ' . (string) $responseBody);

        try {
            $this->treasuryRepo->recordBudgetCallbackResult((int) $request['id'], $delivered ? 'delivered' : 'failed', $logText);
        } catch (\Throwable $e) {
            error_log('[Budget Callback] Could not record result: ' . $e->getMessage());
        }

        if (!$delivered) {
            error_log('[Budget Callback] ' . $event . ' to ' . $url . ' failed: ' . $logText);
        }

        return ['delivered' => $delivered, 'http_code' => $httpCode, 'reason' => $delivered ? null : $logText];
    }
}