<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Allow anonymous access for citizens (no auth required)
// Citizens can use the system without logging in

$pageTitle = 'Online Payment Portal';
$activePage = 'citizen-online-payment';
$errorMsg = null;
$successMsg = null;
$createdPayment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_payment') {
    try {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception('Amount must be greater than zero.');

        $paymentData = [
            'citizen_id'     => null, // Anonymous users
            'citizen_name'   => trim($_POST['citizen_name'] ?? ''),
            'citizen_email'  => trim($_POST['citizen_email'] ?? ''),
            'citizen_phone'  => trim($_POST['citizen_phone'] ?? ''),
            'payment_type'   => $_POST['payment_type'] ?? 'generic',
            'amount'         => $amount,
            'payment_source' => $_POST['payment_source'] ?? 'General Payment',
            'fund_id'        => $_POST['fund_id'] ?? 'general',
        ];

        $createdPayment = $treasuryService->createOnlinePayment($paymentData);
        $successMsg = 'Payment request created successfully. Please complete payment via GCash.';
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

try {
    $funds = $treasuryService->getFunds();
    $paymentHistory = []; // No payment history for anonymous users
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = [];
    $paymentHistory = [];
}

$paymentTypes = [
    'property_tax' => 'Real Property Tax',
    'business_tax' => 'Business Tax & Fees',
    'market_rental' => 'Market Stall Rental',
    'community_tax' => 'Community Tax Certificate',
    'generic' => 'General Payment'
];

$basePath = '../../';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
    <main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

      <!-- Breadcrumb -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            <span>Citizen Services</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Online Payment Portal</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-credit-card text-brand-dark"></i>
            Online Payment Portal
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Pay your taxes, fees, and bills online using GCash payment gateway.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($successMsg): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-check mt-0.5"></i><span><?= htmlspecialchars($successMsg) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($createdPayment): ?>
      <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-2xl p-6 shadow-md">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold">Payment Request Created</h2>
            <p class="text-emerald-100 text-sm mt-1">Reference: <?= htmlspecialchars($createdPayment['payment_reference']) ?></p>
            <p class="text-emerald-100 text-sm">Amount: <?= $treasuryService->formatPeso($createdPayment['amount']) ?></p>
          </div>
          <div class="text-right">
            <div class="text-3xl font-bold"><?= $treasuryService->formatPeso($createdPayment['amount']) ?></div>
            <div class="text-emerald-100 text-sm mt-1">Status: <?= ucfirst($createdPayment['status']) ?></div>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-emerald-400/30">
          <button onclick="initiateGCashPayment('<?= htmlspecialchars($createdPayment['payment_reference']) ?>', <?= $createdPayment['amount'] ?>)" class="bg-white text-emerald-600 hover:bg-emerald-50 font-bold px-6 py-2.5 rounded-lg text-sm transition flex items-center gap-2">
            <i class="fa-solid fa-mobile-screen"></i>
            Pay with GCash
          </button>
        </div>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
          <form method="post" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 space-y-4">
            <input type="hidden" name="action" value="create_payment">
            <h2 class="text-sm font-extrabold text-slate-800 pb-1">Create New Payment</h2>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Full Name</label>
              <input type="text" name="citizen_name" required placeholder="e.g. Dela Cruz, Marites"
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Email Address</label>
                <input type="email" name="citizen_email" placeholder="email@example.com"
                  class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Phone Number</label>
                <input type="tel" name="citizen_phone" placeholder="09123456789"
                  class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Payment Type</label>
              <select name="payment_type" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <?php foreach ($paymentTypes as $key => $label): ?>
                  <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Payment Description</label>
              <input type="text" name="payment_source" placeholder="e.g. 2026 Real Property Tax - Barangay 123"
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Fund</label>
                <select name="fund_id" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                  <?php foreach ($funds as $f): ?>
                    <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['code']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Amount (₱)</label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-gray-400 text-sm font-bold">₱</span>
                  <input type="number" name="amount" min="1" step="0.01" required placeholder="0.00"
                    class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono">
                </div>
              </div>
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
              Create Payment Request
            </button>
          </form>
        </div>

        <div class="lg:col-span-1">
          <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
            <h2 class="text-sm font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Payment Information</h2>
            
            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-lg bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark flex-shrink-0">
                  <i class="fa-solid fa-shield-halved text-sm"></i>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">Secure Payment</h3>
                  <p class="text-[11px] text-slate-500">All transactions are encrypted and secure</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-lg bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark flex-shrink-0">
                  <i class="fa-solid fa-clock text-sm"></i>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">24/7 Availability</h3>
                  <p class="text-[11px] text-slate-500">Pay anytime, anywhere</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-lg bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark flex-shrink-0">
                  <i class="fa-solid fa-receipt text-sm"></i>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">Instant Receipt</h3>
                  <p class="text-[11px] text-slate-500">Receive OR number immediately after payment</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-lg bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-600 flex-shrink-0">
                  <i class="fa-solid fa-mobile-screen text-sm"></i>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">GCash Payment</h3>
                  <p class="text-[11px] text-slate-500">Pay using your GCash account</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($paymentHistory)): ?>
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Payment History</h2>
          <span class="text-[11px] text-slate-400"><?= count($paymentHistory) ?> transactions</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                <th class="px-5 py-3 font-bold">Reference</th>
                <th class="px-5 py-3 font-bold">Type</th>
                <th class="px-5 py-3 font-bold">Amount</th>
                <th class="px-5 py-3 font-bold">Status</th>
                <th class="px-5 py-3 font-bold">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($paymentHistory as $payment): ?>
              <tr class="hover:bg-brand-light/40 transition">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($payment['payment_reference']) ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($payment['payment_source']) ?></td>
                <td class="px-5 py-3 font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($payment['amount']) ?></td>
                <td class="px-5 py-3">
                  <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase
                    <?= $payment['status'] === 'completed' ? 'bg-emerald-100 text-emerald-700' : 
                       ($payment['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : 
                       ($payment['status'] === 'failed' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700')) ?>">
                    <?= htmlspecialchars($payment['status']) ?>
                  </span>
                </td>
                <td class="px-5 py-3 text-slate-500"><?= date('M j, Y g:i A', strtotime($payment['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
      <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center">
        <i class="fa-solid fa-info-circle text-slate-400 text-2xl mb-2"></i>
        <p class="text-xs text-slate-500">Payment history is available for registered citizens. <a href="../../login.php" class="text-brand-dark font-bold hover:underline">Register now</a> to track your payment history.</p>
      </div>
      <?php endif; ?>
    </main>

    <script>
    function initiateGCashPayment(reference, amount) {
        // This would integrate with GCash API
        // For now, show a demo interface
        alert('GCash Payment Integration\n\nReference: ' + reference + '\nAmount: ₱' + amount.toFixed(2) + '\n\nIn production, this would redirect to GCash payment page.');
        
        // Simulate payment callback for demo
        if (confirm('Simulate successful payment?')) {
            window.location.href = 'payment-callback.php?reference=' + reference + '&status=success';
        }
    }
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>