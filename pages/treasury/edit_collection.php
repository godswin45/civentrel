<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../src/bootstrap.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $id           = (int)($_POST['id'] ?? 0);
    $payerName    = trim($_POST['payer_name'] ?? '');
    $amount       = (float)($_POST['amount'] ?? 0);
    $paymentMode  = trim($_POST['payment_mode'] ?? 'cash');
    $revenueSource = trim($_POST['revenue_source'] ?? '');
    $fundId       = trim($_POST['fund_id'] ?? '');
    $auditNote    = trim($_POST['audit_note'] ?? '');

    if ($id <= 0)         throw new Exception('Invalid collection ID');
    if (empty($payerName)) throw new Exception('Payer name is required');
    if ($amount <= 0)     throw new Exception('Amount must be greater than zero');

    $data = [
        'payer_name'   => $payerName,
        'amount'       => $amount,
        'payment_mode' => $paymentMode,
    ];

    if ($revenueSource !== '') $data['revenue_source'] = $revenueSource;
    if ($fundId !== '')        $data['fund_id']        = $fundId;
    if ($auditNote !== '')     $data['audit_note']     = $auditNote;  // stored if column exists

    $success = $treasuryService->updateCollection($id, $data);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update collection']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>