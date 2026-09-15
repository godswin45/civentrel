<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Treasury Reports';
$activePage = 'treasury-reports';
$errorMsg = null;
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sourceFilter = trim((string) ($_GET['source'] ?? ''));
$voucherStatusFilter = strtolower(trim((string) ($_GET['voucher_status'] ?? '')));

$isValidDate = static fn(string $date): bool => $date === '' || (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
if (!$isValidDate($dateFrom)) $dateFrom = '';
if (!$isValidDate($dateTo)) $dateTo = '';

try {
    $funds = $treasuryService->getFunds();
    $collections = $treasuryService->getAllCollections();
    $vouchers = $treasuryService->getAllVouchers();
    $auditService = $auditService ?? null;

    $collections = array_values(array_filter($collections, static function (array $collection) use ($dateFrom, $dateTo, $sourceFilter): bool {
      $createdAt = substr((string) ($collection['created_at'] ?? ''), 0, 10);
      return ($dateFrom === '' || $createdAt >= $dateFrom)
        && ($dateTo === '' || $createdAt <= $dateTo)
        && ($sourceFilter === '' || ($collection['revenue_source'] ?? '') === $sourceFilter);
    }));
    $vouchers = array_values(array_filter($vouchers, static function (array $voucher) use ($dateFrom, $dateTo, $voucherStatusFilter): bool {
      $createdAt = substr((string) ($voucher['created_at'] ?? ''), 0, 10);
      return ($dateFrom === '' || $createdAt >= $dateFrom)
        && ($dateTo === '' || $createdAt <= $dateTo)
        && ($voucherStatusFilter === '' || strtolower((string) ($voucher['status'] ?? '')) === $voucherStatusFilter);
    }));
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $funds = []; $collections = []; $vouchers = [];
    $auditService = null;
}

$totalRevenue = array_sum(array_column($collections, 'amount'));
$released = array_filter($vouchers, fn($v) => strtolower((string) ($v['status'] ?? '')) === 'disbursed');
$totalDisbursed = array_sum(array_column($released, 'amount'));
$netPosition = array_sum(array_column($funds, 'balance'));

$bySource = [];
foreach ($collections as $c) {
    $bySource[$c['revenue_source']] = ($bySource[$c['revenue_source']] ?? 0) + $c['amount'];
}
arsort($bySource);
$maxSource = $bySource ? max($bySource) : 1;
$byPaymentMode = [];
foreach ($collections as $c) {
    $mode = $c['payment_mode'] ?? 'Cash';
    $byPaymentMode[$mode] = ($byPaymentMode[$mode] ?? 0) + $c['amount'];
}

// 12-month trend for line chart
$monthlyTrend = [];
for ($i = 11; $i >= 0; $i--) {
    $ts    = mktime(0, 0, 0, date('n') - $i, 1);
    $key   = date('Y-m', $ts);
    $label = date('M Y', $ts);
    $monthlyTrend[$key] = ['label' => $label, 'total' => 0];
}
foreach ($collections as $c) {
    $key = substr($c['created_at'], 0, 7);
    if (isset($monthlyTrend[$key])) {
        $monthlyTrend[$key]['total'] += (float) $c['amount'];
    }
}
$rTrendLabels = array_column(array_values($monthlyTrend), 'label');
$rTrendTotals = array_column(array_values($monthlyTrend), 'total');

$reportQuery = http_build_query(array_filter([
  'date_from' => $dateFrom,
  'date_to' => $dateTo,
  'source' => $sourceFilter,
  'voucher_status' => $voucherStatusFilter,
], static fn($value) => $value !== ''));
$downloadUrl = 'download-financial-report.php' . ($reportQuery ? '?' . $reportQuery : '');

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
            <span class="text-brand-dark">Reports</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-chart-pie text-brand-dark"></i>
            Treasury Reports
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Summaries drawn from the live collections and disbursements ledger.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold px-4 py-2.5 rounded-lg text-xs transition shadow-sm">
          <i class="fa-solid fa-print"></i>
          Print
        </button>
        <a href="<?= htmlspecialchars($downloadUrl) ?>" download class="inline-flex items-center justify-center gap-2 bg-brand-dark hover:opacity-90 text-white font-bold px-4 py-2.5 rounded-lg text-xs transition shadow-sm">
          <i class="fa-solid fa-download"></i>
          Download report
        </a>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <form method="get" class="print:hidden bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
          <div>
            <h2 class="text-sm font-extrabold text-slate-800">Report filters</h2>
            <p class="text-[11px] text-slate-400 mt-1">Filter the on-screen report and its download.</p>
          </div>
          <a href="reports.php" class="text-[11px] font-bold text-slate-500 hover:text-brand-dark">Clear filters</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <label class="text-[11px] font-semibold text-slate-500">From<input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
          <label class="text-[11px] font-semibold text-slate-500">To<input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
          <label class="text-[11px] font-semibold text-slate-500">Revenue source<select name="source" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"><option value="">All sources</option><?php foreach (array_unique(array_filter(array_column($collections, 'revenue_source'))) as $source): ?><option value="<?= htmlspecialchars($source) ?>" <?= $sourceFilter === $source ? 'selected' : '' ?>><?= htmlspecialchars($source) ?></option><?php endforeach; ?></select></label>
          <label class="text-[11px] font-semibold text-slate-500">Voucher status<select name="voucher_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"><option value="">All statuses</option><?php foreach (['pending', 'approved', 'disbursed', 'cancelled'] as $status): ?><option value="<?= $status ?>" <?= $voucherStatusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></label>
        </div>
        <button type="submit" class="mt-4 inline-flex items-center gap-2 bg-brand-medium hover:opacity-90 text-white font-bold px-4 py-2.5 rounded-lg text-xs"><i class="fa-solid fa-filter"></i> Apply filters</button>
      </form>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Revenue Collected</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($totalRevenue) ?></h3>
            <p class="text-[11px] text-emerald-600 font-semibold"><?= count($collections) ?> official receipts</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-chart-line text-sm"></i>
          </div>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Disbursed</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($totalDisbursed) ?></h3>
            <p class="text-[11px] text-amber-600 font-semibold"><?= count($released) ?> vouchers released</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-arrow-trend-down text-sm"></i>
          </div>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-brand-dark"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Net Treasury Position</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($netPosition) ?></h3>
            <p class="text-[11px] text-slate-400 font-semibold">Across all funds</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-scale-balanced text-sm"></i>
          </div>
        </div>
      </div>

      <!-- 12-Month Collection Trend Line Chart -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-extrabold text-slate-800">12-Month Collection Trend</h2>
            <p class="text-[11px] text-slate-400 mt-0.5">Monthly revenue collected — <?= $dateFrom || $dateTo ? 'filtered period' : 'last 12 months' ?></p>
          </div>
          <?php if ($dateFrom || $dateTo || $sourceFilter): ?>
          <span class="text-[11px] font-bold text-brand-dark bg-brand-light border border-brand-border px-2 py-1 rounded-full">Filtered view</span>
          <?php endif; ?>
        </div>
        <div class="p-5">
          <?php if (!empty($collections)): ?>
          <div style="position:relative; height:260px;">
            <canvas id="rTrendChart"></canvas>
          </div>
          <?php else: ?>
          <div class="flex flex-col items-center py-12 gap-3 text-slate-400">
            <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
              <i class="fa-solid fa-chart-line text-2xl text-slate-300"></i>
            </div>
            <p class="text-xs font-bold">No collection data for this period</p>
            <p class="text-[11px]">Try adjusting your date range or clear the filters.</p>
            <a href="reports.php" class="inline-flex items-center gap-2 mt-1 bg-brand-dark hover:opacity-90 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
              <i class="fa-solid fa-filter-circle-xmark"></i> Clear Filters
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Revenue by Source + Payment Mode -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Revenue by Source -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs flex flex-col">
          <div class="p-5 border-b border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-800">Revenue by Source</h2>
            <span class="text-[11px] text-slate-400">Filtered period · <?= count($bySource) ?> sources</span>
          </div>
          <div class="p-5 flex-1 flex flex-col items-center gap-4">
            <?php if (!empty($bySource)): ?>
            <div style="position:relative; height:200px; width:200px;">
              <canvas id="rDonutChart"></canvas>
            </div>
            <div class="w-full space-y-2" id="rDonutLegend"></div>
            <?php else: ?>
            <div class="flex flex-col items-center py-8 gap-3 text-slate-400">
              <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                <i class="fa-solid fa-chart-pie text-2xl text-slate-300"></i>
              </div>
              <p class="text-xs font-bold">No revenue source data</p>
              <a href="collection.php" class="inline-flex items-center gap-2 mt-1 bg-brand-dark hover:opacity-90 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
                <i class="fa-solid fa-plus"></i> Record Collection
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Fund Utilization -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
          <div class="p-5 border-b border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-800">Fund Utilization</h2>
            <span class="text-[11px] text-slate-400">Disbursed vs. opening balance</span>
          </div>
          <div class="p-5 space-y-4">
            <?php if (!empty($funds)): ?>
            <?php foreach ($funds as $f):
              $openingBalance = $f['opening_balance'] ?? 0;
              $currentBalance = $f['balance'] ?? 0;
              $used = max(0, $openingBalance - $currentBalance);
              $pct = $openingBalance > 0 ? round($used / $openingBalance * 100) : 0;
            ?>
            <div>
              <div class="flex items-baseline justify-between mb-1">
                <span class="text-xs font-bold text-slate-700 truncate max-w-[65%]"><?= htmlspecialchars($f['name']) ?></span>
                <span class="text-xs font-mono font-bold <?= $pct >= 90 ? 'text-red-500' : ($pct >= 60 ? 'text-amber-500' : 'text-slate-700') ?>"><?= $pct ?>% used</span>
              </div>
              <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-700 <?= $pct >= 90 ? 'bg-red-400' : ($pct >= 60 ? 'bg-amber-400' : 'bg-gradient-to-r from-brand-medium to-brand-dark') ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <div class="flex justify-between mt-1">
                <span class="text-[10px] text-slate-400">Remaining: <?= $treasuryService->formatPeso($currentBalance) ?></span>
                <span class="text-[10px] text-slate-400">Opening: <?= $treasuryService->formatPeso($openingBalance) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="flex flex-col items-center py-8 gap-3 text-slate-400">
              <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                <i class="fa-solid fa-vault text-2xl text-slate-300"></i>
              </div>
              <p class="text-xs font-bold">No funds configured yet</p>
              <p class="text-[11px]">Set up treasury funds to track utilization.</p>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

    </main>

    <!-- Chart.js for reports page -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    (function () {
      const peso = (v) => '\u20B1' + parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
      Chart.defaults.font.family = "'Inter','ui-sans-serif',system-ui,sans-serif";
      Chart.defaults.color = '#94a3b8';

      const palette = ['#176B87','#86B6F6','#0ea5e9','#38bdf8','#7dd3fc','#2563eb','#64748b','#94a3b8'];

      // ── 1. 12-MONTH LINE CHART ────────────────────────────────────
      const rTrendCtx = document.getElementById('rTrendChart');
      if (rTrendCtx) {
        const labels = <?= json_encode($rTrendLabels) ?>;
        const totals = <?= json_encode($rTrendTotals) ?>;

        const lineGrad = rTrendCtx.getContext('2d').createLinearGradient(0, 0, 0, 260);
        lineGrad.addColorStop(0, 'rgba(23,107,135,0.18)');
        lineGrad.addColorStop(1, 'rgba(23,107,135,0)');

        new Chart(rTrendCtx, {
          type: 'line',
          data: {
            labels,
            datasets: [{
              label: 'Collections',
              data: totals,
              borderColor: '#176B87',
              borderWidth: 2.5,
              backgroundColor: lineGrad,
              pointBackgroundColor: '#176B87',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 6,
              fill: true,
              tension: 0.4,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: 'rgba(15,23,42,0.92)',
                titleColor: '#e2e8f0',
                bodyColor: '#94a3b8',
                padding: 12,
                cornerRadius: 10,
                callbacks: { label: (ctx) => '  ' + peso(ctx.parsed.y) }
              }
            },
            scales: {
              x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10, weight: '600' } } },
              y: {
                grid: { color: 'rgba(148,163,184,0.12)' },
                border: { display: false },
                ticks: { font: { size: 10 }, callback: (v) => peso(v) }
              }
            },
            animation: { duration: 900, easing: 'easeOutQuart' }
          }
        });
      }

      // ── 2. REVENUE SOURCE DONUT ───────────────────────────────────
      const rDonutData   = <?= json_encode(array_values($bySource)) ?>;
      const rDonutLabels = <?= json_encode(array_keys($bySource)) ?>;
      const rDonutCtx    = document.getElementById('rDonutChart');

      if (rDonutCtx && rDonutData.length) {
        new Chart(rDonutCtx, {
          type: 'doughnut',
          data: {
            labels: rDonutLabels,
            datasets: [{
              data: rDonutData,
              backgroundColor: palette.slice(0, rDonutData.length),
              borderColor: '#fff',
              borderWidth: 3,
              hoverOffset: 8,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: 'rgba(15,23,42,0.92)',
                titleColor: '#e2e8f0',
                bodyColor: '#94a3b8',
                padding: 12,
                cornerRadius: 10,
                callbacks: { label: (ctx) => '  ' + peso(ctx.parsed) }
              }
            },
            animation: { animateRotate: true, duration: 900, easing: 'easeOutQuart' }
          }
        });

        const legend = document.getElementById('rDonutLegend');
        const total  = rDonutData.reduce((s, v) => s + v, 0);
        if (legend) {
          legend.innerHTML = rDonutLabels.map((lbl, i) => {
            const pct = total > 0 ? ((rDonutData[i] / total) * 100).toFixed(1) : 0;
            return `<div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <span class="inline-block w-2.5 h-2.5 rounded-sm shrink-0" style="background:${palette[i] || '#94a3b8'}"></span>
                <span class="text-[11px] text-slate-600 font-medium truncate" title="${lbl}">${lbl}</span>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="text-[11px] font-mono font-bold text-slate-700">${peso(rDonutData[i])}</span>
                <span class="text-[10px] text-slate-400">${pct}%</span>
              </div>
            </div>`;
          }).join('');
        }
      }
    })();
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<style>
  @media print {
    aside, header, .print\:hidden, nav, footer { display: none !important; }
    main { width: 100% !important; padding: 0 !important; overflow: visible !important; }
    body { background: #fff !important; }
    .shadow-xs, .shadow-sm { box-shadow: none !important; }
  }
</style>