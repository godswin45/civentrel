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
$successMsg = null;
$lastReceipt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_assessment') {
            $taxDue = (float) ($_POST['tax_due'] ?? 0);
            if ($taxDue <= 0) throw new Exception('Tax due must be greater than zero.');
            $treasuryService->createTaxAssessment([
                'taxpayer_name'  => trim($_POST['taxpayer_name'] ?? ''),
                'taxpayer_email' => trim($_POST['taxpayer_email'] ?? ''),
                'tax_type'       => $_POST['tax_type'] ?? 'Real Property Tax',
                'assessed_value' => (float) ($_POST['assessed_value'] ?? 0),
                'tax_due'        => $taxDue,
                'due_date'       => $_POST['due_date'] ?? date('Y-m-d'),
            ]);
            header('Location: collection.php?assess_added=1');
            exit;
        } elseif ($_POST['action'] === 'record_collection') {
            $amount = (float) ($_POST['amount'] ?? 0);
            if ($amount <= 0) throw new Exception('Amount must be greater than zero.');

            $lastReceipt = $treasuryService->recordCollection([
                'payer_name'       => trim($_POST['payer_name'] ?? ''),
                'revenue_source'   => $_POST['revenue_source'] ?? '',
                'fund_id'          => $_POST['fund_id'] ?? 'GF',
                'basic_amount'     => (float) ($_POST['basic_amount'] ?? $amount),
                'surcharge_amount' => (float) ($_POST['surcharge_amount'] ?? 0),
                'penalty_amount'   => (float) ($_POST['penalty_amount'] ?? 0),
                'amount'           => $amount,
                'assessment_no'    => trim($_POST['assessment_no'] ?? ''),
                'payment_mode'     => $_POST['payment_mode'] ?? 'cash',
                'collected_by'     => $headerUser['full_name'] ?? null,
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
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

$bizRef = $_GET['biz_ref'] ?? '';
$bizName = $_GET['biz_name'] ?? '';
$bizType = $_GET['biz_type'] ?? '';
if (isset($_GET['assess_added'])) {
    $successMsg = 'New tax assessment recorded successfully.';
}

if (isset($_GET['issued']) && !empty($_SESSION['flash_receipt'])) {
    $lastReceipt = $_SESSION['flash_receipt'];
    unset($_SESSION['flash_receipt']);
}

try {
    $funds = $treasuryService->getFunds();
    $collections = $treasuryService->getAllCollections();
    $assessments = $treasuryService->getAllTaxAssessments();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = []; $collections = []; $assessments = [];
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
      <?php if ($successMsg): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-check mt-0.5"></i><span><?= htmlspecialchars($successMsg) ?></span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <form method="post" id="collectionForm" class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-xs p-5 space-y-4 h-fit">
          <input type="hidden" name="action" value="record_collection">
          <input type="hidden" name="biz_ref" value="<?= htmlspecialchars($bizRef) ?>">
          <input type="hidden" name="assessment_no" id="col_assessment_no" value="">
          <h2 class="text-sm font-extrabold text-slate-800 pb-1">Payment Details</h2>

          <?php if ($bizRef): ?>
          <div class="bg-brand-light border border-brand-border rounded-lg px-3 py-2.5 text-[11px] text-brand-dark flex items-start gap-2">
            <i class="fa-solid fa-link mt-0.5"></i>
            <span>Linked to Business <?= $bizType === 'retirement' ? 'Retirement' : 'Renewal' ?> application <strong><?= htmlspecialchars($bizRef) ?></strong>. Recording this payment will mark it Paid.</span>
          </div>
          <?php endif; ?>

          <div id="linkedAssessmentBanner" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-[11px] text-amber-800 flex items-center justify-between gap-2">
            <span><i class="fa-solid fa-file-invoice mr-1"></i> Linked Assessment: <strong id="linkedAssessmentLabel"></strong></span>
            <button type="button" onclick="clearLinkedAssessment()" class="text-amber-700 hover:text-amber-900 font-bold"><i class="fa-solid fa-xmark"></i></button>
          </div>

          <?php
            $payerNames = array_unique(array_filter(array_column($collections, 'payer_name')));
            sort($payerNames);
          ?>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Payer name</label>
            <input type="text" name="payer_name" id="col_payer_name" required value="<?= htmlspecialchars($bizName) ?>" placeholder="e.g. Dela Cruz, Marites"
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
            <select name="revenue_source" id="col_revenue_source" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              <?php foreach ($revenueSources as $src): ?>
                <option value="<?= htmlspecialchars($src) ?>" <?= ($bizRef && $src === 'Business Permit & License') ? 'selected' : '' ?>><?= htmlspecialchars($src) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Fund</label>
              <select name="fund_id" id="col_fund_id" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
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
                  <option value="<?= htmlspecialchars($f['id']) ?>" data-code="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($fundLabels[$code] ?? $code) ?></option>
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

          <!-- Itemized Breakdown: Basic Tax, Surcharge, Penalty/Interest -->
          <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Itemized Payment Breakdown</span>
              <div class="flex items-center gap-1">
                <button type="button" onclick="applyStatutorySurcharge()" class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 hover:bg-amber-200 transition" title="Add 25% statutory late surcharge">+25% Surcharge</button>
                <button type="button" onclick="applyMonthlyInterest()" class="text-[10px] font-bold px-2 py-0.5 rounded bg-rose-100 text-rose-800 hover:bg-rose-200 transition" title="Add 2% monthly delinquency interest">+2% Interest</button>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-[10px] font-bold text-slate-500 block mb-1">Basic Tax / Principal</label>
                <input type="number" name="basic_amount" id="col_basic_amount" min="0" step="0.01" placeholder="0.00" oninput="recalcCollectionTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-gray-800">
              </div>
              <div>
                <label class="text-[10px] font-bold text-amber-600 block mb-1">Surcharge (₱)</label>
                <input type="number" name="surcharge_amount" id="col_surcharge_amount" min="0" step="0.01" value="0.00" oninput="recalcCollectionTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-amber-700">
              </div>
              <div>
                <label class="text-[10px] font-bold text-rose-600 block mb-1">Penalty / Interest</label>
                <input type="number" name="penalty_amount" id="col_penalty_amount" min="0" step="0.01" value="0.00" oninput="recalcCollectionTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-rose-700">
              </div>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Total Amount Due (₱)</label>
            <div class="relative flex items-center">
              <span class="absolute left-4 text-gray-400 text-sm font-bold">₱</span>
              <input type="number" name="amount" id="col_total_amount" min="1" step="0.01" required placeholder="0.00"
                class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono font-bold">
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

          <?php if ($lastReceipt):
            $rBasic = (float) ($lastReceipt['basic_amount'] ?? $lastReceipt['amount'] ?? 0);
            $rSurcharge = (float) ($lastReceipt['surcharge_amount'] ?? 0);
            $rPenalty = (float) ($lastReceipt['penalty_amount'] ?? 0);
            if ($rBasic <= 0 && $rSurcharge <= 0 && $rPenalty <= 0) {
                $rBasic = (float) ($lastReceipt['amount'] ?? 0);
            }
          ?>
          <div id="or-print-area" class="border-2 border-brand-border rounded-2xl p-8 bg-gradient-to-b from-brand-light/80 to-white">

            <!-- Print letterhead — hidden on screen -->
            <div class="or-print-only text-center mb-4 pb-3 border-b-2 border-dashed border-brand-border">
              <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Republic of the Philippines</p>
              <p class="text-sm font-black uppercase text-slate-900"><?= htmlspecialchars(getenv('LGU_NAME') ?: 'City of Caloocan') ?></p>
              <p class="text-xs text-slate-500">Office of the City Treasurer</p>
            </div>

            <div class="flex justify-between items-start border-b-2 border-dashed border-brand-border pb-4 mb-4">
              <div>
                <div class="text-lg font-black text-brand-dark">Official Receipt</div>
                <div class="text-xs uppercase tracking-wider text-slate-400">Office of the Treasurer</div>
              </div>
              <div class="text-right">
                <div class="font-mono text-sm font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['or_number'] ?? '—') ?></div>
                <div class="text-[10px] text-slate-400 mt-0.5"><?= date('F j, Y g:i A', strtotime($lastReceipt['created_at'])) ?></div>
              </div>
            </div>

            <dl class="space-y-2.5 text-sm">
              <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Received from</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['payer_name']) ?></dd></div>
              <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Nature of collection</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['revenue_source']) ?></dd></div>
              <?php if (!empty($lastReceipt['assessment_no'])): ?>
              <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Assessment No.</dt><dd class="font-mono font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['assessment_no']) ?></dd></div>
              <?php endif; ?>
              <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Payment mode</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars(ucfirst($lastReceipt['payment_mode'])) ?></dd></div>
              <div class="border-t border-dashed border-slate-200 pt-2.5 mt-2.5 space-y-1.5 text-xs">
                <div class="flex justify-between"><dt class="text-slate-500">Basic Tax / Principal Amount</dt><dd class="font-mono font-semibold text-slate-700"><?= $treasuryService->formatPeso($rBasic) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Surcharge (Late Payment)</dt><dd class="font-mono font-semibold text-amber-700"><?= $treasuryService->formatPeso($rSurcharge) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Penalty / Delinquency Interest</dt><dd class="font-mono font-semibold text-rose-700"><?= $treasuryService->formatPeso($rPenalty) ?></dd></div>
              </div>
              <div class="flex justify-between border-t-2 border-slate-200 pt-3 mt-3">
                <dt class="text-slate-500 font-semibold self-end">Total Amount Paid</dt>
                <dd class="font-black text-brand-dark text-2xl"><?= $treasuryService->formatPeso($lastReceipt['amount']) ?></dd>
              </div>
            </dl>

            <div class="mt-6 pt-4 border-t-2 border-dashed border-brand-border text-xs text-slate-500">
              <div class="flex justify-between">
                <span>Collected by: <?= htmlspecialchars($lastReceipt['collected_by'] ?? 'System') ?></span>
                <span class="font-mono"><?= htmlspecialchars($lastReceipt['or_number'] ?? '—') ?></span>
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
                  <p class="text-xs font-bold text-slate-800 mt-1">City Treasurer</p>
                  <p class="text-[10px] text-slate-500"><?= htmlspecialchars(getenv('LGU_NAME') ?: 'City of Caloocan') ?></p>
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
            <p class="text-[11px]">Record a payment on the left or click "Collect Now" from a Tax Assessment below.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Tax Assessments & Delinquency Monitor (RPT & Business Tax) -->
      <div class="no-print bg-white border border-slate-200 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
              <i class="fa-solid fa-scale-unbalanced-flip text-brand-dark"></i>
              Tax Assessments &amp; Delinquency Monitor (RPT &amp; Business Tax)
            </h2>
            <p class="text-[11px] text-slate-400 mt-0.5">Real Property Tax &amp; Business Tax assessments with automatic statutory surcharge (25%) and delinquency interest (2%/mo) computation</p>
          </div>
          <button type="button" onclick="document.getElementById('newAssessmentModal').classList.remove('hidden')"
            class="inline-flex items-center gap-1.5 bg-brand-dark hover:opacity-90 text-white text-xs font-bold px-3.5 py-2 rounded-lg transition shadow-xs">
            <i class="fa-solid fa-plus"></i> New Assessment
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50 border-b border-slate-100">
                <th class="px-4 py-3 font-bold">Assessment No.</th>
                <th class="px-4 py-3 font-bold">Taxpayer / Property</th>
                <th class="px-4 py-3 font-bold">Tax Type</th>
                <th class="px-4 py-3 font-bold text-right">Assessed Value</th>
                <th class="px-4 py-3 font-bold text-right">Basic Tax Due</th>
                <th class="px-4 py-3 font-bold text-right">Surcharge &amp; Interest</th>
                <th class="px-4 py-3 font-bold text-right">Total Collectible</th>
                <th class="px-4 py-3 font-bold">Due Date</th>
                <th class="px-4 py-3 font-bold">Status</th>
                <th class="px-4 py-3 font-bold">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($assessments as $as):
                $st = strtolower((string) ($as['status'] ?? 'unpaid'));
                $basicDue = (float) ($as['tax_due'] ?? 0);
                $dueTs = strtotime($as['due_date'] ?? 'now');
                $isOverdue = ($st === 'overdue') || ($st !== 'paid' && $dueTs < strtotime(date('Y-m-d')));
                $monthsLate = $isOverdue ? max(1, min(36, (int) ceil((time() - $dueTs) / (30 * 86400)))) : 0;
                $surcharge = $isOverdue ? round($basicDue * 0.25, 2) : 0.0;
                $interest  = $isOverdue ? round(($basicDue + $surcharge) * (0.02 * $monthsLate), 2) : 0.0;
                $totalDue  = $basicDue + $surcharge + $interest;
                $fundCodeGuess = stripos((string) $as['tax_type'], 'Property') !== false ? 'PTF' : 'BSF';
                $badgeCls = match ($st === 'paid' ? 'paid' : ($isOverdue ? 'overdue' : $st)) {
                    'paid'    => 'bg-emerald-100 text-emerald-700',
                    'overdue' => 'bg-rose-100 text-rose-700',
                    default   => 'bg-amber-100 text-amber-700',
                };
              ?>
              <tr class="hover:bg-slate-50/70 transition">
                <td class="px-4 py-3 font-mono font-bold text-brand-dark whitespace-nowrap"><?= htmlspecialchars($as['assessment_no']) ?></td>
                <td class="px-4 py-3 font-semibold text-slate-700"><?= htmlspecialchars($as['taxpayer_name']) ?></td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($as['tax_type']) ?></td>
                <td class="px-4 py-3 font-mono text-right text-slate-500"><?= $treasuryService->formatPeso($as['assessed_value'] ?? 0) ?></td>
                <td class="px-4 py-3 font-mono font-bold text-right text-slate-800"><?= $treasuryService->formatPeso($basicDue) ?></td>
                <td class="px-4 py-3 font-mono text-right <?= $isOverdue ? 'text-rose-600 font-bold' : 'text-slate-400' ?>">
                  <?= $isOverdue ? ('+' . $treasuryService->formatPeso($surcharge + $interest) . ' (' . $monthsLate . 'mo)') : '₱0.00' ?>
                </td>
                <td class="px-4 py-3 font-mono font-black text-right text-slate-900"><?= $treasuryService->formatPeso($totalDue) ?></td>
                <td class="px-4 py-3 font-mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($as['due_date']) ?></td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= $badgeCls ?>">
                    <?= $st === 'paid' ? 'PAID' : ($isOverdue ? 'OVERDUE' : strtoupper($st)) ?>
                  </span>
                </td>
                <td class="px-4 py-3">
                  <?php if ($st !== 'paid'): ?>
                  <button type="button"
                    onclick="loadAssessmentIntoForm(<?= htmlspecialchars(json_encode([
                        'assessment_no' => $as['assessment_no'],
                        'taxpayer_name' => $as['taxpayer_name'],
                        'tax_type'      => $as['tax_type'],
                        'fund_code'     => $fundCodeGuess,
                        'basic'         => $basicDue,
                        'surcharge'     => $surcharge,
                        'penalty'       => $interest,
                        'total'         => $totalDue,
                    ]), ENT_QUOTES) ?>)"
                    class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-2.5 py-1.5 rounded-lg text-[10px] transition shadow-xs">
                    <i class="fa-solid fa-cash-register"></i> Collect Now
                  </button>
                  <?php else: ?>
                  <span class="text-[10px] font-bold text-emerald-600"><i class="fa-solid fa-check-double"></i> Settled</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- New Assessment Modal -->
      <div id="newAssessmentModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden no-print">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 shadow-2xl">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-black text-slate-900">New Tax Assessment</h3>
            <button type="button" onclick="document.getElementById('newAssessmentModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="create_assessment">
            <div>
              <label class="text-xs font-semibold text-slate-600">Taxpayer / Property Owner</label>
              <input type="text" name="taxpayer_name" required placeholder="e.g. Santos, Roberto (TCT-88210)" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600">Taxpayer Email (for Reminders)</label>
              <input type="email" name="taxpayer_email" placeholder="taxpayer@example.com" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600">Tax Type</label>
              <select name="tax_type" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
                <option value="Real Property Tax">Real Property Tax (RPT)</option>
                <option value="Business Permit &amp; License">Business Permit &amp; License</option>
                <option value="Community Tax Certificate">Community Tax Certificate</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-600">Assessed Value (₱)</label>
                <input type="number" name="assessed_value" min="0" step="0.01" required placeholder="500000.00" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-600">Basic Tax Due (₱)</label>
                <input type="number" name="tax_due" min="1" step="0.01" required placeholder="5000.00" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono">
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600">Due Date</label>
              <input type="date" name="due_date" required value="<?= date('Y-m-d') ?>" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
            </div>
            <div class="flex gap-2 pt-2">
              <button type="button" onclick="document.getElementById('newAssessmentModal').classList.add('hidden')" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs">Cancel</button>
              <button type="submit" class="flex-1 py-2 bg-brand-dark hover:opacity-90 text-white font-bold rounded-lg text-xs">Save Assessment</button>
            </div>
          </form>
        </div>
      </div>

      <script>
      function recalcCollectionTotal() {
        const b = parseFloat(document.getElementById('col_basic_amount')?.value || 0) || 0;
        const s = parseFloat(document.getElementById('col_surcharge_amount')?.value || 0) || 0;
        const p = parseFloat(document.getElementById('col_penalty_amount')?.value || 0) || 0;
        const totalEl = document.getElementById('col_total_amount');
        if (totalEl && (b > 0 || s > 0 || p > 0)) {
          totalEl.value = (b + s + p).toFixed(2);
        }
      }

      function applyStatutorySurcharge() {
        const b = parseFloat(document.getElementById('col_basic_amount')?.value || document.getElementById('col_total_amount')?.value || 0) || 0;
        if (b <= 0) return;
        document.getElementById('col_basic_amount').value = b.toFixed(2);
        document.getElementById('col_surcharge_amount').value = (b * 0.25).toFixed(2);
        recalcCollectionTotal();
      }

      function applyMonthlyInterest() {
        const b = parseFloat(document.getElementById('col_basic_amount')?.value || document.getElementById('col_total_amount')?.value || 0) || 0;
        const s = parseFloat(document.getElementById('col_surcharge_amount')?.value || 0) || 0;
        if (b <= 0) return;
        document.getElementById('col_basic_amount').value = b.toFixed(2);
        const curP = parseFloat(document.getElementById('col_penalty_amount')?.value || 0) || 0;
        document.getElementById('col_penalty_amount').value = (curP + (b + s) * 0.02).toFixed(2);
        recalcCollectionTotal();
      }

      function loadAssessmentIntoForm(data) {
        document.getElementById('col_assessment_no').value = data.assessment_no || '';
        document.getElementById('col_payer_name').value = data.taxpayer_name || '';
        const srcSel = document.getElementById('col_revenue_source');
        if (srcSel) {
          for (let i = 0; i < srcSel.options.length; i++) {
            if (srcSel.options[i].value.toLowerCase().includes(data.tax_type.toLowerCase()) ||
                (data.tax_type.toLowerCase().includes('business') && srcSel.options[i].value.toLowerCase().includes('business'))) {
              srcSel.selectedIndex = i;
              break;
            }
          }
        }
        const fundSel = document.getElementById('col_fund_id');
        if (fundSel && data.fund_code) {
          for (let i = 0; i < fundSel.options.length; i++) {
            if (fundSel.options[i].getAttribute('data-code') === data.fund_code) {
              fundSel.selectedIndex = i;
              break;
            }
          }
        }
        document.getElementById('col_basic_amount').value = parseFloat(data.basic || 0).toFixed(2);
        document.getElementById('col_surcharge_amount').value = parseFloat(data.surcharge || 0).toFixed(2);
        document.getElementById('col_penalty_amount').value = parseFloat(data.penalty || 0).toFixed(2);
        document.getElementById('col_total_amount').value = parseFloat(data.total || 0).toFixed(2);
        document.getElementById('linkedAssessmentLabel').textContent = data.assessment_no + ' (' + data.taxpayer_name + ')';
        document.getElementById('linkedAssessmentBanner').classList.remove('hidden');
        document.getElementById('collectionForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      function clearLinkedAssessment() {
        document.getElementById('col_assessment_no').value = '';
        document.getElementById('linkedAssessmentBanner').classList.add('hidden');
      }
      </script>

      <!-- Transaction History (hidden during print) -->
      <div class="no-print">
      <?php
      $module = 'collection';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
      </div>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<style>
  .or-print-only { display: none; }
  @media print {
    /* Hide all page chrome */
    header, aside, nav, footer, form,
    .no-print, .lg\:col-span-2,
    .lg\:col-span-3 > div:first-child { display: none !important; }

    body, html { background: #fff !important; margin: 0; padding: 0; }
    main { padding: 8px !important; margin: 0 !important; display: block !important; }

    /* Make grid a single column */
    .grid { display: block !important; }
    .lg\:col-span-3 {
      width: 100% !important;
      border: none !important;
      box-shadow: none !important;
      padding: 0 !important;
    }

    /* Receipt container */
    #or-print-area {
      width: 420px !important;
      max-width: 100% !important;
      margin: 0 auto !important;
      border: 1.5px solid #94a3b8 !important;
      border-radius: 0 !important;
      background: #fff !important;
      padding: 28px !important;
      box-shadow: none !important;
      position: static !important;
    }

    /* Force all text to be visible with explicit colors */
    #or-print-area * { color: #1e293b !important; visibility: visible !important; }
    #or-print-area .text-slate-400,
    #or-print-area .text-slate-500 { color: #64748b !important; }
    #or-print-area .text-brand-dark,
    #or-print-area .text-brand-medium { color: #1e40af !important; }
    #or-print-area .text-2xl { font-size: 22px !important; }

    /* Show print-only elements like letterhead & signature */
    .or-print-only { display: block !important; }

    .shadow-xs, .shadow-sm { box-shadow: none !important; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>