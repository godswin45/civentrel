<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Revenue Collection';
$activePage = 'treasury-collection';
$errorMsg = null;
$lastReceipt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_collection') {
    try {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception('Amount must be greater than zero.');

        $lastReceipt = $treasuryService->recordCollection([
            'payer_name'     => trim($_POST['payer_name'] ?? ''),
            'revenue_source' => $_POST['revenue_source'] ?? '',
            'fund_id'        => $_POST['fund_id'] ?? 'GF',
            'amount'         => $amount,
            'payment_mode'   => $_POST['payment_mode'] ?? 'cash',
            'collected_by'   => $headerUser['full_name'] ?? null,
        ]);
        
        // Log the transaction
        if ($auditService) {
            $auditService->logTransaction([
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $headerUser['full_name'] ?? 'System',
                'action' => 'collect',
                'table_name' => 'tr_collections',
                'record_id' => $lastReceipt['id'] ?? null,
                'new_values' => json_encode($lastReceipt)
            ]);
        }

        // If this payment closes out a Business Renewal/Retirement application
        $bizRefPost = trim($_POST['biz_ref'] ?? '');
        if ($bizRefPost !== '') {
            $app = $treasuryService->getBusinessAppByNo($bizRefPost);
            if ($app) {
                $treasuryService->setBusinessAppStatus($app['id'], 'Paid', ['or_number' => $lastReceipt['or_number']]);
            }
        }

        $_SESSION['flash_receipt'] = $lastReceipt;
        header('Location: collection.php?issued=1');
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

$bizRef = $_GET['biz_ref'] ?? '';
$bizName = $_GET['biz_name'] ?? '';
$bizType = $_GET['biz_type'] ?? '';

if (isset($_GET['issued']) && !empty($_SESSION['flash_receipt'])) {
    $lastReceipt = $_SESSION['flash_receipt'];
    unset($_SESSION['flash_receipt']);
}

try {
    $funds = $treasuryService->getFunds();
    $collections = $treasuryService->getAllCollections();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = []; $collections = [];
}

$revenueSources = ['Real Property Tax', 'Business Permit & License', 'Market & Slaughterhouse Fees', 'Community Tax Certificate', 'Miscellaneous Fees'];

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
            <span class="text-brand-dark">Revenue Collection</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-receipt text-brand-dark"></i>
            Revenue Collection
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Record a payment and issue an official receipt.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <form method="post" class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-xs p-5 space-y-4 h-fit">
          <input type="hidden" name="action" value="record_collection">
          <input type="hidden" name="biz_ref" value="<?= htmlspecialchars($bizRef) ?>">
          <h2 class="text-sm font-extrabold text-slate-800 pb-1">Payment Details</h2>

          <?php if ($bizRef): ?>
          <div class="bg-brand-light border border-brand-border rounded-lg px-3 py-2.5 text-[11px] text-brand-dark flex items-start gap-2">
            <i class="fa-solid fa-link mt-0.5"></i>
            <span>Linked to Business <?= $bizType === 'retirement' ? 'Retirement' : 'Renewal' ?> application <strong><?= htmlspecialchars($bizRef) ?></strong>. Recording this payment will mark it Paid.</span>
          </div>
          <?php endif; ?>

          <?php
            $payerNames = array_unique(array_filter(array_column($collections, 'payer_name')));
            sort($payerNames);
          ?>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Payer name</label>
            <input type="text" name="payer_name" required value="<?= htmlspecialchars($bizName) ?>" placeholder="e.g. Dela Cruz, Marites"
              list="payer-names-list" autocomplete="off"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
            <datalist id="payer-names-list">
              <?php foreach ($payerNames as $pn): ?>
              <option value="<?= htmlspecialchars($pn) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Revenue source</label>
            <select name="revenue_source" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              <?php foreach ($revenueSources as $src): ?>
                <option value="<?= htmlspecialchars($src) ?>" <?= ($bizRef && $src === 'Business Permit & License') ? 'selected' : '' ?>><?= htmlspecialchars($src) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Fund</label>
              <select name="fund_id" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <?php
                $fundLabels = [
                    'BSF' => 'Business Service Fund',
                    'EDU' => 'Education Fund',
                    'GF' => 'General Fund',
                    'HLTH' => 'Health Fund',
                    'INFRA' => 'Infrastructure Fund',
                    'MSF' => 'Market Stall Fund',
                    'PTF' => 'Property Tax Fund',
                    'RDF' => 'Risk Disaster Fund',
                ];
                ?>
                <?php foreach ($funds as $f): ?>
                  <?php $code = strtoupper((string) ($f['code'] ?? '')); ?>
                  <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($fundLabels[$code] ?? $code) ?></option>
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
            Record &amp; issue receipt
          </button>
          <p class="text-[11px] text-slate-400">Receipt number is generated automatically and the fund balance updates immediately.</p>
        </form>

        <div class="lg:col-span-3 bg-white border border-slate-200 rounded-xl shadow-xs p-5">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h2 class="text-sm font-extrabold text-slate-800">Official Receipt</h2>
            <?php if ($lastReceipt): ?>
            <button type="button" onclick="window.print()"
              class="inline-flex items-center gap-2 bg-brand-dark hover:opacity-90 text-white font-bold px-3 py-2 rounded-lg text-xs transition shadow-sm no-print">
              <i class="fa-solid fa-print"></i> Print Receipt
            </button>
            <?php endif; ?>
          </div>

          <?php if ($lastReceipt): ?>
          <div id="or-print-area" class="border-2 border-brand-border rounded-2xl p-8 bg-gradient-to-b from-brand-light/80 to-white">

            <!-- Print letterhead — hidden on screen -->
            <div class="or-print-only text-center mb-4 pb-3 border-b-2 border-dashed border-brand-border">
              <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Republic of the Philippines</p>
              <p class="text-sm font-black uppercase text-slate-900"><?= htmlspecialchars(getenv('LGU_NAME') ?: 'Municipality') ?></p>
              <p class="text-xs text-slate-500">Office of the Municipal Treasurer</p>
            </div>

            <div class="flex justify-between items-start border-b-2 border-dashed border-brand-border pb-4 mb-4">
              <div>
                <div class="text-lg font-black text-brand-dark">Official Receipt</div>
                <div class="text-xs uppercase tracking-wider text-slate-400">Municipal Treasurer's Office</div>
              </div>
              <div class="text-right">
                <div class="font-mono text-sm font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['or_number']) ?></div>
                <div class="text-[10px] text-slate-400 mt-0.5"><?= date('F j, Y g:i A', strtotime($lastReceipt['created_at'])) ?></div>
              </div>
            </div>

            <dl class="space-y-3 text-sm">
              <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Received from</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['payer_name']) ?></dd></div>
              <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Nature of collection</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['revenue_source']) ?></dd></div>
              <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Payment mode</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars(ucfirst($lastReceipt['payment_mode'])) ?></dd></div>
              <div class="flex justify-between border-t-2 border-slate-200 pt-3 mt-3">
                <dt class="text-slate-500 font-semibold self-end">Amount</dt>
                <dd class="font-black text-brand-dark text-2xl"><?= $treasuryService->formatPeso($lastReceipt['amount']) ?></dd>
              </div>
            </dl>

            <div class="mt-6 pt-4 border-t-2 border-dashed border-brand-border text-xs text-slate-500">
              <div class="flex justify-between">
                <span>Collected by: <?= htmlspecialchars($lastReceipt['collected_by'] ?? 'System') ?></span>
                <span class="font-mono"><?= htmlspecialchars($lastReceipt['or_number']) ?></span>
              </div>
              <!-- Signature block — print only -->
              <div class="or-print-only mt-10 grid grid-cols-2 gap-10">
                <div>
                  <div class="border-b border-slate-700 pb-1 mt-8"></div>
                  <p class="text-xs font-bold text-slate-800 mt-1"><?= htmlspecialchars($lastReceipt['collected_by'] ?? 'Cashier') ?></p>
                  <p class="text-[10px] text-slate-500">Collecting Officer</p>
                </div>
                <div>
                  <div class="border-b border-slate-700 pb-1 mt-8"></div>
                  <p class="text-xs font-bold text-slate-800 mt-1">Municipal Treasurer</p>
                  <p class="text-[10px] text-slate-500"><?= htmlspecialchars(getenv('LGU_NAME') ?: 'Municipality') ?></p>
                </div>
              </div>
            </div>
          </div>

          <?php else: ?>
          <div class="flex flex-col items-center text-center py-12 gap-3 text-slate-400">
            <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
              <i class="fa-solid fa-receipt text-3xl text-slate-300"></i>
            </div>
            <p class="text-xs font-bold">No receipt issued yet</p>
            <p class="text-[11px]">Record a payment on the left to generate an official receipt.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Transaction History -->
      <?php
      $module = 'collection';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<style>
  .or-print-only { display: none; }
  @media print {
    /* Hide all chrome */
    aside, header, nav, footer, form,
    .no-print, .lg\:col-span-2 { display: none !important; }

    body, html { background: #fff !important; margin: 0; padding: 0; }

    main { width: 100% !important; padding: 16px !important; margin: 0 !important;
           overflow: visible !important; display: block !important; }

    /* Expand receipt column to full width */
    .grid { display: block !important; }
    .lg\:col-span-3 {
      width: 100% !important; border: none !important;
      box-shadow: none !important; padding: 0 !important;
    }
    /* Header row inside the card (Print Receipt button) */
    .lg\:col-span-3 > div:first-child { border: none !important; padding: 0 !important; margin-bottom: 0 !important; }

    /* The receipt area */
    #or-print-area {
      border: 1.5px solid #94a3b8 !important;
      border-radius: 0 !important;
      background: #fff !important;
      padding: 28px !important;
      max-width: 440px;
      margin: 0 auto;
    }

    /* Show print-only elements */
    .or-print-only { display: block !important; }

    .shadow-xs, .shadow-sm { box-shadow: none !important; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>