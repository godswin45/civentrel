<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Business Tax & Regulatory Fees';
$activePage = 'treasury-business-tax';
$errorMsg = null;
$lastReceipt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_business_tax') {
    try {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception('Amount must be greater than zero.');

        $lastReceipt = $treasuryService->recordCollection([
            'payer_name'     => trim($_POST['business_name'] ?? ''),
            'revenue_source' => $_POST['tax_type'] ?? 'Business Tax',
            'fund_id'        => $_POST['fund_id'] ?? 'BSF',
            'amount'         => $amount,
            'payment_mode'   => $_POST['payment_mode'] ?? 'cash',
            'collected_by'   => $headerUser['full_name'] ?? null,
        ]);
        
        // Log the transaction
        if ($auditService) {
            $auditService->logTransaction([
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $headerUser['full_name'] ?? 'System',
                'module' => 'collection',
                'action' => 'collect',
                'table_name' => 'tr_collections',
                'record_id' => $lastReceipt['id'] ?? null,
                'new_values' => json_encode($lastReceipt)
            ]);
        }

        $_SESSION['flash_receipt'] = $lastReceipt;
        header('Location: business-tax.php?issued=1');
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

if (isset($_GET['issued']) && !empty($_SESSION['flash_receipt'])) {
    $lastReceipt = $_SESSION['flash_receipt'];
    unset($_SESSION['flash_receipt']);
}

$allCollections = [];
try {
    $funds = $treasuryService->getFunds();
    $allCollections = $treasuryService->getAllCollections();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = [];
}

$taxTypes = ['Business Tax', 'Mayor\'s Permit Fee', 'Sanitary Inspection Fee', 'Fire Safety Fee', 'Zoning Fee', 'Building Permit Fee', 'Electrical Inspection Fee', 'Plumbing Fee'];

$basePath = '../../';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
    <main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

      <!-- Breadcrumb -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            <span>Revenue Collection & Treasury</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Business Tax & Fees</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-file-invoice-dollar text-brand-dark"></i>
            Business Tax & Regulatory Fees
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Business permits and regulatory fee collection system.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <form method="post" class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 space-y-4 h-fit">
          <input type="hidden" name="action" value="record_business_tax">
          <h2 class="text-sm font-extrabold text-slate-800 pb-1">Business Tax Payment</h2>

         <?php
  $bizNames = array_unique(array_filter(array_column($allCollections, 'payer_name')));
  sort($bizNames);
?>
<div class="space-y-1.5">
  <label class="text-xs font-semibold text-gray-500">Business Name</label>
  <input type="text" name="business_name" required placeholder="e.g. Dela Cruz Sari-Sari Store"
    list="biz-names-list" autocomplete="off"
    class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
  <datalist id="biz-names-list">
    <?php foreach ($bizNames as $bn): ?>
    <option value="<?= htmlspecialchars($bn) ?>">
    <?php endforeach; ?>
  </datalist>
</div>


          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Business Permit Number</label>
            <input type="text" name="permit_number" placeholder="e.g. BP-2026-001234"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Tax Type</label>
            <select name="tax_type" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              <?php foreach ($taxTypes as $type): ?>
                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
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
              <label class="text-xs font-semibold text-gray-500">Mode</label>
              <select name="payment_mode" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <option value="cash">Cash</option>
                <option value="online">Online / E-wallet</option>
              </select>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Amount (₱)</label>
            <div class="relative flex items-center">
              <span class="absolute left-4 text-gray-400 text-sm font-bold">₱</span>
              <input type="number" name="amount" min="1" step="0.01" required placeholder="0.00"
                class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono">
            </div>
          </div>

          <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
            Record Business Tax Payment
          </button>
        </form>

        <div class="lg:col-span-3 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
          <h2 class="text-sm font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Official Receipt</h2>
          <?php if ($lastReceipt): ?>
            <div class="border-2 border-brand-border rounded-2xl p-8 bg-gradient-to-b from-brand-light/80 to-white relative w-full">
              <div class="flex justify-between items-start border-b-2 border-dashed border-brand-border pb-4 mb-4">
                <div>
                  <div class="text-lg font-black text-brand-dark">Official Receipt</div>
                  <div class="text-xs uppercase tracking-wider text-slate-400">Business Tax & Fees</div>
                </div>
                <div class="font-mono text-sm font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['or_number']) ?></div>
              </div>
              <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Received from</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['payer_name']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Nature of collection</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['revenue_source']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Payment mode</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['payment_mode']) ?></dd></div>
                <div class="flex justify-between border-t-2 border-slate-200 pt-3 mt-3"><dt class="text-slate-500 font-semibold">Amount</dt><dd class="font-black text-brand-dark text-2xl"><?= $treasuryService->formatPeso($lastReceipt['amount']) ?></dd></div>
              </dl>
              <div class="mt-6 pt-4 border-t-2 border-dashed border-brand-border text-xs text-slate-500">
                <div class="flex justify-between">
                  <span>Collected by: <?= htmlspecialchars($lastReceipt['collected_by'] ?? 'System') ?></span>
                  <span><?= date('M j, Y g:i A', strtotime($lastReceipt['created_at'])) ?></span>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="text-center py-12 text-slate-400">
              <i class="fa-solid fa-receipt text-4xl mb-3 opacity-30"></i>
              <p class="text-xs">No receipt issued yet. Record a business tax payment to generate one.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <!-- Transaction History -->
      <?php 
      $module = 'business_tax';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>