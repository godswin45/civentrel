<?php
/**
 * GET /api/citizen/treasury/payments
 * GET /api/citizen/treasury/payments?transaction_id=TXN-2026-000001
 * 
 * Get payment history or details for authenticated citizen
 * 
 * Response (Payment History):
 * {
 *   "status": "success",
 *   "message": "Payment history retrieved",
 *   "data": [
 *     {
 *       "transaction_id": "TXN-2026-000001",
 *       "reference_no": "REF-20260905-000001",
 *       "receipt_no": "OR-2026-000001",
 *       "status": "Paid",
 *       "amount": 4200,
 *       "service_fee": 42,
 *       "total_amount": 4242,
 *       "payment_type": "Business Tax & Fees",
 *       "payment_method": "GCash",
 *       "created_at": "2026-09-05T10:30:00Z"
 *     }
 *   ]
 * }
 * 
 * Response (Single Payment):
 * {
 *   "status": "success",
 *   "message": "Payment details retrieved",
 *   "data": {
 *     "transaction_id": "TXN-2026-000001",
 *     "reference_no": "REF-20260905-000001",
 *     "receipt_no": "OR-2026-000001",
 *     "status": "Paid",
 *     "amount": 4200,
 *     "service_fee": 42,
 *     "total_amount": 4242,
 *     "payment_type": "Business Tax & Fees",
 *     "payment_method": "GCash",
 *     "taxpayer_name": "Maria Santos",
 *     "account_number": "T-20485",
 *     "email": "maria.santos@example.com",
 *     "created_at": "2026-09-05T10:30:00Z",
 *     "settled_at": "2026-09-05T10:35:00Z"
 *   }
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

header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond([
        'status' => 'error',
        'message' => 'Method not allowed. Use GET to retrieve payment information.'
    ], 405);
}

try {
    // Verify authentication
    if (empty($_SESSION['citizen_id'])) {
        respond([
            'status' => 'error',
            'message' => 'Unauthorized: Please log in as a citizen to access payment history.'
        ], 401);
    }
    
    $citizenId = (int) $_SESSION['citizen_id'];
    
    // Initialize payment service
    $paymentService = new \App\Services\CitizenPaymentService($db ?? null);
    
    // Check if querying single transaction
    $transactionId = $_GET['transaction_id'] ?? null;
    
    if ($transactionId) {
        // Get single payment details
        $payment = $paymentService->getPaymentDetail($transactionId, $citizenId);
        
        if (!$payment) {
            respond([
                'status' => 'error',
                'message' => 'Payment not found or unauthorized access.'
            ], 404);
        }
        
        respond([
            'status' => 'success',
            'message' => 'Payment details retrieved',
            'data' => $payment
        ], 200);
    } else {
        // Get payment history
        $limit = min((int) ($_GET['limit'] ?? 50), 100);
        $offset = (int) ($_GET['offset'] ?? 0);
        
        $payments = $paymentService->getPaymentHistory($citizenId, $limit, $offset);
        
        respond([
            'status' => 'success',
            'message' => 'Payment history retrieved',
            'count' => count($payments),
            'data' => $payments
        ], 200);
    }
    
} catch (\Throwable $e) {
    respond([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ], 500);
}
?>
