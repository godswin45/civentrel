<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Online Payments Management';
$activePage = 'treasury-online-payments';
$errorMsg = null;
$successMsg = null;

try {
    $onlinePayments = $treasuryService->getAllOnlinePayments();
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $onlinePayments = [];
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
            <span class="text-brand-dark">Online Payments</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-credit-card text-brand-dark"></i>
            Online Payments Management
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Monitor and manage online payment transactions from citizens.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-blue-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Payments</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count($onlinePayments) ?></h3>
            <p class="text-[11px] text-slate-400 font-semibold">All time</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-receipt text-sm"></i>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Completed</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($onlinePayments, fn($p) => $p['status'] === 'completed')) ?></h3>
            <p class="text-[11px] text-emerald-600 font-semibold">Successful payments</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-check text-sm"></i>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Pending</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($onlinePayments, fn($p) => $p['status'] === 'pending')) ?></h3>
            <p class="text-[11px] text-amber-600 font-semibold">Awaiting processing</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-clock text-sm"></i>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-red-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Failed</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($onlinePayments, fn($p) => $p['status'] === 'failed')) ?></h3>
            <p class="text-[11px] text-red-600 font-semibold">Payment errors</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-xmark text-sm"></i>
          </div>
        </div>
      </div>

      <!-- Payments Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Payment Transactions</h2>
          <span class="text-[11px] text-slate-400"><?= count($onlinePayments) ?> total transactions</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                <th class="px-5 py-3 font-bold">Reference</th>
                <th class="px-5 py-3 font-bold">Citizen</th>
                <th class="px-5 py-3 font-bold">Type</th>
                <th class="px-5 py-3 font-bold">Amount</th>
                <th class="px-5 py-3 font-bold">Gateway</th>
                <th class="px-5 py-3 font-bold">Status</th>
                <th class="px-5 py-3 font-bold">OR Number</th>
                <th class="px-5 py-3 font-bold">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($onlinePayments as $payment): ?>
              <tr class="hover:bg-brand-light/40 transition">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($payment['payment_reference']) ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($payment['citizen_name']) ?></td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($payment['payment_source']) ?></td>
                <td class="px-5 py-3 font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($payment['amount']) ?></td>
                <td class="px-5 py-3 text-slate-500 uppercase"><?= htmlspecialchars($payment['payment_gateway']) ?></td>
                <td class="px-5 py-3">
                  <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase
                    <?= $payment['status'] === 'completed' ? 'bg-emerald-100 text-emerald-700' : 
                       ($payment['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : 
                       ($payment['status'] === 'processing' ? 'bg-blue-100 text-blue-700' : 
                       ($payment['status'] === 'failed' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'))) ?>">
                    <?= htmlspecialchars($payment['status']) ?>
                  </span>
                </td>
                <td class="px-5 py-3 font-mono text-slate-500"><?= $payment['or_number'] ? htmlspecialchars($payment['or_number']) : '-' ?></td>
                <td class="px-5 py-3 text-slate-500"><?= date('M j, Y g:i A', strtotime($payment['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($onlinePayments)): ?>
              <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">No online payments recorded yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>