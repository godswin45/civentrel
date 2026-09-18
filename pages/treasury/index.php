<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Treasury Dashboard';
$activePage = 'treasury-index';
$errorMsg = null;

try {
    $funds = $treasuryService->getFunds();
    $recent = $treasuryService->getRecentCollections(8);
    $allCollections = $treasuryService->getAllCollections();
    $vouchers = $treasuryService->getAllVouchers();
    $budgetRequests = $treasuryService->getAllBudgetRequests();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $funds = []; $recent = []; $allCollections = []; $vouchers = []; $budgetRequests = []; $auditService = null;
}

$totalBalance = array_sum(array_column($funds, 'balance'));
$today = date('Y-m-d');
$todayCollections = array_filter($allCollections, fn($c) => substr($c['created_at'], 0, 10) === $today);
$todayTotal = array_sum(array_column($todayCollections, 'amount'));
$pendingVouchers = array_filter($vouchers, fn($v) => strtolower($v['status']) === 'pending');
$pendingTotal = array_sum(array_column($pendingVouchers, 'amount'));
$pendingBudgetRequests = array_filter($budgetRequests, fn($b) => strtolower($b['status']) === 'pending');
$pendingBudgetTotal = array_sum(array_column($pendingBudgetRequests, 'requested_amount'));

// Current month totals
$thisMonth = date('Y-m');
$monthlyCollections = array_filter($allCollections, fn($c) => substr($c['created_at'], 0, 7) === $thisMonth);
$monthlyTotal = array_sum(array_column($monthlyCollections, 'amount'));

// Month-over-month % change badge
$prevMonth = date('Y-m', mktime(0, 0, 0, date('n') - 1, 1));
$prevMonthCollections = array_filter($allCollections, fn($c) => substr($c['created_at'], 0, 7) === $prevMonth);
$prevMonthTotal = array_sum(array_column($prevMonthCollections, 'amount'));
$monthlyChangePct = $prevMonthTotal > 0
    ? round((($monthlyTotal - $prevMonthTotal) / $prevMonthTotal) * 100, 1)
    : null;

// Yesterday comparison for Today's Collections badge
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterdayCollections = array_filter($allCollections, fn($c) => substr($c['created_at'], 0, 10) === $yesterday);
$yesterdayTotal = array_sum(array_column($yesterdayCollections, 'amount'));
$todayChangePct = $yesterdayTotal > 0
    ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1)
    : null;

// Calculate collection by revenue source
$revenueSources = [];
foreach ($allCollections as $collection) {
    $source = $collection['revenue_source'];
    if (!isset($revenueSources[$source])) {
        $revenueSources[$source] = 0;
    }
    $revenueSources[$source] += $collection['amount'];
}
arsort($revenueSources);

// Build 12-month trend data (current month + 11 previous)
$monthlyTrend = [];
for ($i = 11; $i >= 0; $i--) {
    $ts    = mktime(0, 0, 0, date('n') - $i, 1);
    $key   = date('Y-m', $ts);               // e.g. "2026-09"
    $label = date('M Y', $ts);               // e.g. "Sep 2026"
    $monthlyTrend[$key] = ['label' => $label, 'total' => 0];
}
foreach ($allCollections as $c) {
    $key = substr($c['created_at'], 0, 7);
    if (isset($monthlyTrend[$key])) {
        $monthlyTrend[$key]['total'] += (float) $c['amount'];
    }
}
$trendLabels = array_column(array_values($monthlyTrend), 'label');   // JS array
$trendTotals = array_column(array_values($monthlyTrend), 'total');    // JS array

// ── AI Predictive Analytics (Linear Regression) ──────────────────
$n = count($trendTotals);
$sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
for ($i = 0; $i < $n; $i++) {
    $x = $i;
    $y = $trendTotals[$i];
    $sumX += $x;
    $sumY += $y;
    $sumXY += ($x * $y);
    $sumXX += ($x * $x);
}
if ($n * $sumXX - $sumX * $sumX == 0) {
    $m = 0; // Avoid division by zero if all x are same
} else {
    $m = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
}
$b = ($sumY - $m * $sumX) / $n;
$predictedNextMonth = $m * 12 + $b; // Predict next month (index 12)
if ($predictedNextMonth < 0) $predictedNextMonth = 0;

// ── Alert computations ───────────────────────────────────────────
$alerts = [];

// 1. Low fund balance (< 15% of opening)
foreach ($funds as $f) {
    $opening = (float)($f['opening_balance'] ?? 0);
    $balance = (float)($f['balance'] ?? 0);
    if ($opening > 0 && $balance / $opening < 0.15) {
        $pct = round($balance / $opening * 100);
        $alerts[] = ['level'=>'danger','icon'=>'fa-triangle-exclamation',
            'msg'=>'<strong>'.htmlspecialchars($f['name']??'Fund').'</strong> is critically low — only <strong>'.$pct.'%</strong> ('.$treasuryService->formatPeso($balance).') remaining.',
            'link'=>null];
    }
}

// 2. Stale vouchers pending > 3 days
$staleVouchers = array_filter($pendingVouchers, fn($v) => (time() - strtotime($v['created_at'] ?? 'now')) > 259200);
if (count($staleVouchers)) {
    $n = count($staleVouchers);
    $alerts[] = ['level'=>'warning','icon'=>'fa-clock-rotate-left',
        'msg'=>'<strong>'.$n.' voucher'.($n>1?'s have':' has').'</strong> been pending over 3 days.',
        'link'=>['href'=>'disbursement.php','label'=>'Review →']];
}

// 3. Pending budget requests
if (count($pendingBudgetRequests)) {
    $n = count($pendingBudgetRequests);
    $alerts[] = ['level'=>'info','icon'=>'fa-file-circle-question',
        'msg'=>'<strong>'.$n.' budget request'.($n>1?'s':'').'</strong> awaiting approval — '.$treasuryService->formatPeso($pendingBudgetTotal).' total.',
        'link'=>['href'=>'budget-approvals.php','label'=>'Review →']];
}

// ── AI Anomaly Detection ──────────────────────────────────────────
$releasedVouchers = array_filter($vouchers, fn($v) => strtolower($v['status'] ?? '') === 'disbursed');
$anomalyAlerts = [];
if (count($releasedVouchers) >= 3) {
    $voucherAmounts = array_column(array_values($releasedVouchers), 'amount');
    $avgDisbursement = array_sum($voucherAmounts) / count($voucherAmounts);
    $anomalyThreshold = $avgDisbursement * 3;
    foreach ($pendingVouchers as $v) {
        $vAmount = (float)($v['amount'] ?? 0);
        if ($vAmount > $anomalyThreshold && $anomalyThreshold > 0) {
            $payeeName = htmlspecialchars($v['payee'] ?? 'Unknown Payee');
            $anomalyAlerts[] = ['payee'=>$payeeName,'amount'=>$vAmount,'avg'=>$avgDisbursement];
            $alerts[] = [
                'level' => 'danger',
                'icon'  => 'fa-robot',
                'msg'   => '🤖 <strong>AI Anomaly Detected!</strong> Voucher for <strong>'.$payeeName.'</strong> ('.$treasuryService->formatPeso($vAmount).') is <strong>'.round($vAmount/$avgDisbursement,1).'x</strong> higher than the historical average ('.$treasuryService->formatPeso($avgDisbursement).'). Manual review recommended.',
                'link'  => ['href'=>'disbursement.php','label'=>'Investigate →'],
            ];
        }
    }
    // Send email alert once per day if anomalies found
    if (!empty($anomalyAlerts) && !isset($_SESSION['anomaly_email_sent_'.date('Y-m-d')])) {
        try {
            require_once __DIR__ . '/../../config/mailer.php';
            $rows = '';
            foreach ($anomalyAlerts as $a) {
                $risk = round($a['amount'] / $a['avg'], 1);
                $rows .= '<tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 12px;color:#1e293b;">'.$a['payee'].'</td>
                    <td style="padding:10px 12px;text-align:right;color:#dc2626;font-weight:bold;">&#8369;'.number_format($a['amount'],2).'</td>
                    <td style="padding:10px 12px;text-align:right;"><span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:999px;font-weight:bold;">'.$risk.'x avg</span></td>
                  </tr>';
            }
            $emailBody = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
              <div style="background:linear-gradient(135deg,#1e293b,#0f172a);padding:28px 32px;text-align:center;">
                <h1 style="color:#f1f5f9;font-size:20px;margin:0;letter-spacing:1px;">&#9888;&#65039; CIVENTRAL AI SECURITY ALERT</h1>
                <p style="color:#94a3b8;font-size:12px;margin:8px 0 0;">Automated Anomaly Detection System</p>
              </div>
              <div style="padding:28px 32px;background:#fff;">
                <p style="color:#1e293b;font-size:15px;font-weight:bold;">Dear Head of Treasury,</p>
                <p style="color:#475569;font-size:14px;">The Civentral AI system has detected <strong style="color:#dc2626;">'.count($anomalyAlerts).' suspicious disbursement voucher(s)</strong> that require your immediate attention:</p>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
                  <thead><tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="text-align:left;padding:10px 12px;color:#64748b;">PAYEE</th>
                    <th style="text-align:right;padding:10px 12px;color:#64748b;">AMOUNT</th>
                    <th style="text-align:right;padding:10px 12px;color:#64748b;">RISK</th>
                  </tr></thead>
                  <tbody>'.$rows.'</tbody>
                </table>
                <p style="color:#475569;font-size:13px;">Historical average disbursement: <strong>&#8369;'.number_format($avgDisbursement,2).'</strong></p>
                <p style="color:#475569;font-size:13px;">Please log in to the Treasury Portal immediately to review and verify these vouchers before releasing any funds.</p>
                <div style="text-align:center;margin:24px 0;">
                  <a href="https://civentral.tech/pages/treasury/disbursement.php" style="background:#dc2626;color:white;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;">Review Suspicious Vouchers &rarr;</a>
                </div>
              </div>
              <div style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;">
                <p style="color:#94a3b8;font-size:11px;margin:0;">This is an automated security alert from the Civentral AI Anomaly Detection System.<br>Caloocan City Treasury Portal</p>
              </div>
            </div>';
            sendSystemEmail('balcogodwin5@gmail.com','Head of Treasury','[AI ALERT] Suspicious Disbursement Detected - Civentral Treasury',$emailBody);
            $_SESSION['anomaly_email_sent_'.date('Y-m-d')] = true;
        } catch (\Throwable $e) {
            error_log('Anomaly email failed: '.$e->getMessage());
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
            <span>Revenue Collection & Treasury</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Dashboard</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-cash-register text-brand-dark"></i>
            Treasury Dashboard
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Consolidated view of daily collections, fund balances, and disbursements for the Office of the Municipal Treasurer.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <span>Could not load live data. <?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

            <?php if (!empty($alerts)):
        $cls  = ['danger'=>'bg-red-50 border-red-300 text-red-800','warning'=>'bg-amber-50 border-amber-300 text-amber-800','info'=>'bg-blue-50 border-blue-300 text-blue-800'];
        $icls = ['danger'=>'text-red-500','warning'=>'text-amber-500','info'=>'text-blue-500'];
      ?>
      <div class="space-y-2">
        <?php foreach ($alerts as $i => $a): ?>
        <div id="alrt-<?= $i ?>" class="flex items-start justify-between gap-3 border rounded-xl px-4 py-3 text-xs <?= $cls[$a['level']] ?>">
          <div class="flex items-start gap-2.5 min-w-0">
            <i class="fa-solid <?= $a['icon'] ?> mt-0.5 shrink-0 <?= $icls[$a['level']] ?>"></i>
            <span class="leading-relaxed"><?= $a['msg'] ?><?php if ($a['link']): ?>
              <a href="<?= htmlspecialchars($a['link']['href']) ?>" class="ml-1 underline font-bold hover:opacity-80"><?= htmlspecialchars($a['link']['label']) ?></a>
            <?php endif; ?></span>
          </div>
          <button onclick="document.getElementById('alrt-<?= $i ?>').remove()"
            class="shrink-0 opacity-40 hover:opacity-100 transition ml-2 mt-0.5" title="Dismiss">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">
        <!-- Today's Collections -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Today's Collections</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($todayTotal) ?></h3>
            <div class="flex items-center gap-2 flex-wrap">
              <p class="text-[11px] text-emerald-600 font-semibold"><?= count($todayCollections) ?> receipts today</p>
              <?php if ($todayChangePct !== null): ?>
              <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full <?= $todayChangePct >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' ?>">
                <i class="fa-solid <?= $todayChangePct >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                <?= ($todayChangePct >= 0 ? '+' : '') . $todayChangePct ?>% vs yesterday
              </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-sack-dollar text-sm"></i>
          </div>
        </div>

        <!-- Treasury Balance -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-brand-dark"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Treasury Balance</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($totalBalance) ?></h3>
            <p class="text-[11px] text-slate-400 font-semibold"><?= count($funds) ?> active funds</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-vault text-sm"></i>
          </div>
        </div>

        <!-- Monthly Collections -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-purple-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Monthly Collections</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($monthlyTotal) ?></h3>
            <div class="flex items-center gap-2 flex-wrap">
              <p class="text-[11px] text-purple-600 font-semibold"><?= count($monthlyCollections) ?> receipts this month</p>
              <?php if ($monthlyChangePct !== null): ?>
              <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full <?= $monthlyChangePct >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' ?>">
                <i class="fa-solid <?= $monthlyChangePct >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                <?= ($monthlyChangePct >= 0 ? '+' : '') . $monthlyChangePct ?>% vs last month
              </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-calendar-days text-sm"></i>
          </div>
        </div>

        <!-- Pending Requests -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Pending Requests</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count($pendingVouchers) + count($pendingBudgetRequests) ?></h3>
            <p class="text-[11px] text-amber-600 font-semibold"><?= $treasuryService->formatPeso($pendingTotal + $pendingBudgetTotal) ?> pending</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-hourglass-half text-sm"></i>
          </div>
        </div>

        <!-- Total Receipts -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-slate-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Receipts</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count($allCollections) ?></h3>
            <p class="text-[11px] text-slate-400 font-semibold">All-time transactions</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-receipt text-sm"></i>
          </div>
        </div>

        <!-- AI Revenue Forecast -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-indigo-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-500 dark:text-indigo-400 block flex items-center gap-1">
              <i class="fa-solid fa-wand-magic-sparkles"></i> AI Forecast
            </span>
            <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight"><?= $treasuryService->formatPeso($predictedNextMonth) ?></h3>
            <p class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold">Predicted next month</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-800/50 flex items-center justify-center text-indigo-500 group-hover:bg-indigo-100 transition">
            <i class="fa-solid fa-brain text-sm"></i>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           CHART ROW 1 — 12-Month Trend + Revenue Donut
      ══════════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- 12-Month Collection Trend (bar chart) -->
        <div class="xl:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs flex flex-col">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div>
              <h2 class="text-sm font-extrabold text-slate-800">12-Month Collection Trend</h2>
              <p class="text-[11px] text-slate-400 mt-0.5">Monthly revenue collected over the last year</p>
            </div>
            <div class="flex items-center gap-3">
              <?php if ($monthlyChangePct !== null): ?>
              <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-full
                <?= $monthlyChangePct >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' ?>">
                <i class="fa-solid <?= $monthlyChangePct >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                <?= ($monthlyChangePct >= 0 ? '+' : '') . $monthlyChangePct ?>% vs last month
              </span>
              <?php endif; ?>
              <a href="reports.php" class="text-[11px] font-bold text-brand-dark hover:underline">Full report &rarr;</a>
            </div>
          </div>
          <div class="p-5 flex-1 min-h-0">
            <div style="position:relative; height:240px;">
              <canvas id="trendChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Revenue Source Donut -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs flex flex-col">
          <div class="p-5 border-b border-slate-100 shrink-0">
            <h2 class="text-sm font-extrabold text-slate-800">Revenue by Source</h2>
            <p class="text-[11px] text-slate-400 mt-0.5">All-time collection breakdown</p>
          </div>
          <div class="p-5 flex-1 flex flex-col items-center justify-center gap-4">
            <?php if (!empty($revenueSources)): ?>
            <div style="position:relative; height:180px; width:180px;">
              <canvas id="donutChart"></canvas>
            </div>
            <!-- Legend -->
            <div class="w-full space-y-2 mt-1" id="donutLegend"></div>
            <?php else: ?>
            <div class="flex flex-col items-center py-8 text-slate-400">
              <i class="fa-solid fa-chart-pie text-3xl opacity-25 mb-2"></i>
              <p class="text-xs">No revenue data yet</p>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- ═══════════════════════════════════════════════════════════
           CHART ROW 2 — Fund Balances (bar) + Progress legend
      ══════════════════════════════════════════════════════════════ -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-extrabold text-slate-800">Fund Balances</h2>
            <p class="text-[11px] text-slate-400 mt-0.5">Current balance across all active funds</p>
          </div>
          <a href="disbursement.php" class="text-[11px] font-bold text-brand-dark hover:underline">Manage &rarr;</a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
          <!-- Bar chart -->
          <div class="p-5">
            <div style="position:relative; height:220px;">
              <canvas id="fundChart"></canvas>
            </div>
          </div>
          <!-- Progress bars legend -->
          <div class="p-5 space-y-4">
            <?php if (!empty($funds)):
              $maxBal = max(array_column($funds, 'balance') ?: [1]);
              foreach ($funds as $f):
                $pct = max(4, round(($f['balance'] / max($maxBal, 1)) * 100));
            ?>
            <div>
              <div class="flex items-baseline justify-between mb-1">
                <span class="text-xs font-bold text-slate-700 truncate max-w-[60%]"><?= htmlspecialchars($f['name']) ?>
                  <span class="text-slate-400 font-medium">· <?= htmlspecialchars($f['code']) ?></span>
                </span>
                <span class="text-xs font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($f['balance']) ?></span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-brand-medium to-brand-dark transition-all duration-700" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <?php endforeach; endif; ?>
            <?php if (empty($funds)): ?>
              <p class="text-xs text-slate-400">No fund data yet — run the SQL schema.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-800">Revenue Collection Systems</h2>
          </div>
          <div class="p-5 grid grid-cols-1 gap-4">
            <a href="business-tax.php" class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl hover:bg-brand-light hover:border-brand-medium transition group">
              <div class="h-12 w-12 rounded-lg bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark group-hover:bg-brand-medium group-hover:text-white transition">
                <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
              </div>
              <div class="flex-1">
                <h3 class="text-sm font-bold text-slate-800">Business Tax & Fees</h3>
                <p class="text-[11px] text-slate-500">Business permits and regulatory fees</p>
              </div>
              <i class="fa-solid fa-chevron-right text-slate-400 group-hover:text-brand-dark"></i>
            </a>

            <a href="market-stall.php" class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl hover:bg-brand-light hover:border-brand-medium transition group">
              <div class="h-12 w-12 rounded-lg bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark group-hover:bg-brand-medium group-hover:text-white transition">
                <i class="fa-solid fa-store text-lg"></i>
              </div>
              <div class="flex-1">
                <h3 class="text-sm font-bold text-slate-800">Market Stall Rental</h3>
                <p class="text-[11px] text-slate-500">Market stall billing and payments</p>
              </div>
              <i class="fa-solid fa-chevron-right text-slate-400 group-hover:text-brand-dark"></i>
            </a>

            <a href="collection.php" class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl hover:bg-brand-light hover:border-brand-medium transition group">
              <div class="h-12 w-12 rounded-lg bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark group-hover:bg-brand-medium group-hover:text-white transition">
                <i class="fa-solid fa-receipt text-lg"></i>
              </div>
              <div class="flex-1">
                <h3 class="text-sm font-bold text-slate-800">General Collection</h3>
                <p class="text-[11px] text-slate-500">Generic payment collection</p>
              </div>
              <i class="fa-solid fa-chevron-right text-slate-400 group-hover:text-brand-dark"></i>
            </a>

            <a href="online-payments.php" class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl hover:bg-brand-light hover:border-brand-medium transition group">
              <div class="h-12 w-12 rounded-lg bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark group-hover:bg-brand-medium group-hover:text-white transition">
                <i class="fa-solid fa-credit-card text-lg"></i>
              </div>
              <div class="flex-1">
                <h3 class="text-sm font-bold text-slate-800">Online Payments</h3>
                <p class="text-[11px] text-slate-500">Digital payment processing</p>
              </div>
              <i class="fa-solid fa-chevron-right text-slate-400 group-hover:text-brand-dark"></i>
            </a>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-800">Budget Management</h2>
            <a href="budget-approvals.php" class="text-[11px] font-bold text-brand-dark hover:underline">Approvals &rarr;</a>
          </div>
          <div class="p-5 space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-700">Pending Budget Requests</span>
              <span class="text-xs font-mono font-bold text-amber-600"><?= count($pendingBudgetRequests) ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-700">Total Requested</span>
              <span class="text-xs font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($pendingBudgetTotal) ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-700">Pending Disbursements</span>
              <span class="text-xs font-mono font-bold text-amber-600"><?= count($pendingVouchers) ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-700">Total Disbursement Amount</span>
              <span class="text-xs font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($pendingTotal) ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs" id="recent-tx-card">
        <!-- Card header -->
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <h2 class="text-sm font-extrabold text-slate-800">Recent Transactions</h2>
            <span id="tx-count-badge"
              class="inline-flex items-center text-[10px] font-black px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
              <?= count($recent) ?> records
            </span>
          </div>
          <!-- Search + Filter controls -->
          <div class="flex items-center gap-2 flex-wrap">
            <div class="relative">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
              <input type="text" id="tx-search"
                placeholder="Search OR#, payer, source…"
                class="pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-lg w-52 focus:ring-2 focus:ring-brand-medium/30 focus:border-brand-medium outline-none transition"
                oninput="filterTransactions()">
            </div>
            <select id="tx-mode-filter" onchange="filterTransactions()"
              class="text-xs border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-medium/30 focus:border-brand-medium outline-none transition text-slate-600 bg-white">
              <option value="">All modes</option>
              <?php
                $modes = array_unique(array_map(
                    fn($c) => ucfirst(strtolower($c['payment_mode'] ?? 'Cash')),
                    $recent
                ));
                foreach ($modes as $m): ?>
              <option value="<?= htmlspecialchars(strtolower($m)) ?>"><?= htmlspecialchars($m) ?></option>
              <?php endforeach; ?>
            </select>
            <button onclick="clearTxFilters()"
              id="tx-clear-btn"
              class="hidden text-[11px] font-bold text-slate-400 hover:text-brand-dark px-2 py-2 rounded-lg transition">
              <i class="fa-solid fa-xmark"></i> Clear
            </button>
            <a href="collection.php" class="text-[11px] font-bold text-brand-dark hover:underline whitespace-nowrap">View all &rarr;</a>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-xs" id="tx-table">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                <th class="px-5 py-3 font-bold">OR No.</th>
                <th class="px-5 py-3 font-bold">Payer</th>
                <th class="px-5 py-3 font-bold">Source</th>
                <th class="px-5 py-3 font-bold">Mode</th>
                <th class="px-5 py-3 font-bold text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" id="tx-tbody">
              <?php foreach ($recent as $c): ?>
              <tr class="tx-row hover:bg-brand-light/40 transition"
                  data-or="<?= strtolower(htmlspecialchars($c['or_number'] ?? '')) ?>"
                  data-payer="<?= strtolower(htmlspecialchars($c['payer_name'] ?? '')) ?>"
                  data-source="<?= strtolower(htmlspecialchars($c['revenue_source'] ?? '')) ?>"
                  data-mode="<?= strtolower(htmlspecialchars($c['payment_mode'] ?? 'cash')) ?>">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($c['or_number'] ?? '—') ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700 cursor-pointer hover:bg-brand-light/30 rounded"
                    onclick="showReceiptDetails(<?= htmlspecialchars(json_encode($c)) ?>)">
                  <?= htmlspecialchars($c['payer_name'] ?? '—') ?>
                </td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($c['revenue_source'] ?? '—') ?></td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500">
                    <?= htmlspecialchars(ucfirst(strtolower($c['payment_mode'] ?? 'Cash'))) ?>
                  </span>
                </td>
                <td class="px-5 py-3 text-right font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($c['amount']) ?></td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($recent)): ?>
              <tr id="tx-empty-data">
                <td colspan="5" class="px-5 py-14 text-center">
                  <div class="flex flex-col items-center gap-3">
                    <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                      <i class="fa-solid fa-receipt text-2xl text-slate-300"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-400">No collections recorded yet</p>
                    <p class="text-[11px] text-slate-400">Start by recording your first official receipt.</p>
                    <a href="collection.php" class="inline-flex items-center gap-2 mt-1 bg-brand-dark hover:opacity-90 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
                      <i class="fa-solid fa-plus"></i> Record First Collection
                    </a>
                  </div>
                </td>
              </tr>
              <?php endif; ?>

              <!-- No-search-results row (hidden until needed) -->
              <tr id="tx-no-results" class="hidden">
                <td colspan="5" class="px-5 py-12 text-center">
                  <div class="flex flex-col items-center gap-3">
                    <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                      <i class="fa-solid fa-filter-circle-xmark text-2xl text-slate-300"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-400">No matching transactions</p>
                    <p class="text-[11px] text-slate-400">Try different keywords or clear the filters.</p>
                    <button onclick="clearTxFilters()" class="inline-flex items-center gap-2 mt-1 bg-white border border-slate-200 text-slate-600 text-xs font-bold px-4 py-2 rounded-lg transition hover:bg-slate-50">
                      <i class="fa-solid fa-xmark"></i> Clear Filters
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <script>
      function filterTransactions() {
        const q    = document.getElementById('tx-search').value.trim().toLowerCase();
        const mode = document.getElementById('tx-mode-filter').value.toLowerCase();
        const rows = document.querySelectorAll('#tx-tbody .tx-row');
        const noResults = document.getElementById('tx-no-results');
        const clearBtn  = document.getElementById('tx-clear-btn');
        const badge     = document.getElementById('tx-count-badge');

        let visible = 0;
        rows.forEach(row => {
          const matchText = !q
            || row.dataset.or.includes(q)
            || row.dataset.payer.includes(q)
            || row.dataset.source.includes(q);
          const matchMode = !mode || row.dataset.mode.includes(mode);
          const show = matchText && matchMode;
          row.classList.toggle('hidden', !show);
          if (show) visible++;
        });

        noResults.classList.toggle('hidden', visible > 0);
        clearBtn.classList.toggle('hidden', !q && !mode);
        badge.textContent = visible + ' record' + (visible !== 1 ? 's' : '');
        badge.className = 'inline-flex items-center text-[10px] font-black px-2 py-0.5 rounded-full '
          + (visible === 0 ? 'bg-red-50 text-red-500' : 'bg-slate-100 text-slate-600');
      }

      function clearTxFilters() {
        document.getElementById('tx-search').value = '';
        document.getElementById('tx-mode-filter').value = '';
        filterTransactions();
      }
      </script>


      <!-- Transaction History -->
      <?php 
      $module = 'treasury';
      $limit = 8;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>

    <!-- ═══════ CHART.JS ═══════ -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    (function () {
      // ── Shared helpers ────────────────────────────────────────────
      const peso = (v) =>
        '\u20B1' + parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

      Chart.defaults.font.family = "'Inter', 'ui-sans-serif', system-ui, sans-serif";
      Chart.defaults.color = '#94a3b8';   // slate-400

      // ── 1. 12-MONTH TREND BAR CHART ──────────────────────────────
      const trendLabels = <?= json_encode($trendLabels) ?>;
      const trendTotals = <?= json_encode($trendTotals) ?>;

      const trendCtx = document.getElementById('trendChart');
      if (trendCtx) {
        // Gradient fill for bars
        const barGrad = trendCtx.getContext('2d').createLinearGradient(0, 0, 0, 240);
        barGrad.addColorStop(0, 'rgba(23, 107, 135, 0.90)');   // brand-dark
        barGrad.addColorStop(1, 'rgba(134, 182, 246, 0.55)');  // brand-medium

        new Chart(trendCtx, {
          type: 'bar',
          data: {
            labels: trendLabels,
            datasets: [{
              label: 'Collections',
              data: trendTotals,
              backgroundColor: barGrad,
              borderColor: 'rgba(23, 107, 135, 0.9)',
              borderWidth: 0,
              borderRadius: 6,
              borderSkipped: false,
              hoverBackgroundColor: 'rgba(23, 107, 135, 1)',
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
                callbacks: {
                  label: (ctx) => '  ' + peso(ctx.parsed.y)
                }
              }
            },
            scales: {
              x: {
                grid: { display: false },
                border: { display: false },
                ticks: { font: { size: 10, weight: '600' } }
              },
              y: {
                grid: { color: 'rgba(148,163,184,0.12)', drawBorder: false },
                border: { display: false, dash: [4, 4] },
                ticks: {
                  font: { size: 10 },
                  callback: (v) => peso(v)
                }
              }
            },
            animation: {
              duration: 900,
              easing: 'easeOutQuart'
            }
          }
        });
      }

      // ── 2. REVENUE SOURCE DONUT CHART ────────────────────────────
      const donutData  = <?= json_encode(array_values($revenueSources)) ?>;
      const donutLabels = <?= json_encode(array_keys($revenueSources)) ?>;

      const palette = [
        '#176B87', '#86B6F6', '#0ea5e9', '#38bdf8',
        '#7dd3fc', '#2563eb', '#64748b', '#94a3b8'
      ];

      const donutCtx = document.getElementById('donutChart');
      if (donutCtx && donutData.length) {
        new Chart(donutCtx, {
          type: 'doughnut',
          data: {
            labels: donutLabels,
            datasets: [{
              data: donutData,
              backgroundColor: palette.slice(0, donutData.length),
              borderColor: '#ffffff',
              borderWidth: 3,
              hoverOffset: 8,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: 'rgba(15,23,42,0.92)',
                titleColor: '#e2e8f0',
                bodyColor: '#94a3b8',
                padding: 12,
                cornerRadius: 10,
                callbacks: {
                  label: (ctx) => '  ' + peso(ctx.parsed)
                }
              }
            },
            animation: { animateRotate: true, duration: 900, easing: 'easeOutQuart' }
          }
        });

        // Build custom legend
        const legend = document.getElementById('donutLegend');
        const total  = donutData.reduce((s, v) => s + v, 0);
        if (legend) {
          legend.innerHTML = donutLabels.map((lbl, i) => {
            const pct = total > 0 ? ((donutData[i] / total) * 100).toFixed(1) : 0;
            return `
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="inline-block w-2.5 h-2.5 rounded-sm shrink-0" style="background:${palette[i] || '#94a3b8'}"></span>
                  <span class="text-[11px] text-slate-600 font-medium truncate" title="${lbl}">${lbl}</span>
                </div>
                <span class="text-[11px] font-mono font-bold text-slate-700 shrink-0">${pct}%</span>
              </div>`;
          }).join('');
        }
      }

      // ── 3. FUND BALANCES HORIZONTAL BAR CHART ────────────────────
      const fundLabels  = <?= json_encode(array_column($funds, 'code')) ?>;
      const fundTotals  = <?= json_encode(array_map('floatval', array_column($funds, 'balance'))) ?>;

      const fundCtx = document.getElementById('fundChart');
      if (fundCtx && fundLabels.length) {
        const hGrad = fundCtx.getContext('2d').createLinearGradient(0, 0, 400, 0);
        hGrad.addColorStop(0, 'rgba(134, 182, 246, 0.75)');
        hGrad.addColorStop(1, 'rgba(23, 107, 135, 0.95)');

        new Chart(fundCtx, {
          type: 'bar',
          data: {
            labels: fundLabels,
            datasets: [{
              label: 'Balance',
              data: fundTotals,
              backgroundColor: hGrad,
              borderColor: 'rgba(23, 107, 135, 0.9)',
              borderWidth: 0,
              borderRadius: 6,
              borderSkipped: false,
            }]
          },
          options: {
            indexAxis: 'y',
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
                callbacks: {
                  label: (ctx) => '  ' + peso(ctx.parsed.x)
                }
              }
            },
            scales: {
              x: {
                grid: { color: 'rgba(148,163,184,0.12)' },
                border: { display: false },
                ticks: { font: { size: 10 }, callback: (v) => peso(v) }
              },
              y: {
                grid: { display: false },
                border: { display: false },
                ticks: { font: { size: 11, weight: '700' } }
              }
            },
            animation: { duration: 900, easing: 'easeOutQuart' }
          }
        });
      }
    })();
    </script>

    <!-- Receipt Details Modal -->
    <div id="receiptModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
      <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold text-slate-900">Receipt Details</h3>
          <button onclick="hideReceiptModal()" class="text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
        <div id="receiptDetails" class="space-y-3 text-sm">
          <!-- Details will be populated by JavaScript -->
        </div>
        <div id="receiptActions" class="mt-4 pt-4 border-t border-slate-200 flex gap-2">
          <!-- Action buttons will be populated by JavaScript -->
        </div>
      </div>
    </div>

    <!-- Edit Receipt Modal -->
    <div id="editReceiptModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
      <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-base font-black text-slate-900">Edit Receipt</h3>
            <p class="text-[11px] text-slate-400 mt-0.5">Changes are recorded in the audit log.</p>
          </div>
          <button onclick="hideEditModal()" class="text-slate-400 hover:text-slate-600 h-8 w-8 flex items-center justify-center rounded-lg hover:bg-slate-100 transition">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
        <form id="editReceiptForm" class="space-y-4">
          <input type="hidden" id="editReceiptId">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5 col-span-2">
              <label class="text-xs font-semibold text-gray-500">Payer Name</label>
              <input type="text" id="editPayerName" required
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
            </div>
            <div class="space-y-1.5 col-span-2">
              <label class="text-xs font-semibold text-gray-500">Revenue Source</label>
              <select id="editRevenueSource" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <option value="Real Property Tax">Real Property Tax</option>
                <option value="Business Permit &amp; License">Business Permit &amp; License</option>
                <option value="Market &amp; Slaughterhouse Fees">Market &amp; Slaughterhouse Fees</option>
                <option value="Community Tax Certificate">Community Tax Certificate</option>
                <option value="Miscellaneous Fees">Miscellaneous Fees</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Fund</label>
              <select id="editFundId" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <?php
                $fundLabels2 = ['BSF'=>'Business Service Fund','EDU'=>'Education Fund','GF'=>'General Fund','HLTH'=>'Health Fund','INFRA'=>'Infrastructure Fund','MSF'=>'Market Stall Fund','PTF'=>'Property Tax Fund','RDF'=>'Risk Disaster Fund'];
                foreach ($funds as $f):
                  $code2 = strtoupper((string)($f['code'] ?? ''));
                ?>
                <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($fundLabels2[$code2] ?? $code2) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Amount (₱)</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">₱</span>
                <input type="number" id="editAmount" min="0" step="0.01" required
                  class="w-full pl-8 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono">
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Payment Mode</label>
              <select id="editPaymentMode" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <option value="cash">Cash</option>
                <option value="online">Online / E-wallet</option>
              </select>
            </div>
            <div class="space-y-1.5 col-span-2">
              <label class="text-xs font-semibold text-gray-500">Audit Note <span class="text-slate-300 font-normal">(reason for edit)</span></label>
              <textarea id="editAuditNote" rows="2" placeholder="e.g. Corrected payer name spelling / wrong fund assigned…"
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition resize-none"></textarea>
            </div>
          </div>
          <div class="flex gap-2 pt-1">
            <button type="button" onclick="hideEditModal()" class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-sm transition">Cancel</button>
            <button type="submit" class="flex-1 py-2.5 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    let currentReceipt = null;
    
    function showReceiptDetails(receipt) {
      currentReceipt = receipt;
      const modal = document.getElementById('receiptModal');
      const details = document.getElementById('receiptDetails');
      const actions = document.getElementById('receiptActions');
      
      const date = new Date(receipt.created_at);
      const formattedDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
      const formattedTime = date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
      });

      details.innerHTML = `
        <div class="bg-slate-50 rounded-lg p-4 space-y-3">
          <div class="flex justify-between">
            <span class="text-slate-500">OR Number:</span>
            <span class="font-mono font-bold text-slate-800">${receipt.or_number}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Payer:</span>
            <span class="font-semibold text-slate-700">${receipt.payer_name}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Source:</span>
            <span class="font-semibold text-slate-700">${receipt.revenue_source}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Amount:</span>
            <span class="font-mono font-bold text-brand-dark text-lg">${formatPeso(receipt.amount)}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Payment Mode:</span>
            <span class="font-semibold text-slate-700">${receipt.payment_mode || 'Cash'}</span>
          </div>
          <div class="border-t border-slate-200 pt-3 mt-3">
            <div class="flex justify-between">
              <span class="text-slate-500">Date:</span>
              <span class="font-semibold text-slate-700">${formattedDate}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Time:</span>
              <span class="font-semibold text-slate-700">${formattedTime}</span>
            </div>
          </div>
          <div class="border-t border-slate-200 pt-3 mt-3">
            <div class="flex justify-between">
              <span class="text-slate-500">Collected By:</span>
              <span class="font-semibold text-slate-700">${receipt.collected_by || 'System'}</span>
            </div>
          </div>
        </div>
      `;
      
      actions.innerHTML = `
        <button onclick="editReceipt(${receipt.id})" class="flex-1 py-2.5 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition">
          <i class="fa-solid fa-edit mr-2"></i>Edit
        </button>
        <button onclick="deleteReceipt(${receipt.id})" class="flex-1 py-2.5 px-4 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-sm transition">
          <i class="fa-solid fa-trash mr-2"></i>Delete
        </button>
      `;
      
      modal.classList.remove('hidden');
    }

    function hideReceiptModal() {
      document.getElementById('receiptModal').classList.add('hidden');
    }

    function editReceipt(id) {
      hideReceiptModal();
      const editModal = document.getElementById('editReceiptModal');
      document.getElementById('editReceiptId').value = id;
      document.getElementById('editPayerName').value = currentReceipt.payer_name;
      document.getElementById('editAmount').value = currentReceipt.amount;
      document.getElementById('editPaymentMode').value = currentReceipt.payment_mode || 'cash';
      // Revenue source
      const srcSel = document.getElementById('editRevenueSource');
      if (srcSel) { srcSel.value = currentReceipt.revenue_source || ''; }
      // Fund
      const fundSel = document.getElementById('editFundId');
      if (fundSel && currentReceipt.fund_id) { fundSel.value = currentReceipt.fund_id; }
      // Clear audit note
      const note = document.getElementById('editAuditNote');
      if (note) note.value = '';
      editModal.classList.remove('hidden');
    }

    function hideEditModal() {
      document.getElementById('editReceiptModal').classList.add('hidden');
    }

    function deleteReceipt(id) {
      if (confirm('Are you sure you want to delete this receipt? This action cannot be undone.')) {
        // Send delete request to server
        fetch('delete_collection.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            hideReceiptModal();
            location.reload();
          } else {
            alert('Error: ' + data.error);
          }
        })
        .catch(error => {
          alert('Error: ' + error);
        });
      }
    }

    function formatPeso(amount) {
      return '₱' + parseFloat(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    // Handle edit form submission
    document.getElementById('editReceiptForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const id          = document.getElementById('editReceiptId').value;
      const payerName   = document.getElementById('editPayerName').value;
      const amount      = document.getElementById('editAmount').value;
      const paymentMode = document.getElementById('editPaymentMode').value;
      const revSrc      = document.getElementById('editRevenueSource')?.value || '';
      const fundId      = document.getElementById('editFundId')?.value || '';
      const auditNote   = document.getElementById('editAuditNote')?.value || '';

      let body = 'id=' + id
        + '&payer_name='      + encodeURIComponent(payerName)
        + '&amount='          + amount
        + '&payment_mode='    + encodeURIComponent(paymentMode)
        + '&revenue_source='  + encodeURIComponent(revSrc)
        + '&fund_id='         + encodeURIComponent(fundId)
        + '&audit_note='      + encodeURIComponent(auditNote);

      fetch('edit_collection.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          hideEditModal();
          location.reload();
        } else {
          alert('Error: ' + data.error);
        }
      })
      .catch(err => alert('Error: ' + err));
    });

    // Close modals on outside click
    document.getElementById('receiptModal').addEventListener('click', function(e) {
      if (e.target === this) {
        hideReceiptModal();
      }
    });

    document.getElementById('editReceiptModal').addEventListener('click', function(e) {
      if (e.target === this) {
        hideEditModal();
      }
    });
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>