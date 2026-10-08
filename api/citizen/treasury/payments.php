<?php
/**
 * POST /api/citizen/treasury/payments
 * 
 * Process online payment from citizen/mobile app
 * 
 * Request:
 * {
 *   "taxpayer_name": "Maria Santos",
 *   "account_number": "T-20485",
 *   "email": "maria.santos@example.com",
 *   "payment_type": "Business Tax & Fees",
 *   "amount": 4200,
 *   "payment_method": "GCash",
 *   "notes": "Payment purpose",
 *   "source": "civentral-apps",
 *   "municipality_code": "CAL-2026",
 *   "idempotency_key": "unique-client-generated-key",
 *   "citizen_user_id": 123
 * }
 * 
 * Response (Success):
 * {
 *   "status": "success",
 *   "message": "Payment accepted by the online payment gateway.",
 *   "transaction_id": "TXN-2026-000001",
 *   "reference_no": "REF-20260905-000001",
 *   "receipt_no": "OR-2026-000001",
 *   "data": {
 *     "transaction_id": "TXN-2026-000001",
 *     "reference_no": "REF-20260905-000001",
 *     "receipt_no": "OR-2026-000001",
 *     "status": "Paid"
 *   }
 * }
 * 
 * Response (Error):
 * {
 *   "status": "error",
 *   "message": "Readable error message"
 * }
 */

// Bootstrap MUST come first — it calls session_set_cookie_params()
// before session_start(), which must happen before any headers are sent.
require_once __DIR__ . '/../../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// CORS — open to all modules on the CIVENTRAL platform
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Only allow POST for payment processing
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'status' => 'error',
        'message' => 'Method not allowed. Use POST for payment processing.'
    ], 405);
}

try {
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Initialize payment service
    $paymentService = new \App\Services\CitizenPaymentService($db ?? null);
    
    // Process payment
    $result = $paymentService->processPayment($input);
    
    if (!$result['success']) {
        respond([
            'status' => 'error',
            'message' => $result['error'] ?? 'Payment processing failed'
        ], 400);
    }
    
    // Check if this was a duplicate submission (idempotency)
    $isDuplicate = $result['isDuplicate'] ?? false;
    
    // Successful response
    respond([
        'status' => 'success',
        'message' => $isDuplicate 
            ? 'This payment was already processed. Here are the details.'
            : 'Payment checkout link generated successfully.',
        'transaction_id' => $result['data']['transaction_id'],
        'reference_no' => $result['data']['reference_no'],
        'checkout_url' => $result['data']['checkout_url'] ?? null,
        'data' => $result['data'],
        'isDuplicate' => $isDuplicate
    ], 200);
    
} catch (\Throwable $e) {
    respond([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ], 500);
}
?>
