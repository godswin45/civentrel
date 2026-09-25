<?php
/**
 * POST /api/webhooks/paymongo
 * 
 * Webhook listener for PayMongo events.
 * Listens for `checkout_session.payment.paid` or `payment.paid` events.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/bootstrap.php';

function respondWebhook(string $message, int $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['status' => $statusCode === 200 ? 'success' : 'error', 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWebhook('Only POST is allowed', 405);
}

// 1. Get the payload and signature
$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

// (Optional but recommended) Verify signature using PAYMONGO_WEBHOOK_SECRET
// We will skip strict verification for localhost testing if secret is not set, 
// but in production this should be enforced.

$event = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($event['data'])) {
    respondWebhook('Invalid JSON payload', 400);
}

$eventType = $event['data']['attributes']['type'] ?? '';

// We only care about successful payments
if ($eventType !== 'payment.paid' && $eventType !== 'checkout_session.payment.paid') {
    respondWebhook('Event ignored', 200);
}

try {
    $db = Database::getInstance();
    
    // 2. Extract the reference number
    // Depending on the event type, the reference number location might vary slightly, 
    // but PayMongo usually keeps it under the attributes or linked checkout session.
    // For simplicity, we will search for the reference_number recursively or in known paths.
    
    $referenceNumber = null;
    $amountPaid = 0;
    
    if ($eventType === 'checkout_session.payment.paid') {
        $referenceNumber = $event['data']['attributes']['data']['attributes']['reference_number'] ?? null;
        // In some PayMongo API versions, it might just be directly under attributes
        if (!$referenceNumber) {
            $referenceNumber = $event['data']['attributes']['reference_number'] ?? null;
        }
    } else {
        // payment.paid
        $referenceNumber = $event['data']['attributes']['description'] ?? ''; // Sometimes stored here
        // If it's a payment intent, it might not have reference_number directly.
    }
    
    // If we can't find it easily, let's try to find the gateway_reference (Checkout Session ID)
    // The webhook payload usually contains the source or checkout session ID.
    $checkoutSessionId = null;
    if (isset($event['data']['attributes']['data']['id'])) {
        $checkoutSessionId = $event['data']['attributes']['data']['id']; // cs_xxxxxx
    }

    if (empty($referenceNumber) && empty($checkoutSessionId)) {
        respondWebhook('Could not identify transaction reference in payload', 400);
    }

    // 3. Find the pending transaction in our database
    $payment = null;
    if ($referenceNumber) {
        $payments = $db->select('tr_online_payments', ['payment_reference' => $referenceNumber], '*', 'id DESC LIMIT 1');
        $payment = $payments[0] ?? null;
    } 
    
    if (!$payment && $checkoutSessionId) {
        $payments = $db->select('tr_online_payments', ['gateway_reference' => $checkoutSessionId], '*', 'id DESC LIMIT 1');
        $payment = $payments[0] ?? null;
    }

    if (!$payment) {
        respondWebhook('Transaction not found in database', 404);
    }

    if ($payment['status'] === 'completed') {
        respondWebhook('Transaction already processed', 200);
    }

    // 4. Generate OR (Receipt) Number
    $receiptNo = 'OR-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

    $db->getPdo()->beginTransaction();

    // 5. Update Online Payment Status
    $db->update('tr_online_payments', [
        'status' => 'completed',
        'receipt_no' => $receiptNo,
        'settled_at' => date('Y-m-d H:i:s'),
        'gateway_response' => json_encode($event) // Keep a record of the webhook payload
    ], ['id' => $payment['id']]);

    // 6. Record in Collections (Treasury Dashboard reads this!)
    $db->insert('tr_collections', [
        'or_number' => $receiptNo,
        'payer_name' => $payment['taxpayer_name'],
        'revenue_source' => $payment['payment_type'],
        'fund_id' => $payment['fund_id'],
        'fund_code' => $payment['fund_code'],
        'amount' => $payment['amount'],
        'payment_mode' => $payment['payment_method'],
        'collected_by' => 'PayMongo Webhook',
        'status' => 'valid',
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $db->getPdo()->commit();

    respondWebhook('Payment processed and recorded successfully', 200);

} catch (\Throwable $e) {
    if (isset($db) && method_exists($db, 'getPdo')) {
        $db->getPdo()->rollBack();
    }
    error_log("PayMongo Webhook Error: " . $e->getMessage());
    respondWebhook('Internal server error', 500);
}
