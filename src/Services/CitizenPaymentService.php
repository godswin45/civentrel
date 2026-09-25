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
    
    // We no longer strictly limit payment types because the API is now a Universal Gateway
    // Modules will send their source_module and we will map them dynamically.
    
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
            
            // 4. Removed strict payment_type validation. Accept any payment type from registered modules.
            
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
                'source_module' => $input['source_module'] ?? 'Unknown Module',
                'application_id' => $input['application_id'] ?? null,
                'fund_id' => $this->resolveFundId($input['payment_type'], $input['source_module'] ?? ''),
                'fund_code' => $this->resolveFundCode($input['payment_type'], $input['source_module'] ?? ''),
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
                
                // 10. Call payment gateway
                $gatewayResult = $this->callPaymentGateway($paymentData, (int) $paymentId);
                
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
                
                // 11. Update payment with gateway reference, but keep status as pending!
                $this->db->update('tr_online_payments',
                    [
                        'gateway_reference' => $gatewayResult['gateway_reference'] ?? null,
                        'gateway_response' => json_encode($gatewayResult)
                    ],
                    ['id' => $paymentId]
                );
                
                // 12. DO NOT record in tr_collections yet! That happens in the Webhook.
                
                // 13. Commit transaction
                if ($this->db && method_exists($this->db, 'getPdo')) {
                    $this->db->getPdo()->commit();
                }
                
                return [
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transactionId,
                        'reference_no' => $referenceNo,
                        'receipt_no' => null, // No receipt yet until paid
                        'status' => 'Pending',
                        'amount' => $amount,
                        'service_fee' => $serviceFee,
                        'total_amount' => $totalAmount,
                        'checkout_url' => $gatewayResult['checkout_url'] ?? null
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
     * Call payment gateway (PayMongo Checkout Session)
     */
    private function callPaymentGateway(array $paymentData, int $paymentId): array {
        $secretKey = getenv('PAYMONGO_SECRET_KEY');
        if (empty($secretKey)) {
            return ['success' => false, 'message' => 'Payment gateway keys are not configured.'];
        }

        // PayMongo accepts amounts in centavos (e.g. 100.00 PHP = 10000)
        $amountInCentavos = (int) round($paymentData['total_amount'] * 100);

        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => [
                        'name' => $paymentData['taxpayer_name'],
                        'email' => $paymentData['email']
                    ],
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => 'Civentral Govt Fee: ' . $paymentData['payment_type'],
                    'line_items' => [
                        [
                            'currency' => 'PHP',
                            'amount' => (int) round($paymentData['amount'] * 100),
                            'description' => $paymentData['payment_type'],
                            'name' => 'Government Fee',
                            'quantity' => 1
                        ]
                    ],
                    'payment_method_types' => ['gcash', 'paymaya', 'card'],
                    'reference_number' => $paymentData['payment_reference'],
                    'success_url' => getenv('APP_URL') ? rtrim(getenv('APP_URL'), '/') . '/payment-success' : 'http://localhost/civentrel/payment-success',
                    'cancel_url' => getenv('APP_URL') ? rtrim(getenv('APP_URL'), '/') . '/payment-cancel' : 'http://localhost/civentrel/payment-cancel'
                ]
            ]
        ];

        // If there's a service fee, add it as a line item
        if ($paymentData['service_fee'] > 0) {
            $payload['data']['attributes']['line_items'][] = [
                'currency' => 'PHP',
                'amount' => (int) round($paymentData['service_fee'] * 100),
                'description' => 'System Service Fee',
                'name' => 'Service Fee',
                'quantity' => 1
            ];
        }

        $ch = curl_init('https://api.paymongo.com/v2/checkout_sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':')
        ]);
        // Temporarily ignore SSL for local XAMPP issues if needed
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        
        $debugInfo = "Response: " . print_r($response, true) . "\nURL: " . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . "\nError: " . curl_error($ch);
        file_put_contents(__DIR__ . '/../../curl_dump.txt', $debugInfo);
        
        if ($response === false) {
            throw new \Exception("cURL error: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);

        $this->logGatewayCall(
            $paymentId,
            'PayMongo',
            $payload,
            $responseData,
            $httpCode,
            $httpCode >= 400 ? ($responseData['errors'][0]['detail'] ?? 'Unknown API Error') : null
        );

        if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['data']['attributes']['checkout_url'])) {
            // Keep status as pending, but return the checkout URL
            return [
                'success' => true,
                'checkout_url' => $responseData['data']['attributes']['checkout_url'],
                'gateway_reference' => $responseData['data']['id'],
                'message' => 'Checkout session created successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Gateway error: ' . ($responseData['errors'][0]['detail'] ?? 'Failed to generate checkout link')
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
     * Resolve fund ID from payment type and source module
     */
    private function resolveFundId(string $paymentType, string $sourceModule = ''): string {
        $type = strtolower($paymentType . ' ' . $sourceModule);
        
        if (strpos($type, 'property') !== false || strpos($type, 'zoning') !== false) {
            return 'property_tax';
        }
        if (strpos($type, 'business') !== false || strpos($type, 'franchise') !== false || strpos($type, 'building') !== false) {
            return 'business';
        }
        if (strpos($type, 'market') !== false) {
            return 'market';
        }
        if (strpos($type, 'scholarship') !== false || strpos($type, 'education') !== false) {
            return 'sef';
        }
        if (strpos($type, 'cemetery') !== false || strpos($type, 'parks') !== false || strpos($type, 'facility') !== false || strpos($type, 'water') !== false) {
            return 'eef';
        }
        
        return 'general';
    }
    
    /**
     * Resolve fund code from payment type and source module
     */
    private function resolveFundCode(string $paymentType, string $sourceModule = ''): string {
        $type = strtolower($paymentType . ' ' . $sourceModule);
        
        if (strpos($type, 'property') !== false || strpos($type, 'zoning') !== false) {
            return 'PTF';
        }
        if (strpos($type, 'business') !== false || strpos($type, 'franchise') !== false || strpos($type, 'building') !== false) {
            return 'BSF';
        }
        if (strpos($type, 'market') !== false) {
            return 'MSF';
        }
        if (strpos($type, 'scholarship') !== false || strpos($type, 'education') !== false) {
            return 'SEF';
        }
        if (strpos($type, 'cemetery') !== false || strpos($type, 'parks') !== false || strpos($type, 'facility') !== false || strpos($type, 'water') !== false) {
            return 'EEF';
        }
        
        return 'GF';
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
