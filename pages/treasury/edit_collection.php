<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        $payerName = trim($_POST['payer_name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMode = $_POST['payment_mode'] ?? 'cash';
        
        if ($id <= 0) throw new Exception('Invalid collection ID');
        if (empty($payerName)) throw new Exception('Payer name is required');
        if ($amount <= 0) throw new Exception('Amount must be greater than zero');
        
        // Update collection
        $success = $treasuryService->updateCollection($id, [
            'payer_name' => $payerName,
            'amount' => $amount,
            'payment_mode' => $paymentMode
        ]);
        
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update collection']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>