<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Market Stall Rental';
$activePage = 'treasury-market-stall';
$errorMsg = null;
$lastReceipt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_rental') {
    try {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception('Amount must be greater than zero.');

        $lastReceipt = $treasuryService->recordCollection([
            'payer_name'       => trim($_POST['stall_holder'] ?? ''),
            'revenue_source'   => 'Market Stall Rental',
            'fund_id'          => $_POST['fund_id'] ?? 'MSF',
            'basic_amount'     => (float) ($_POST['basic_amount'] ?? $amount),
            'surcharge_amount' => (float) ($_POST['surcharge_amount'] ?? 0),
            'penalty_amount'   => (float) ($_POST['penalty_amount'] ?? 0),
            'amount'           => $amount,
            'stall_number'     => trim($_POST['stall_number'] ?? ''),
            'payment_mode'     => $_POST['payment_mode'] ?? 'cash',
            'collected_by'     => $headerUser['full_name'] ?? null,
        ]);
        
        // Log the transaction
        if ($auditService) {
            $auditService->logTransaction([
                'user_id' => $_SESSION['user_id'] ?? ($_SESSION['employee_id'] ?? null),
                'username' => $headerUser['full_name'] ?? 'System',
                'module' => 'market_stall',
                'action' => 'collect',
                'table_name' => 'tr_collections',
                'record_id' => $lastReceipt['id'] ?? null,
                'new_values' => json_encode($lastReceipt)
            ]);
        }

        $_SESSION['flash_receipt'] = $lastReceipt;
        header('Location: market-stall.php?issued=1');
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

if (isset($_GET['issued']) && !empty($_SESSION['flash_receipt'])) {
    $lastReceipt = $_SESSION['flash_receipt'];
    unset($_SESSION['flash_receipt']);
}

try {
    $funds = $treasuryService->getFunds();
    $marketStalls = $treasuryService->getAllMarketStalls();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = []; $marketStalls = [];
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
            <span>Revenue Collection & Treasury</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Market Stall Rental</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-store text-brand-dark"></i>
            Market Stall Rental Billing &amp; Occupancy Ledger
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Public market stall occupancy monitoring, monthly rental billing, and late surcharge collection (cutoff every 20th of the month).
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <form method="post" id="stallRentalForm" class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 space-y-4 h-fit">
          <input type="hidden" name="action" value="record_rental">
          <h2 class="text-sm font-extrabold text-slate-800 pb-1">Stall Rental Payment</h2>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Stall Holder Name</label>
            <input type="text" name="stall_holder" id="ms_stall_holder" required placeholder="e.g. Dela Cruz, Marites"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Stall Number</label>
            <input type="text" name="stall_number" id="ms_stall_number" placeholder="e.g. A-02"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Market Location / Section</label>
            <select name="market_location" id="ms_market_location" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              <option value="public_market">Public Market</option>
              <option value="private_market">Private Market</option>
              <option value="night_market">Night Market</option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Rental Period</label>
            <select name="rental_period" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annually">Annually</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Fund</label>
              <select name="fund_id" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <?php foreach ($funds as $f): ?>
                  <option value="<?= htmlspecialchars($f['id']) ?>" <?= strtoupper((string) ($f['code'] ?? '')) === 'MSF' ? 'selected' : '' ?>><?= htmlspecialchars($f['code']) ?></option>
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

          <!-- Itemized Rental Breakdown -->
          <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Rental &amp; Late Surcharge</span>
              <button type="button" onclick="msApplyCutoffSurcharge()" class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 hover:bg-amber-200 transition" title="Add 25% surcharge for payment after the 20th cutoff">+25% Late Surcharge</button>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-[10px] font-bold text-slate-500 block mb-1">Monthly Rental</label>
                <input type="number" name="basic_amount" id="ms_basic_amount" min="0" step="0.01" placeholder="0.00" oninput="msRecalcTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-gray-800">
              </div>
              <div>
                <label class="text-[10px] font-bold text-amber-600 block mb-1">Surcharge (25%)</label>
                <input type="number" name="surcharge_amount" id="ms_surcharge_amount" min="0" step="0.01" value="0.00" oninput="msRecalcTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-amber-700">
              </div>
              <div>
                <label class="text-[10px] font-bold text-rose-600 block mb-1">Arrears / Interest</label>
                <input type="number" name="penalty_amount" id="ms_penalty_amount" min="0" step="0.01" value="0.00" oninput="msRecalcTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-rose-700">
              </div>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Total Amount Due (₱)</label>
            <div class="relative flex items-center">
              <span class="absolute left-4 text-gray-400 text-sm font-bold">₱</span>
              <input type="number" name="amount" id="ms_total_amount" min="1" step="0.01" required placeholder="0.00"
                class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono font-bold">
            </div>
          </div>

          <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
            Record Stall Rental Payment
          </button>
        </form>

        <div class="lg:col-span-3 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h2 class="text-sm font-extrabold text-slate-800">Official Receipt</h2>
            <?php if ($lastReceipt): ?>
            <button type="button" onclick="window.print()"
              class="no-print inline-flex items-center gap-2 bg-brand-dark hover:opacity-90 text-white font-bold px-3 py-2 rounded-lg text-xs transition shadow-sm">
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
            <div id="or-print-area" class="border-2 border-brand-border rounded-2xl p-8 bg-gradient-to-b from-brand-light/80 to-white relative w-full">

              <!-- Print letterhead — hidden on screen -->
              <div class="or-print-only text-center mb-4 pb-3 border-b-2 border-dashed border-brand-border">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Republic of the Philippines</p>
                <p class="text-sm font-black uppercase text-slate-900"><?= htmlspecialchars(getenv('LGU_NAME') ?: 'City of Caloocan') ?></p>
                <p class="text-xs text-slate-500">Office of the City Treasurer</p>
              </div>

              <div class="flex justify-between items-start border-b-2 border-dashed border-brand-border pb-4 mb-4">
                <div>
                  <div class="text-lg font-black text-brand-dark">Official Receipt</div>
                  <div class="text-xs uppercase tracking-wider text-slate-400">Market Stall Rental</div>
                </div>
                <div class="font-mono text-sm font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['or_number'] ?? '—') ?></div>
              </div>
              <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Received from</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['payer_name']) ?></dd></div>
                <?php if (!empty($lastReceipt['stall_number'])): ?>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Stall Number</dt><dd class="font-mono font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['stall_number']) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Nature of collection</dt><dd class="font-bold text-slate-800">Market Stall Rental</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Payment mode</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars(ucfirst($lastReceipt['payment_mode'])) ?></dd></div>
                <div class="border-t border-dashed border-slate-200 pt-2.5 mt-2.5 space-y-1.5 text-xs">
                  <div class="flex justify-between"><dt class="text-slate-500">Base Stall Rental Fee</dt><dd class="font-mono font-semibold text-slate-700"><?= $treasuryService->formatPeso($rBasic) ?></dd></div>
                  <div class="flex justify-between"><dt class="text-slate-500">Late Payment Surcharge</dt><dd class="font-mono font-semibold text-amber-700"><?= $treasuryService->formatPeso($rSurcharge) ?></dd></div>
                  <div class="flex justify-between"><dt class="text-slate-500">Delinquency Interest / Arrears</dt><dd class="font-mono font-semibold text-rose-700"><?= $treasuryService->formatPeso($rPenalty) ?></dd></div>
                </div>
                <div class="flex justify-between border-t-2 border-slate-200 pt-3 mt-3"><dt class="text-slate-500 font-semibold">Total Amount Paid</dt><dd class="font-black text-brand-dark text-2xl"><?= $treasuryService->formatPeso($lastReceipt['amount']) ?></dd></div>
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
              <p class="text-xs">No receipt issued yet. Record a stall rental payment to generate one.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Public Market Stall Occupancy & Delinquency Ledger -->
      <div class="no-print bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-extrabold text-slate-800">Public Market Stall Occupancy &amp; Delinquency Ledger</h2>
            <p class="text-[11px] text-slate-400 mt-0.5">Tracks occupied vs. vacant stalls, monthly rental rates, last payment dates, and 25% surcharge after the 20th cutoff</p>
          </div>
          <span class="text-[11px] font-bold text-slate-500"><?= count($marketStalls) ?> stalls registered</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50 border-b border-slate-100">
                <th class="px-4 py-3 font-bold">Stall #</th>
                <th class="px-4 py-3 font-bold">Market Section</th>
                <th class="px-4 py-3 font-bold">Vendor / Stallholder</th>
                <th class="px-4 py-3 font-bold">Occupancy</th>
                <th class="px-4 py-3 font-bold text-right">Monthly Fee</th>
                <th class="px-4 py-3 font-bold">Last Payment Date</th>
                <th class="px-4 py-3 font-bold">Billing Status</th>
                <th class="px-4 py-3 font-bold text-right">Total Due (w/ Cutoff Surcharge)</th>
                <th class="px-4 py-3 font-bold">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php
              $currentYm = date('Y-m');
              $pastCutoff = (int) date('j') > 20;
              foreach ($marketStalls as $st):
                $occ = strtolower((string) ($st['status'] ?? 'vacant'));
                $fee = (float) ($st['monthly_fee'] ?? 0);
                $lastPay = $st['last_payment_date'] ?? null;
                $paidThisMonth = $lastPay && substr($lastPay, 0, 7) === $currentYm;
                $daysSincePay = $lastPay ? (int) floor((time() - strtotime($lastPay)) / 86400) : 999;
                $isDelinquent = ($occ === 'occupied') && !$paidThisMonth && ($daysSincePay > 30 || $pastCutoff);
                $surcharge = $isDelinquent ? round($fee * 0.25, 2) : 0.0;
                $interest  = ($isDelinquent && $daysSincePay > 45) ? round(($fee + $surcharge) * 0.02, 2) : 0.0;
                $totalDue  = $occ === 'occupied' ? ($paidThisMonth ? 0.0 : ($fee + $surcharge + $interest)) : 0.0;
              ?>
              <tr class="hover:bg-slate-50/70 transition">
                <td class="px-4 py-3 font-mono font-bold text-brand-dark"><?= htmlspecialchars($st['stall_number']) ?></td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($st['section']) ?></td>
                <td class="px-4 py-3 font-semibold text-slate-800"><?= htmlspecialchars($st['vendor_name'] ?: '— Vacant —') ?></td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $occ === 'occupied' ? 'bg-blue-100 text-blue-700' : ($occ === 'vacant' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-700') ?>">
                    <?= htmlspecialchars($occ) ?>
                  </span>
                </td>
                <td class="px-4 py-3 font-mono font-bold text-right text-slate-700"><?= $treasuryService->formatPeso($fee) ?></td>
                <td class="px-4 py-3 font-mono text-slate-500"><?= htmlspecialchars($lastPay ?: 'Never') ?></td>
                <td class="px-4 py-3">
                  <?php if ($occ !== 'occupied'): ?>
                    <span class="text-[10px] font-bold text-slate-400">N/A</span>
                  <?php elseif ($paidThisMonth): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700">PAID THIS MONTH</span>
                  <?php elseif ($isDelinquent): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700">DELINQUENT (+25%)</span>
                  <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-700">DUE THIS MONTH</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3 font-mono font-black text-right <?= $isDelinquent ? 'text-rose-600' : 'text-slate-800' ?>">
                  <?= $treasuryService->formatPeso($totalDue) ?>
                </td>
                <td class="px-4 py-3">
                  <?php if ($occ === 'occupied' && !$paidThisMonth): ?>
                  <button type="button" onclick='msLoadStall(<?= json_encode([
                      "stall_number" => $st["stall_number"],
                      "vendor_name"  => $st["vendor_name"],
                      "basic"        => $fee,
                      "surcharge"    => $surcharge,
                      "penalty"      => $interest,
                      "total"        => $totalDue,
                  ]) ?>)' class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-2.5 py-1.5 rounded-lg text-[10px] transition">
                    <i class="fa-solid fa-cash-register mr-1"></i> Collect Rental
                  </button>
                  <?php elseif ($occ === 'occupied'): ?>
                  <span class="text-[10px] font-bold text-emerald-600"><i class="fa-solid fa-check-double"></i> Current</span>
                  <?php else: ?>
                  <span class="text-[10px] text-slate-400">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <script>
      function msRecalcTotal() {
        const b = parseFloat(document.getElementById('ms_basic_amount')?.value || 0) || 0;
        const s = parseFloat(document.getElementById('ms_surcharge_amount')?.value || 0) || 0;
        const p = parseFloat(document.getElementById('ms_penalty_amount')?.value || 0) || 0;
        const totalEl = document.getElementById('ms_total_amount');
        if (totalEl && (b > 0 || s > 0 || p > 0)) {
          totalEl.value = (b + s + p).toFixed(2);
        }
      }
      function msApplyCutoffSurcharge() {
        const b = parseFloat(document.getElementById('ms_basic_amount')?.value || document.getElementById('ms_total_amount')?.value || 0) || 0;
        if (b <= 0) return;
        document.getElementById('ms_basic_amount').value = b.toFixed(2);
        document.getElementById('ms_surcharge_amount').value = (b * 0.25).toFixed(2);
        msRecalcTotal();
      }
      function msLoadStall(data) {
        document.getElementById('ms_stall_number').value = data.stall_number || '';
        document.getElementById('ms_stall_holder').value = data.vendor_name || '';
        document.getElementById('ms_basic_amount').value = parseFloat(data.basic || 0).toFixed(2);
        document.getElementById('ms_surcharge_amount').value = parseFloat(data.surcharge || 0).toFixed(2);
        document.getElementById('ms_penalty_amount').value = parseFloat(data.penalty || 0).toFixed(2);
        document.getElementById('ms_total_amount').value = parseFloat(data.total || 0).toFixed(2);
        document.getElementById('stallRentalForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      </script>
      <div class="no-print">
      <?php 
      $module = 'market_stall';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
      </div>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<style>
  .or-print-only { display: none; }
  @media print {
    header, aside, nav, footer, form,
    .no-print, .lg\:col-span-2,
    .lg\:col-span-3 > div:first-child { display: none !important; }
    body, html { background: #fff !important; margin: 0; padding: 0; }
    main { padding: 8px !important; margin: 0 !important; display: block !important; }
    .grid { display: block !important; }
    .lg\:col-span-3 { width: 100% !important; border: none !important; box-shadow: none !important; padding: 0 !important; }
    #or-print-area {
      width: 420px !important; max-width: 100% !important;
      margin: 0 auto !important;
      border: 1.5px solid #94a3b8 !important;
      border-radius: 0 !important;
      background: #fff !important;
      padding: 28px !important;
      box-shadow: none !important;
      position: static !important;
    }
    #or-print-area * { color: #1e293b !important; }
    #or-print-area .text-slate-400,
    #or-print-area .text-slate-500 { color: #64748b !important; }
    #or-print-area .text-brand-dark { color: #1e40af !important; }
    .or-print-only { display: block !important; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>