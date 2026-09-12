<?php
/**
 * Test Cases for CitizenPaymentService
 * 
 * Run tests with: php tests/CitizenPaymentServiceTest.php
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Services/CitizenPaymentService.php';

class CitizenPaymentServiceTest {
    
    private $paymentService;
    private $testResults = [];
    
    public function __construct() {
        global $db;
        $this->paymentService = new \App\Services\CitizenPaymentService($db);
    }
    
    /**
     * Test 1: Successful Payment Processing
     */
    public function testSuccessfulPayment() {
        echo "\n=== TEST 1: Successful Payment Processing ===\n";
        
        $_SESSION['citizen_id'] = 123;
        
        $input = [
            'taxpayer_name' => 'Maria Santos',
            'account_number' => 'T-20485',
            'email' => 'maria.santos@example.com',
            'payment_type' => 'Business Tax & Fees',
            'amount' => 4200,
            'service_fee' => 42,  // Will be recalculated
            'total_amount' => 4242,  // Will be recalculated
            'payment_method' => 'GCash',
            'notes' => 'Business tax payment',
            'source' => 'civentral-apps',
            'municipality_code' => 'CAL-2026',
            'idempotency_key' => 'test-' . time() . '-1',
            'citizen_user_id' => 123
        ];
        
        $result = $this->paymentService->processPayment($input);
        
        $passed = $result['success'] === true;
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        
        $this->testResults['successful_payment'] = $passed;
    }
    
    /**
     * Test 2: Validation Failure - Missing Required Fields
     */
    public function testValidationFailureMissingFields() {
        echo "\n=== TEST 2: Validation Failure - Missing Fields ===\n";
        
        $_SESSION['citizen_id'] = 123;
        
        $input = [
            'taxpayer_name' => 'Maria Santos',
            // Missing account_number
            'email' => 'maria.santos@example.com',
            'payment_type' => 'Business Tax & Fees',
            'amount' => 4200,
            // Missing payment_method
        ];
        
        $result = $this->paymentService->processPayment($input);
        
        $passed = $result['success'] === false && strpos($result['error'], 'Missing required field') !== false;
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo "Expected Error: " . $result['error'] . "\n";
        
        $this->testResults['validation_missing_fields'] = $passed;
    }
    
    /**
     * Test 3: Validation Failure - Invalid Amount
     */
    public function testValidationFailureInvalidAmount() {
        echo "\n=== TEST 3: Validation Failure - Invalid Amount ===\n";
        
        $_SESSION['citizen_id'] = 123;
        
        $input = [
            'taxpayer_name' => 'Maria Santos',
            'account_number' => 'T-20485',
            'email' => 'maria.santos@example.com',
            'payment_type' => 'Business Tax & Fees',
            'amount' => -100,  // Negative amount
            'payment_method' => 'GCash',
            'idempotency_key' => 'test-' . time() . '-2'
        ];
        
        $result = $this->paymentService->processPayment($input);
        
        $passed = $result['success'] === false && strpos($result['error'], 'Amount must be greater than zero') !== false;
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo "Expected Error: " . $result['error'] . "\n";
        
        $this->testResults['validation_invalid_amount'] = $passed;
    }
    
    /**
     * Test 4: Validation Failure - Invalid Email
     */
    public function testValidationFailureInvalidEmail() {
        echo "\n=== TEST 4: Validation Failure - Invalid Email ===\n";
        
        $_SESSION['citizen_id'] = 123;
        
        $input = [
            'taxpayer_name' => 'Maria Santos',
            'account_number' => 'T-20485',
            'email' => 'invalid-email',  // Invalid email
            'payment_type' => 'Business Tax & Fees',
            'amount' => 4200,
            'payment_method' => 'GCash',
            'idempotency_key' => 'test-' . time() . '-3'
        ];
        
        $result = $this->paymentService->processPayment($input);
        
        $passed = $result['success'] === false && strpos($result['error'], 'Invalid email format') !== false;
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo "Expected Error: " . $result['error'] . "\n";
        
        $this->testResults['validation_invalid_email'] = $passed;
    }
    
    /**
     * Test 5: Validation Failure - Invalid Payment Type
     */
    public function testValidationFailureInvalidPaymentType() {
        echo "\n=== TEST 5: Validation Failure - Invalid Payment Type ===\n";
        
        $_SESSION['citizen_id'] = 123;
        
        $input = [
            'taxpayer_name' => 'Maria Santos',
            'account_number' => 'T-20485',
            'email' => 'maria.santos@example.com',
            'payment_type' => 'Invalid Payment Type',  // Invalid
            'amount' => 4200,
            'payment_method' => 'GCash',
            'idempotency_key' => 'test-' . time() . '-4'
        ];
        
        $result = $this->paymentService->processPayment($input);
        
        $passed = $result['success'] === false && strpos($result['error'], 'Invalid payment_type') !== false;
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo "Expected Error: " . $result['error'] . "\n";
        
        $this->testResults['validation_invalid_payment_type'] = $passed;
    }
    
    /**
     * Test 6: Duplicate Submission (Idempotency)
     */
    public function testDuplicateSubmissionIdempotency() {
        echo "\n=== TEST 6: Duplicate Submission (Idempotency) ===\n";
        
        $_SESSION['citizen_id'] = 123;
        $idempotencyKey = 'test-idempotency-' . time();
        
        $input = [
            'taxpayer_name' => 'Maria Santos',
            'account_number' => 'T-20485',
            'email' => 'maria.santos@example.com',
            'payment_type' => 'Business Tax & Fees',
            'amount' => 4200,
            'payment_method' => 'GCash',
            'notes' => 'First payment',
            'source' => 'civentral-apps',
            'municipality_code' => 'CAL-2026',
            'idempotency_key' => $idempotencyKey,
            'citizen_user_id' => 123
        ];
        
        // First submission
        echo "First Submission:\n";
        $result1 = $this->paymentService->processPayment($input);
        echo "  Result: " . ($result1['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        $txnId1 = $result1['data']['transaction_id'] ?? null;
        
        // Second submission with same idempotency key
        echo "Second Submission (Same Idempotency Key):\n";
        $result2 = $this->paymentService->processPayment($input);
        echo "  Result: " . ($result2['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        $txnId2 = $result2['data']['transaction_id'] ?? null;
        echo "  Is Duplicate: " . ($result2['isDuplicate'] ?? false ? "✓ YES" : "✗ NO") . "\n";
        
        // Should return same transaction ID for both
        $passed = $result1['success'] === true 
            && $result2['success'] === true 
            && $txnId1 === $txnId2
            && ($result2['isDuplicate'] ?? false) === true;
        
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        
        $this->testResults['idempotency_duplicate_submission'] = $passed;
    }
    
    /**
     * Test 7: Unauthorized Access (No Session)
     */
    public function testUnauthorizedAccess() {
        echo "\n=== TEST 7: Unauthorized Access (No Session) ===\n";
        
        // Unset session
        unset($_SESSION['citizen_id']);
        
        $input = [
            'taxpayer_name' => 'Maria Santos',
            'account_number' => 'T-20485',
            'email' => 'maria.santos@example.com',
            'payment_type' => 'Business Tax & Fees',
            'amount' => 4200,
            'payment_method' => 'GCash',
            'idempotency_key' => 'test-' . time() . '-7'
        ];
        
        $result = $this->paymentService->processPayment($input);
        
        $passed = $result['success'] === false && strpos($result['error'], 'Unauthorized') !== false;
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo "Expected Error: " . $result['error'] . "\n";
        
        $this->testResults['unauthorized_access'] = $passed;
    }
    
    /**
     * Test 8: Server-Side Amount Recalculation
     */
    public function testServerSideAmountRecalculation() {
        echo "\n=== TEST 8: Server-Side Amount Recalculation ===\n";
        
        $_SESSION['citizen_id'] = 123;
        
        // Client sends incorrect service_fee and total_amount
        $input = [
            'taxpayer_name' => 'Maria Santos',
            'account_number' => 'T-20485',
            'email' => 'maria.santos@example.com',
            'payment_type' => 'Business Tax & Fees',
            'amount' => 4200,
            'service_fee' => 999,  // Incorrect - should be 42
            'total_amount' => 9999,  // Incorrect - should be 4242
            'payment_method' => 'GCash',
            'source' => 'civentral-apps',
            'municipality_code' => 'CAL-2026',
            'idempotency_key' => 'test-' . time() . '-8',
            'citizen_user_id' => 123
        ];
        
        $result = $this->paymentService->processPayment($input);
        
        $expectedServiceFee = 42;  // 1% of 4200
        $expectedTotal = 4242;  // 4200 + 42
        
        $actualServiceFee = $result['data']['service_fee'] ?? null;
        $actualTotal = $result['data']['total_amount'] ?? null;
        
        $passed = $result['success'] === true 
            && $actualServiceFee == $expectedServiceFee
            && $actualTotal == $expectedTotal;
        
        echo "Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo "Expected Service Fee: ₱" . number_format($expectedServiceFee, 2) . "\n";
        echo "Actual Service Fee: ₱" . number_format($actualServiceFee, 2) . "\n";
        echo "Expected Total: ₱" . number_format($expectedTotal, 2) . "\n";
        echo "Actual Total: ₱" . number_format($actualTotal, 2) . "\n";
        
        $this->testResults['server_side_amount_recalculation'] = $passed;
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "\n╔════════════════════════════════════════════════════════════════╗\n";
        echo "║   CITIZEN PAYMENT SERVICE TEST SUITE                           ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        
        $this->testSuccessfulPayment();
        $this->testValidationFailureMissingFields();
        $this->testValidationFailureInvalidAmount();
        $this->testValidationFailureInvalidEmail();
        $this->testValidationFailureInvalidPaymentType();
        $this->testDuplicateSubmissionIdempotency();
        $this->testUnauthorizedAccess();
        $this->testServerSideAmountRecalculation();
        
        // Print summary
        echo "\n╔════════════════════════════════════════════════════════════════╗\n";
        echo "║   TEST SUMMARY                                                 ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        
        $total = count($this->testResults);
        $passed = array_sum($this->testResults);
        $failed = $total - $passed;
        
        echo "\nTotal Tests: " . $total . "\n";
        echo "Passed: " . $passed . "\n";
        echo "Failed: " . $failed . "\n";
        echo "Success Rate: " . number_format(($passed / $total) * 100, 1) . "%\n\n";
        
        foreach ($this->testResults as $test => $result) {
            echo ($result ? "✓" : "✗") . " " . str_replace('_', ' ', ucfirst($test)) . "\n";
        }
        
        echo "\n";
        
        return $failed === 0;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $tester = new CitizenPaymentServiceTest();
    $success = $tester->runAllTests();
    exit($success ? 0 : 1);
}
?>
