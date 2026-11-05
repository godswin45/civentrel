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
            'payer_name'       => trim($_POST['business_name'] ?? ''),
            'revenue_source'   => $_POST['tax_type'] ?? 'Business Tax',
            'fund_id'          => $_POST['fund_id'] ?? 'BSF',
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
                'user_id' => $_SESSION['user_id'] ?? ($_SESSION['employee_id'] ?? null),
                'username' => $headerUser['full_name'] ?? 'System',
                'module' => 'business_tax',
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

try {
    $funds = $treasuryService->getFunds();
    $assessments = array_values(array_filter(
        $treasuryService->getAllTaxAssessments(),
        static fn(array $a): bool => stripos((string) ($a['tax_type'] ?? ''), 'Property') === false
    ));
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = []; $assessments = [];
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
            Business permits and regulatory fee collection system with itemized surcharge &amp; penalty computation.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <form method="post" id="bizTaxForm" class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 space-y-4 h-fit">
          <input type="hidden" name="action" value="record_business_tax">
          <input type="hidden" name="assessment_no" id="bt_assessment_no" value="">
          <h2 class="text-sm font-extrabold text-slate-800 pb-1">Business Tax Payment</h2>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Business Name</label>
            <input type="text" name="business_name" id="bt_business_name" required placeholder="e.g. Dela Cruz Sari-Sari Store"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Business Permit / Assessment No.</label>
            <input type="text" name="permit_number" id="bt_permit_number" placeholder="e.g. BP-2026-001234"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Tax Type</label>
            <select name="tax_type" id="bt_tax_type" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
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
                  <option value="<?= htmlspecialchars($f['id']) ?>" <?= strtoupper((string) ($f['code'] ?? '')) === 'BSF' ? 'selected' : '' ?>><?= htmlspecialchars($f['code']) ?></option>
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

          <!-- Itemized Breakdown -->
          <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Itemized Tax Breakdown</span>
              <div class="flex items-center gap-1">
                <button type="button" onclick="btApplySurcharge()" class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 hover:bg-amber-200 transition">+25% Surcharge</button>
                <button type="button" onclick="btApplyInterest()" class="text-[10px] font-bold px-2 py-0.5 rounded bg-rose-100 text-rose-800 hover:bg-rose-200 transition">+2% Interest</button>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-[10px] font-bold text-slate-500 block mb-1">Basic Tax / Fee</label>
                <input type="number" name="basic_amount" id="bt_basic_amount" min="0" step="0.01" placeholder="0.00" oninput="btRecalcTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-gray-800">
              </div>
              <div>
                <label class="text-[10px] font-bold text-amber-600 block mb-1">Surcharge (₱)</label>
                <input type="number" name="surcharge_amount" id="bt_surcharge_amount" min="0" step="0.01" value="0.00" oninput="btRecalcTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-amber-700">
              </div>
              <div>
                <label class="text-[10px] font-bold text-rose-600 block mb-1">Penalty / Interest</label>
                <input type="number" name="penalty_amount" id="bt_penalty_amount" min="0" step="0.01" value="0.00" oninput="btRecalcTotal()"
                  class="w-full px-2.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-rose-700">
              </div>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Total Amount Due (₱)</label>
            <div class="relative flex items-center">
              <span class="absolute left-4 text-gray-400 text-sm font-bold">₱</span>
              <input type="number" name="amount" id="bt_total_amount" min="1" step="0.01" required placeholder="0.00"
                class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono font-bold">
            </div>
          </div>

          <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
            Record Business Tax Payment
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
                  <div class="text-xs uppercase tracking-wider text-slate-400">Business Tax &amp; Regulatory Fees</div>
                </div>
                <div class="font-mono text-sm font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['or_number'] ?? '—') ?></div>
              </div>
              <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Received from</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['payer_name']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Nature of collection</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars($lastReceipt['revenue_source']) ?></dd></div>
                <?php if (!empty($lastReceipt['assessment_no'])): ?>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Assessment / Permit Ref</dt><dd class="font-mono font-bold text-brand-dark"><?= htmlspecialchars($lastReceipt['assessment_no']) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between"><dt class="text-slate-500 font-semibold">Payment mode</dt><dd class="font-bold text-slate-800"><?= htmlspecialchars(ucfirst($lastReceipt['payment_mode'])) ?></dd></div>
                <div class="border-t border-dashed border-slate-200 pt-2.5 mt-2.5 space-y-1.5 text-xs">
                  <div class="flex justify-between"><dt class="text-slate-500">Basic Tax / Regulatory Fee</dt><dd class="font-mono font-semibold text-slate-700"><?= $treasuryService->formatPeso($rBasic) ?></dd></div>
                  <div class="flex justify-between"><dt class="text-slate-500">Surcharge (Late Renewal)</dt><dd class="font-mono font-semibold text-amber-700"><?= $treasuryService->formatPeso($rSurcharge) ?></dd></div>
                  <div class="flex justify-between"><dt class="text-slate-500">Penalty / Delinquency Interest</dt><dd class="font-mono font-semibold text-rose-700"><?= $treasuryService->formatPeso($rPenalty) ?></dd></div>
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
              <p class="text-xs">No receipt issued yet. Record a business tax payment to generate one.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Business Tax Assessments Ledger -->
      <div class="no-print bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Business Tax Assessments &amp; Delinquency Schedule</h2>
          <p class="text-[11px] text-slate-400 mt-0.5">Select an unpaid or overdue business assessment to auto-fill basic tax, 25% surcharge, and 2%/mo penalty</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50 border-b border-slate-100">
                <th class="px-4 py-3 font-bold">Assessment No.</th>
                <th class="px-4 py-3 font-bold">Business Name</th>
                <th class="px-4 py-3 font-bold">Tax Type</th>
                <th class="px-4 py-3 font-bold text-right">Gross / Assessed</th>
                <th class="px-4 py-3 font-bold text-right">Basic Tax</th>
                <th class="px-4 py-3 font-bold text-right">Surcharge + Penalty</th>
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
              ?>
              <tr class="hover:bg-slate-50/70">
                <td class="px-4 py-3 font-mono font-bold text-brand-dark"><?= htmlspecialchars($as['assessment_no']) ?></td>
                <td class="px-4 py-3 font-semibold text-slate-700"><?= htmlspecialchars($as['taxpayer_name']) ?></td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($as['tax_type']) ?></td>
                <td class="px-4 py-3 font-mono text-right text-slate-500"><?= $treasuryService->formatPeso($as['assessed_value'] ?? 0) ?></td>
                <td class="px-4 py-3 font-mono font-bold text-right text-slate-800"><?= $treasuryService->formatPeso($basicDue) ?></td>
                <td class="px-4 py-3 font-mono text-right <?= $isOverdue ? 'text-rose-600 font-bold' : 'text-slate-400' ?>"><?= $treasuryService->formatPeso($surcharge + $interest) ?></td>
                <td class="px-4 py-3 font-mono font-black text-right text-slate-900"><?= $treasuryService->formatPeso($totalDue) ?></td>
                <td class="px-4 py-3 font-mono text-slate-500"><?= htmlspecialchars($as['due_date']) ?></td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= $st === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($isOverdue ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') ?>">
                    <?= $st === 'paid' ? 'PAID' : ($isOverdue ? 'OVERDUE' : strtoupper($st)) ?>
                  </span>
                </td>
                <td class="px-4 py-3">
                  <?php if ($st !== 'paid'): ?>
                  <button type="button" onclick='btLoadAssessment(<?= json_encode([
                      "assessment_no" => $as["assessment_no"],
                      "business_name" => $as["taxpayer_name"],
                      "tax_type"      => $as["tax_type"],
                      "basic"         => $basicDue,
                      "surcharge"     => $surcharge,
                      "penalty"       => $interest,
                      "total"         => $totalDue,
                  ]) ?>)' class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-2.5 py-1.5 rounded-lg text-[10px]">
                    <i class="fa-solid fa-cash-register mr-1"></i> Collect
                  </button>
                  <?php else: ?>
                  <span class="text-[10px] font-bold text-emerald-600"><i class="fa-solid fa-check-double"></i> Paid</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <script>
      function btRecalcTotal() {
        const b = parseFloat(document.getElementById('bt_basic_amount')?.value || 0) || 0;
        const s = parseFloat(document.getElementById('bt_surcharge_amount')?.value || 0) || 0;
        const p = parseFloat(document.getElementById('bt_penalty_amount')?.value || 0) || 0;
        const totalEl = document.getElementById('bt_total_amount');
        if (totalEl && (b > 0 || s > 0 || p > 0)) {
          totalEl.value = (b + s + p).toFixed(2);
        }
      }
      function btApplySurcharge() {
        const b = parseFloat(document.getElementById('bt_basic_amount')?.value || document.getElementById('bt_total_amount')?.value || 0) || 0;
        if (b <= 0) return;
        document.getElementById('bt_basic_amount').value = b.toFixed(2);
        document.getElementById('bt_surcharge_amount').value = (b * 0.25).toFixed(2);
        btRecalcTotal();
      }
      function btApplyInterest() {
        const b = parseFloat(document.getElementById('bt_basic_amount')?.value || document.getElementById('bt_total_amount')?.value || 0) || 0;
        const s = parseFloat(document.getElementById('bt_surcharge_amount')?.value || 0) || 0;
        if (b <= 0) return;
        document.getElementById('bt_basic_amount').value = b.toFixed(2);
        const curP = parseFloat(document.getElementById('bt_penalty_amount')?.value || 0) || 0;
        document.getElementById('bt_penalty_amount').value = (curP + (b + s) * 0.02).toFixed(2);
        btRecalcTotal();
      }
      function btLoadAssessment(data) {
        document.getElementById('bt_assessment_no').value = data.assessment_no || '';
        document.getElementById('bt_permit_number').value = data.assessment_no || '';
        document.getElementById('bt_business_name').value = data.business_name || '';
        const sel = document.getElementById('bt_tax_type');
        if (sel && data.tax_type) {
          for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value.toLowerCase() === data.tax_type.toLowerCase()) {
              sel.selectedIndex = i;
              break;
            }
          }
        }
        document.getElementById('bt_basic_amount').value = parseFloat(data.basic || 0).toFixed(2);
        document.getElementById('bt_surcharge_amount').value = parseFloat(data.surcharge || 0).toFixed(2);
        document.getElementById('bt_penalty_amount').value = parseFloat(data.penalty || 0).toFixed(2);
        document.getElementById('bt_total_amount').value = parseFloat(data.total || 0).toFixed(2);
        document.getElementById('bizTaxForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      </script>
      <!-- Transaction History (hidden during print) -->
      <div class="no-print">
      <?php 
      $module = 'business_tax';
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