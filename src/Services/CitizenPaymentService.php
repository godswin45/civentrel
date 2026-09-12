<?php

namespace App\Services;

/**
 * CitizenPaymentService
 * Handles online treasury payments from citizen/mobile apps
 * Includes idempotency protection, amount validation, and gateway integration
 */
class CitizenPaymentService {
    
    private $db;
    private $treasuryRepo;
    
    // Service fee calculation: 1% of amount (will be made configurable)
    private const SERVICE_FEE_PERCENTAGE = 0.01;
    
    // Supported payment types
    private const SUPPORTED_PAYMENT_TYPES = [
        'Real Property Tax',
        'Business Tax & Fees',
        'Market Stall Rental',
        'Community Tax Certificate',
        'General government payment / miscellaneous fees',
        'Business permit renewal or retirement payment'
    ];
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    /**
     * Process online payment from citizen/mobile app
     * 
     * @param array $input Payment data from request
     * @return array [success => bool, data => array, error => string]
     */
    public function processPayment(array $input): array {
        try {
            // 1. Validate authentication
            if (empty($_SESSION['citizen_id']) && empty($input['citizen_user_id'])) {
                return [
                    'success' => false,
                    'error' => 'Unauthorized: Citizen authentication required'
                ];
            }
            
            $citizenId = $_SESSION['citizen_id'] ?? $input['citizen_user_id'];
            
            // 2. Validate required fields
            $validation = $this->validateInput($input);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['message']
                ];
            }
            
            // 3. Check for duplicate submission using idempotency key
            if (!empty($input['idempotency_key'])) {
                $existingPayment = $this->checkIdempotency($input['idempotency_key']);
                if ($existingPayment) {
                    // Return the existing payment result
                    return [
                        'success' => true,
                        'data' => $this->formatPaymentResponse($existingPayment),
                        'isDuplicate' => true
                    ];
                }
            }
            
            // 4. Validate payment type
            if (!in_array($input['payment_type'], self::SUPPORTED_PAYMENT_TYPES, true)) {
                return [
                    'success' => false,
                    'error' => sprintf('Invalid payment_type: %s', $input['payment_type'])
                ];
            }
            
            // 5. Recalculate amounts on server (don't trust client)
            $amount = (float) $input['amount'];
            $serviceFee = round($amount * self::SERVICE_FEE_PERCENTAGE, 2);
            $totalAmount = $amount + $serviceFee;
            
            // 6. Generate transaction identifiers
            $transactionId = $this->generateTransactionId();
            $referenceNo = $this->generateReferenceNo();
            $receiptNo = $this->generateReceiptNo();
            
            // 7. Prepare payment record
            $paymentData = [
                'payment_reference' => $this->generatePaymentReference(),
                'transaction_id' => $transactionId,
                'reference_no' => $referenceNo,
                'receipt_no' => $receiptNo,
                'citizen_id' => $citizenId,
                'citizen_name' => trim($input['taxpayer_name'] ?? ''),
                'taxpayer_name' => trim($input['taxpayer_name'] ?? ''),
                'account_number' => trim($input['account_number'] ?? ''),
                'email' => strtolower(trim($input['email'] ?? '')),
                'payment_type' => $input['payment_type'],
                'payment_source' => $input['payment_type'],
                'fund_id' => $this->resolveFundId($input['payment_type']),
                'fund_code' => $this->resolveFundCode($input['payment_type']),
                'amount' => $amount,
                'service_fee' => $serviceFee,
                'total_amount' => $totalAmount,
                'payment_gateway' => strtolower($input['payment_method'] ?? 'gcash'),
                'payment_method' => strtolower($input['payment_method'] ?? 'gcash'),
                'notes' => trim($input['notes'] ?? ''),
                'source' => $input['source'] ?? 'civentral-apps',
                'municipality_code' => $input['municipality_code'] ?? 'CAL-2026',
                'idempotency_key' => $input['idempotency_key'] ?? null,
                'status' => 'processing',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // 8. Start database transaction
            if ($this->db && method_exists($this->db, 'getPdo')) {
                $this->db->getPdo()->beginTransaction();
            }
            
            try {
                // 9. Insert payment record
                $paymentId = $this->db->insert('tr_online_payments', $paymentData);
                
                if (!$paymentId) {
                    throw new \Exception('Failed to create payment record');
                }
                
                // 10. Call payment gateway (simulated for now, should integrate with actual gateway)
                $gatewayResult = $this->callPaymentGateway($paymentData);
                
                if (!$gatewayResult['success']) {
                    // Gateway call failed, mark as failed
                    $this->db->update('tr_online_payments', 
                        ['status' => 'failed', 'gateway_response' => json_encode($gatewayResult)],
                        ['id' => $paymentId]
                    );
                    
                    if ($this->db && method_exists($this->db, 'getPdo')) {
                        $this->db->getPdo()->rollBack();
                    }
                    
                    return [
                        'success' => false,
                        'error' => $gatewayResult['message'] ?? 'Payment gateway error'
                    ];
                }
                
                // 11. Update payment as completed
                $this->db->update('tr_online_payments',
                    [
                        'status' => 'completed',
                        'gateway_reference' => $gatewayResult['gateway_reference'] ?? null,
                        'gateway_response' => json_encode($gatewayResult),
                        'settled_at' => date('Y-m-d H:i:s')
                    ],
                    ['id' => $paymentId]
                );
                
                // 12. Record transaction in treasury collection
                $collectionRecord = [
                    'or_number' => $receiptNo,
                    'payer_name' => trim($input['taxpayer_name'] ?? ''),
                    'revenue_source' => $input['payment_type'],
                    'fund_id' => $paymentData['fund_id'],
                    'fund_code' => $paymentData['fund_code'],
                    'amount' => $amount,
                    'payment_mode' => strtolower($input['payment_method'] ?? 'gcash'),
                    'collected_by' => 'Online Gateway (' . ($input['source'] ?? 'civentral-apps') . ')',
                    'status' => 'valid',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->insert('tr_collections', $collectionRecord);
                
                // 13. Commit transaction
                if ($this->db && method_exists($this->db, 'getPdo')) {
                    $this->db->getPdo()->commit();
                }
                
                return [
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transactionId,
                        'reference_no' => $referenceNo,
                        'receipt_no' => $receiptNo,
                        'status' => 'Paid',
                        'amount' => $amount,
                        'service_fee' => $serviceFee,
                        'total_amount' => $totalAmount
                    ]
                ];
                
            } catch (\Throwable $e) {
                // Rollback on any error
                if ($this->db && method_exists($this->db, 'getPdo')) {
                    $this->db->getPdo()->rollBack();
                }
                
                return [
                    'success' => false,
                    'error' => 'Payment processing failed: ' . $e->getMessage()
                ];
            }
            
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate payment input
     */
    private function validateInput(array $input): array {
        $required = ['taxpayer_name', 'account_number', 'email', 'payment_type', 'amount', 'payment_method'];
        
        foreach ($required as $field) {
            if (empty($input[$field])) {
                return [
                    'valid' => false,
                    'message' => sprintf('Missing required field: %s', $field)
                ];
            }
        }
        
        // Validate amount is positive
        $amount = (float) ($input['amount'] ?? 0);
        if ($amount <= 0) {
            return [
                'valid' => false,
                'message' => 'Amount must be greater than zero'
            ];
        }
        
        // Validate email format
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Invalid email format'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check for duplicate submission using idempotency key
     */
    private function checkIdempotency(string $key): ?array {
        if (!$this->db) {
            return null;
        }
        
        $results = $this->db->select('tr_online_payments', 
            ['idempotency_key' => $key],
            '*',
            'created_at DESC LIMIT 1'
        );
        
        return $results[0] ?? null;
    }
    
    /**
     * Call payment gateway (integrated with actual gateway service)
     */
    private function callPaymentGateway(array $paymentData): array {
        // This would integrate with actual payment gateway like GCash, Maya, etc.
        // For now, simulate successful gateway response
        
        try {
            // Log the gateway call attempt
            $this->logGatewayCall(
                null, // payment_id will be updated later
                $paymentData['payment_gateway'],
                $paymentData,
                null,
                null,
                null
            );
            
            // In production, call actual gateway API
            // For MVP, simulate success
            return [
                'success' => true,
                'gateway_reference' => 'GW-' . time() . '-' . substr(bin2hex(random_bytes(4)), 0, 8),
                'message' => 'Payment processed successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gateway error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Log gateway call for audit trail
     */
    private function logGatewayCall(
        ?int $paymentId,
        string $gatewayName,
        array $request,
        ?array $response,
        ?int $responseCode,
        ?string $errorMessage
    ): void {
        if (!$this->db) {
            return;
        }
        
        $this->db->insert('tr_payment_gateway_log', [
            'payment_id' => $paymentId,
            'gateway_name' => $gatewayName,
            'request_payload' => json_encode($request),
            'response_payload' => json_encode($response ?? []),
            'response_code' => $responseCode,
            'status' => $response['status'] ?? null,
            'error_message' => $errorMessage,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Resolve fund ID from payment type
     */
    private function resolveFundId(string $paymentType): string {
        $fundMap = [
            'Real Property Tax' => 'property_tax',
            'Business Tax & Fees' => 'business',
            'Market Stall Rental' => 'market',
            'Community Tax Certificate' => 'general',
            'Business permit renewal or retirement payment' => 'business'
        ];
        
        return $fundMap[$paymentType] ?? 'general';
    }
    
    /**
     * Resolve fund code from payment type
     */
    private function resolveFundCode(string $paymentType): string {
        $fundMap = [
            'Real Property Tax' => 'PTF',
            'Business Tax & Fees' => 'BSF',
            'Market Stall Rental' => 'MSF',
            'Community Tax Certificate' => 'GF',
            'Business permit renewal or retirement payment' => 'BSF'
        ];
        
        return $fundMap[$paymentType] ?? 'GF';
    }
    
    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string {
        return 'TXN-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 6));
    }
    
    /**
     * Generate unique reference number
     */
    private function generateReferenceNo(): string {
        return 'REF-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
    
    /**
     * Generate receipt (OR) number
     */
    private function generateReceiptNo(): string {
        return 'OR-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
    
    /**
     * Generate payment reference
     */
    private function generatePaymentReference(): string {
        return 'PAY-' . time() . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    
    /**
     * Format payment response
     */
    private function formatPaymentResponse(array $payment): array {
        return [
            'transaction_id' => $payment['transaction_id'] ?? null,
            'reference_no' => $payment['reference_no'] ?? null,
            'receipt_no' => $payment['receipt_no'] ?? null,
            'status' => $payment['status'] === 'completed' ? 'Paid' : ucfirst($payment['status']),
            'amount' => (float) $payment['amount'],
            'service_fee' => (float) $payment['service_fee'],
            'total_amount' => (float) $payment['total_amount']
        ];
    }
    
    /**
     * Get payment history for citizen
     */
    public function getPaymentHistory(int $citizenId, int $limit = 50, int $offset = 0): array {
        if (!$this->db) {
            return [];
        }
        
        $results = $this->db->select('tr_online_payments',
            ['citizen_id' => $citizenId],
            '*',
            'created_at DESC LIMIT ' . intval($limit) . ' OFFSET ' . intval($offset)
        );
        
        return array_map(fn($p) => $this->formatPaymentResponse($p), $results);
    }
    
    /**
     * Get payment detail by transaction ID
     */
    public function getPaymentDetail(string $transactionId, int $citizenId): ?array {
        if (!$this->db) {
            return null;
        }
        
        $results = $this->db->select('tr_online_payments',
            ['transaction_id' => $transactionId, 'citizen_id' => $citizenId],
            '*'
        );
        
        if (empty($results)) {
            return null;
        }
        
        $payment = $results[0];
        return [
            'transaction_id' => $payment['transaction_id'],
            'reference_no' => $payment['reference_no'],
            'receipt_no' => $payment['receipt_no'],
            'status' => $payment['status'] === 'completed' ? 'Paid' : ucfirst($payment['status']),
            'amount' => (float) $payment['amount'],
            'service_fee' => (float) $payment['service_fee'],
            'total_amount' => (float) $payment['total_amount'],
            'payment_type' => $payment['payment_type'],
            'payment_method' => $payment['payment_method'],
            'taxpayer_name' => $payment['taxpayer_name'],
            'account_number' => $payment['account_number'],
            'email' => $payment['email'],
            'created_at' => $payment['created_at'],
            'settled_at' => $payment['settled_at']
        ];
    }
}
