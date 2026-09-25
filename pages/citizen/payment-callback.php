<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check (Bypass if returning from PayMongo with a reference)
if (empty($_GET['reference']) && empty($_SESSION['user_id']) && empty($_SESSION['employee_id']) && empty($_SESSION['citizen_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Payment Callback';
$activePage = 'citizen-online-payment';
$errorMsg = null;
$successMsg = null;
$payment = null;

// Handle payment callback
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reference'])) {
    try {
        $reference = $_GET['reference'];
        $status = $_GET['status'] ?? 'failed';
        
        // In production, verify the callback with GCash signature
        // For demo, we'll process directly
        
        $gatewayResponse = [
            'status' => $status,
            'reference' => 'GCASH-' . time(),
            'transaction_id' => 'TXN-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 16)),
            'timestamp' => date('c'),
        ];
        
        $payment = $treasuryService->processOnlinePayment($reference, $gatewayResponse);
        
        if ($payment['status'] === 'completed') {
            $successMsg = 'Payment completed successfully! Your OR number is: ' . $payment['or_number'];
        } else {
            $errorMsg = 'Payment failed. Please try again or contact support.';
        }
    } catch (Exception $e) {
        if ($e->getMessage() === 'Payment already processed.') {
            // Fetch the payment to show the receipt anyway
            $payment = $treasuryService->getPaymentByReference($reference);
            if ($payment) {
                $successMsg = 'Payment was successful! Your OR number is: ' . ($payment['or_number'] ?? 'Pending');
            } else {
                $errorMsg = 'Payment already processed but could not retrieve details.';
            }
        } else {
            $errorMsg = $e->getMessage();
        }
    }
}

$basePath = '../../';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
    <main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

      <!-- Breadcrumb -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            <span>Citizen Services</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Payment Result</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-receipt text-brand-dark"></i>
            Payment Result
          </h1>
        </div>
      </div>

      <?php if ($successMsg): ?>
      <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-8 text-center">
        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-check text-3xl text-emerald-600"></i>
        </div>
        <h2 class="text-xl font-bold text-emerald-800 mb-2">Payment Successful!</h2>
        <p class="text-emerald-600 mb-6"><?= htmlspecialchars($successMsg) ?></p>
        
        <?php if ($payment): ?>
        <div class="max-w-md mx-auto bg-white border border-emerald-200 rounded-xl p-4 text-left">
          <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-3">
            <span class="text-xs text-slate-400">Payment Reference</span>
            <span class="text-xs font-mono font-bold text-slate-800"><?= htmlspecialchars($payment['payment_reference']) ?></span>
          </div>
          <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-3">
            <span class="text-xs text-slate-400">OR Number</span>
            <span class="text-xs font-mono font-bold text-brand-dark"><?= htmlspecialchars($payment['or_number']) ?></span>
          </div>
          <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-3">
            <span class="text-xs text-slate-400">Amount</span>
            <span class="text-xs font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($payment['amount']) ?></span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-xs text-slate-400">Date</span>
            <span class="text-xs font-bold text-slate-800"><?= date('M j, Y g:i A', strtotime($payment['created_at'])) ?></span>
          </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-6 space-x-3">
          <a href="online-payment.php" class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-lg text-sm transition">
            Make Another Payment
          </a>
          <a href="online-payment.php" class="inline-block bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50 font-bold px-6 py-2.5 rounded-lg text-sm transition">
            View Payment History
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 rounded-2xl p-8 text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-xmark text-3xl text-red-600"></i>
        </div>
        <h2 class="text-xl font-bold text-red-800 mb-2">Payment Failed</h2>
        <p class="text-red-600 mb-6"><?= htmlspecialchars($errorMsg) ?></p>
        
        <div class="mt-6 space-x-3">
          <a href="online-payment.php" class="inline-block bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-2.5 rounded-lg text-sm transition">
            Try Again
          </a>
          <a href="../../pages/dashboard.php" class="inline-block bg-white border border-red-200 text-red-700 hover:bg-red-50 font-bold px-6 py-2.5 rounded-lg text-sm transition">
            Return to Dashboard
          </a>
        </div>
      </div>
      <?php endif; ?>

    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>