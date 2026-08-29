<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Treasury Reports';
$activePage = 'treasury-reports';
$errorMsg = null;

try {
    $funds = $treasuryService->getFunds();
    $collections = $treasuryService->getAllCollections();
    $vouchers = $treasuryService->getAllVouchers();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $funds = []; $collections = []; $vouchers = [];
    $auditService = null;
}

$totalRevenue = array_sum(array_column($collections, 'amount'));
$released = array_filter($vouchers, fn($v) => $v['status'] === 'Released');
$totalDisbursed = array_sum(array_column($released, 'amount'));
$netPosition = array_sum(array_column($funds, 'balance'));

$bySource = [];
foreach ($collections as $c) {
    $bySource[$c['revenue_source']] = ($bySource[$c['revenue_source']] ?? 0) + $c['amount'];
}
arsort($bySource);
$maxSource = $bySource ? max($bySource) : 1;

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
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

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

      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Revenue by Source</h2>
          <span class="text-[11px] text-slate-400">All time</span>
        </div>
        <div class="p-5 space-y-4">
          <?php foreach ($bySource as $src => $amt): $pct = round($amt / $maxSource * 100); ?>
          <div class="grid grid-cols-[160px_1fr_110px] items-center gap-3">
            <span class="text-xs text-slate-600 font-medium truncate"><?= htmlspecialchars($src) ?></span>
            <div class="h-3 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full bg-brand-dark rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="text-xs font-mono font-bold text-slate-700 text-right"><?= $treasuryService->formatPeso($amt) ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (empty($bySource)): ?>
            <p class="text-xs text-slate-400">No data yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Fund Utilization</h2>
          <span class="text-[11px] text-slate-400">Disbursed vs. opening balance</span>
        </div>
        <div class="p-5 space-y-4">
          <?php foreach ($funds as $f):
            $openingBalance = $f['opening_balance'] ?? 0;
            $currentBalance = $f['balance'] ?? 0;
            $used = max(0, $openingBalance - $currentBalance);
            $pct = $openingBalance > 0 ? round($used / $openingBalance * 100) : 0;
          ?>
          <div class="grid grid-cols-[220px_1fr_110px] items-center gap-3">
            <span class="text-xs text-slate-600 font-medium truncate"><?= htmlspecialchars($f['name']) ?></span>
            <div class="h-3 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full bg-brand-medium rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="text-xs font-mono font-bold text-slate-700 text-right"><?= $pct ?>% used</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Transaction History -->
      <?php 
      $module = 'reports';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>