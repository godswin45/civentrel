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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// CORS configuration
$allowedOrigins = [
    'http://localhost',
    'http://localhost:80',
    'http://localhost:3000',
    'http://127.0.0.1',
    'http://127.0.0.1:80',
    'http://127.0.0.1:3000'
];

if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    if (in_array($origin, $allowedOrigins) || preg_match('/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
    }
}

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/Services/CitizenPaymentService.php';

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
            : 'Payment accepted by the online payment gateway.',
        'transaction_id' => $result['data']['transaction_id'],
        'reference_no' => $result['data']['reference_no'],
        'receipt_no' => $result['data']['receipt_no'],
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
