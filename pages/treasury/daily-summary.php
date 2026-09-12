<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle     = "Cashier's Daily Summary";
$activePage    = 'treasury-daily-summary';
$errorMsg      = null;

// Date to summarize — defaults to today
$selectedDate  = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$cashierName   = $headerUser['full_name'] ?? ($_SESSION['email'] ?? 'Cashier');
$lguName       = getenv('LGU_NAME') ?: 'Municipality';
$lguProvince   = getenv('LGU_PROVINCE') ?: '';

try {
    $allCollections = $treasuryService->getAllCollections();
} catch (Exception $e) {
    $errorMsg       = $e->getMessage();
    $allCollections = [];
}

// Filter to selected date only
$dayCollections = array_values(array_filter(
    $allCollections,
    fn($c) => substr((string) ($c['created_at'] ?? ''), 0, 10) === $selectedDate
));

// ── Aggregations ─────────────────────────────────────────────────────────────

// By revenue source
$bySource = [];
foreach ($dayCollections as $c) {
    $src               = $c['revenue_source'] ?? 'Uncategorized';
    $bySource[$src]    = ($bySource[$src] ?? 0) + (float) $c['amount'];
}
arsort($bySource);

// By payment mode
$byMode = [];
foreach ($dayCollections as $c) {
    $mode            = ucfirst(strtolower($c['payment_mode'] ?? 'Cash'));
    $byMode[$mode]   = ($byMode[$mode] ?? 0) + (float) $c['amount'];
}
arsort($byMode);

// By fund
$byFund = [];
foreach ($dayCollections as $c) {
    $fund            = $c['fund_name'] ?? ($c['fund_id'] ?? 'General Fund');
    $byFund[$fund]   = ($byFund[$fund] ?? 0) + (float) $c['amount'];
}

// Grand total and counts
$grandTotal   = array_sum(array_column($dayCollections, 'amount'));
$receiptCount = count($dayCollections);

// OR number range
$orNumbers = array_filter(array_column($dayCollections, 'or_number'));
$orFrom    = $orNumbers ? min($orNumbers) : '—';
$orTo      = $orNumbers ? max($orNumbers) : '—';

// Formatted date display
$displayDate = date('F j, Y', strtotime($selectedDate));
$dayOfWeek   = date('l', strtotime($selectedDate));
$isToday     = $selectedDate === date('Y-m-d');
$isPast      = $selectedDate < date('Y-m-d');

$basePath = '../../';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
    <main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

      <!-- ── Breadcrumb & Actions ─────────────────────────────────────────── -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5 print:hidden">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            <span>Revenue Collection & Treasury</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Daily Summary</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-file-invoice-dollar text-brand-dark"></i>
            Cashier's Daily Collection Summary
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            End-of-day report of all collections for a given date, grouped by revenue source and payment mode.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold px-4 py-2.5 rounded-lg text-xs transition shadow-sm">
            <i class="fa-solid fa-print"></i> Print Report
          </button>
          <?php if (!empty($dayCollections)): ?>
          <a href="daily-summary.php?date=<?= htmlspecialchars($selectedDate) ?>&export=csv"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:opacity-90 text-white font-bold px-4 py-2.5 rounded-lg text-xs transition shadow-sm">
            <i class="fa-solid fa-file-csv"></i> Export CSV
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Date Picker ─────────────────────────────────────────────────── -->
      <form method="get" class="print:hidden bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
        <div class="flex items-end gap-3 flex-wrap">
          <div class="flex-1 min-w-[200px]">
            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Select Date</label>
            <input type="date" name="date" id="date-picker"
              value="<?= htmlspecialchars($selectedDate) ?>"
              max="<?= date('Y-m-d') ?>"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-brand-medium/30 focus:border-brand-medium outline-none transition">
          </div>
          <button type="submit"
            class="inline-flex items-center gap-2 bg-brand-dark hover:opacity-90 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition">
            <i class="fa-solid fa-magnifying-glass"></i> Load Report
          </button>
          <?php if ($selectedDate !== date('Y-m-d')): ?>
          <a href="daily-summary.php"
            class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-brand-dark px-3 py-2.5 rounded-xl border border-slate-200 hover:border-brand-border transition">
            <i class="fa-solid fa-calendar-day"></i> Today
          </a>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start gap-2 print:hidden">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- PRINTABLE REPORT AREA                                             -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <div id="printable-report">

        <!-- Report Header (visible on screen + print) -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6 print:rounded-none print:border-0 print:shadow-none print:p-0">

          <!-- Print-only letterhead -->
          <div class="hidden print:block mb-4 text-center border-b pb-4">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Republic of the Philippines</p>
            <h2 class="text-base font-black uppercase tracking-wide text-slate-900"><?= htmlspecialchars($lguName) ?><?= $lguProvince ? ', ' . htmlspecialchars($lguProvince) : '' ?></h2>
            <p class="text-xs text-slate-600 mt-1">Office of the Municipal Treasurer</p>
          </div>

          <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
              <div class="flex items-center gap-2 print:hidden">
                <div class="h-9 w-9 rounded-xl bg-brand-dark/10 flex items-center justify-center">
                  <i class="fa-solid fa-calendar-check text-brand-dark text-sm"></i>
                </div>
                <div>
                  <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Daily Summary Report</p>
                  <h2 class="text-lg font-black text-slate-900"><?= $dayOfWeek ?>, <?= $displayDate ?></h2>
                </div>
              </div>
              <div class="hidden print:block">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">CASHIER'S DAILY COLLECTION REPORT</p>
                <h2 class="text-base font-black text-slate-900"><?= $dayOfWeek ?>, <?= $displayDate ?></h2>
              </div>
            </div>
            <div class="text-right space-y-1">
              <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Prepared by</p>
              <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($cashierName) ?></p>
              <p class="text-[11px] text-slate-500"><?= $lguName ?> Treasurer's Office</p>
              <?php if ($isToday): ?>
              <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 rounded-full">
                <i class="fa-solid fa-circle text-[6px]"></i> Live — Today's Report
              </span>
              <?php elseif ($isPast): ?>
              <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-slate-50 text-slate-500 border border-slate-200 px-2 py-0.5 rounded-full">
                <i class="fa-solid fa-clock-rotate-left text-[8px]"></i> Historical Report
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- KPI Strip -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-5 border-t border-slate-100">
            <div class="space-y-0.5">
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Collected</p>
              <p class="text-xl font-black text-slate-900"><?= $treasuryService->formatPeso($grandTotal) ?></p>
            </div>
            <div class="space-y-0.5">
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">No. of Receipts</p>
              <p class="text-xl font-black text-slate-900"><?= $receiptCount ?></p>
            </div>
            <div class="space-y-0.5">
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">OR Number From</p>
              <p class="text-xl font-black text-slate-900 font-mono"><?= htmlspecialchars((string) $orFrom) ?></p>
            </div>
            <div class="space-y-0.5">
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">OR Number To</p>
              <p class="text-xl font-black text-slate-900 font-mono"><?= htmlspecialchars((string) $orTo) ?></p>
            </div>
          </div>
        </div>

        <?php if (empty($dayCollections)): ?>
        <!-- Empty State -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-12 flex flex-col items-center gap-4 text-center print:hidden">
          <div class="h-16 w-16 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
            <i class="fa-solid fa-file-circle-xmark text-3xl text-slate-300"></i>
          </div>
          <div>
            <p class="text-sm font-black text-slate-500">No collections on <?= $displayDate ?></p>
            <p class="text-xs text-slate-400 mt-1">No official receipts were recorded on this date.</p>
          </div>
          <div class="flex gap-2 mt-2">
            <a href="collection.php" class="inline-flex items-center gap-2 bg-brand-dark hover:opacity-90 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
              <i class="fa-solid fa-plus"></i> Record Collection
            </a>
            <a href="daily-summary.php" class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold px-4 py-2 rounded-lg transition hover:bg-slate-50">
              <i class="fa-solid fa-calendar-day"></i> View Today
            </a>
          </div>
        </div>

        <?php else: ?>

        <!-- ── Two-column layout: Source table + Mode breakdown ────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 print:grid-cols-3 print:gap-3">

          <!-- Revenue by Source table (2/3 width) -->
          <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden print:rounded-none print:border print:shadow-none">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 class="text-sm font-extrabold text-slate-800">Collections by Revenue Source</h3>
                <p class="text-[11px] text-slate-400 mt-0.5"><?= $receiptCount ?> official receipt<?= $receiptCount !== 1 ? 's' : '' ?> · <?= $displayDate ?></p>
              </div>
              <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-full">
                <?= count($bySource) ?> source<?= count($bySource) !== 1 ? 's' : '' ?>
              </span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead class="bg-slate-50 border-b border-slate-100">
                  <tr>
                    <th class="px-5 py-3 text-left font-black text-slate-500 uppercase tracking-wider text-[10px]">#</th>
                    <th class="px-5 py-3 text-left font-black text-slate-500 uppercase tracking-wider text-[10px]">Revenue Source</th>
                    <th class="px-5 py-3 text-right font-black text-slate-500 uppercase tracking-wider text-[10px]">Receipts</th>
                    <th class="px-5 py-3 text-right font-black text-slate-500 uppercase tracking-wider text-[10px]">Amount</th>
                    <th class="px-5 py-3 text-right font-black text-slate-500 uppercase tracking-wider text-[10px]">Share</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <?php $rowNum = 1; foreach ($bySource as $src => $amt):
                    $srcReceipts = count(array_filter($dayCollections, fn($c) => ($c['revenue_source'] ?? '') === $src));
                    $pct = $grandTotal > 0 ? round($amt / $grandTotal * 100, 1) : 0;
                  ?>
                  <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-5 py-3 text-slate-400 font-mono"><?= $rowNum++ ?></td>
                    <td class="px-5 py-3">
                      <span class="font-semibold text-slate-800"><?= htmlspecialchars($src) ?></span>
                    </td>
                    <td class="px-5 py-3 text-right font-mono text-slate-600"><?= $srcReceipts ?></td>
                    <td class="px-5 py-3 text-right font-mono font-bold text-slate-900"><?= $treasuryService->formatPeso($amt) ?></td>
                    <td class="px-5 py-3 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden hidden print:hidden sm:block">
                          <div class="h-full rounded-full bg-brand-dark" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="text-[11px] font-bold text-slate-500"><?= $pct ?>%</span>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                  <tr>
                    <td colspan="2" class="px-5 py-3 text-xs font-black text-slate-700 uppercase tracking-wider">GRAND TOTAL</td>
                    <td class="px-5 py-3 text-right font-mono font-black text-slate-700"><?= $receiptCount ?></td>
                    <td class="px-5 py-3 text-right font-mono font-black text-slate-900 text-sm"><?= $treasuryService->formatPeso($grandTotal) ?></td>
                    <td class="px-5 py-3 text-right font-bold text-slate-500">100%</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Right column: Payment Mode + Fund breakdown -->
          <div class="space-y-5">

            <!-- Payment Mode -->
            <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden print:rounded-none print:border print:shadow-none">
              <div class="p-5 border-b border-slate-100">
                <h3 class="text-sm font-extrabold text-slate-800">Payment Mode</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Breakdown by tender type</p>
              </div>
              <div class="p-5 space-y-3">
                <?php foreach ($byMode as $mode => $amt):
                  $pct = $grandTotal > 0 ? round($amt / $grandTotal * 100) : 0;
                  $icon = match(strtolower($mode)) {
                      'cash'   => 'fa-money-bill-wave',
                      'online', 'gcash', 'paymaya', 'maya' => 'fa-mobile-screen-button',
                      'check', 'cheque' => 'fa-money-check',
                      default  => 'fa-credit-card'
                  };
                  $color = match(strtolower($mode)) {
                      'cash'   => 'text-emerald-600 bg-emerald-50',
                      'online', 'gcash', 'paymaya', 'maya' => 'text-blue-600 bg-blue-50',
                      'check', 'cheque' => 'text-amber-600 bg-amber-50',
                      default  => 'text-slate-600 bg-slate-50'
                  };
                ?>
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2.5">
                    <span class="h-8 w-8 rounded-lg <?= $color ?> flex items-center justify-center shrink-0">
                      <i class="fa-solid <?= $icon ?> text-xs"></i>
                    </span>
                    <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($mode) ?></span>
                  </div>
                  <div class="text-right">
                    <p class="text-xs font-black text-slate-900"><?= $treasuryService->formatPeso($amt) ?></p>
                    <p class="text-[10px] text-slate-400"><?= $pct ?>%</p>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- By Fund -->
            <?php if (!empty($byFund)): ?>
            <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden print:rounded-none print:border print:shadow-none">
              <div class="p-5 border-b border-slate-100">
                <h3 class="text-sm font-extrabold text-slate-800">By Fund</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Fund allocation breakdown</p>
              </div>
              <div class="p-5 space-y-3">
                <?php foreach ($byFund as $fund => $amt): ?>
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold text-slate-700 truncate max-w-[55%]"><?= htmlspecialchars($fund) ?></span>
                  <span class="text-xs font-black text-slate-900 font-mono"><?= $treasuryService->formatPeso($amt) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- ── Full Transaction List ──────────────────────────────────── -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden print:rounded-none print:border print:shadow-none">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between print:hidden">
            <div>
              <h3 class="text-sm font-extrabold text-slate-800">Full Transaction List</h3>
              <p class="text-[11px] text-slate-400">All <?= $receiptCount ?> receipts for <?= $displayDate ?></p>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                  <th class="px-5 py-3 text-left font-black text-slate-500 uppercase tracking-wider text-[10px]">OR No.</th>
                  <th class="px-5 py-3 text-left font-black text-slate-500 uppercase tracking-wider text-[10px]">Payer Name</th>
                  <th class="px-5 py-3 text-left font-black text-slate-500 uppercase tracking-wider text-[10px]">Revenue Source</th>
                  <th class="px-5 py-3 text-left font-black text-slate-500 uppercase tracking-wider text-[10px]">Mode</th>
                  <th class="px-5 py-3 text-right font-black text-slate-500 uppercase tracking-wider text-[10px]">Amount</th>
                  <th class="px-5 py-3 text-left font-black text-slate-500 uppercase tracking-wider text-[10px] print:hidden">Time</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach ($dayCollections as $c): ?>
                <tr class="hover:bg-slate-50/60 transition print:break-inside-avoid">
                  <td class="px-5 py-3 font-mono font-bold text-brand-dark"><?= htmlspecialchars($c['or_number'] ?? '—') ?></td>
                  <td class="px-5 py-3 font-medium text-slate-800"><?= htmlspecialchars($c['payer_name'] ?? '—') ?></td>
                  <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($c['revenue_source'] ?? '—') ?></td>
                  <td class="px-5 py-3">
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600">
                      <?= htmlspecialchars(ucfirst(strtolower($c['payment_mode'] ?? 'Cash'))) ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-right font-mono font-bold text-slate-900"><?= $treasuryService->formatPeso($c['amount']) ?></td>
                  <td class="px-5 py-3 text-slate-400 font-mono print:hidden">
                    <?= strlen($c['created_at'] ?? '') > 10 ? date('h:i A', strtotime($c['created_at'])) : '—' ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                <tr>
                  <td colspan="4" class="px-5 py-3 text-xs font-black text-slate-700 uppercase tracking-wider">GRAND TOTAL</td>
                  <td class="px-5 py-3 text-right font-mono font-black text-slate-900 text-sm"><?= $treasuryService->formatPeso($grandTotal) ?></td>
                  <td class="print:hidden"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- ── Signature Block (Print) ──────────────────────────────── -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6 print:rounded-none print:border print:shadow-none">
          <div class="grid grid-cols-2 gap-16">
            <div class="space-y-8">
              <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Prepared by</p>
                <div class="border-b-2 border-slate-800 pb-1 w-48 mt-8"></div>
                <p class="text-xs font-bold text-slate-800 mt-1"><?= htmlspecialchars($cashierName) ?></p>
                <p class="text-[11px] text-slate-500">Collecting Officer</p>
              </div>
            </div>
            <div class="space-y-8">
              <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Noted by</p>
                <div class="border-b-2 border-slate-800 pb-1 w-48 mt-8"></div>
                <p class="text-xs font-bold text-slate-800 mt-1">Municipal Treasurer</p>
                <p class="text-[11px] text-slate-500"><?= htmlspecialchars($lguName) ?></p>
              </div>
            </div>
          </div>
          <p class="text-[10px] text-slate-400 mt-6 print:block">
            Generated on <?= date('F j, Y \a\t h:i A') ?> from <?= htmlspecialchars($lguName) ?> Treasury Management System.
            This report covers OR No. <?= htmlspecialchars((string) $orFrom) ?> to <?= htmlspecialchars((string) $orTo) ?>.
          </p>
        </div>

        <?php endif; ?>
      </div><!-- /printable-report -->

    </main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<style>
@media print {
  aside, header, .print\:hidden, nav, footer, form { display: none !important; }
  main { width: 100% !important; padding: 0 !important; overflow: visible !important; margin: 0 !important; }
  body { background: #fff !important; }
  #printable-report { display: block !important; }
  .shadow-xs, .shadow-sm { box-shadow: none !important; }
  .rounded-2xl { border-radius: 0 !important; }
  table { page-break-inside: auto; }
  tr { page-break-inside: avoid; page-break-after: auto; }
  thead { display: table-header-group; }
  tfoot { display: table-footer-group; }
}
</style>
