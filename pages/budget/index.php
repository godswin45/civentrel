<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Budget Management';
$activePage = 'budget-index';
$errorMsg = null;

try {
    $budgetRequests = $treasuryService->getAllBudgetRequests();
    $funds = $treasuryService->getFunds();
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $budgetRequests = [];
    $funds = [];
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
            <span>Budget Management</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Dashboard</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-money-bill-trend-up text-brand-dark"></i>
            Budget Management Dashboard
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Overview of departmental budget requests and approvals.
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
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Requests</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count($budgetRequests) ?></h3>
            <p class="text-[11px] text-slate-400 font-semibold">All time</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-file-invoice text-sm"></i>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Pending</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($budgetRequests, fn($r) => $r['status'] === 'Pending')) ?></h3>
            <p class="text-[11px] text-amber-600 font-semibold">Awaiting review</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-clock text-sm"></i>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Approved</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($budgetRequests, fn($r) => $r['status'] === 'Approved')) ?></h3>
            <p class="text-[11px] text-emerald-600 font-semibold">Awaiting release</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-check text-sm"></i>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-teal-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Released</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($budgetRequests, fn($r) => $r['status'] === 'Released')) ?></h3>
            <p class="text-[11px] text-teal-600 font-semibold">Funds disbursed</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-paper-plane text-sm"></i>
          </div>
        </div>
      </div>

      <!-- Budget Requests Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Budget Requests</h2>
          <span class="text-[11px] text-slate-400"><?= count($budgetRequests) ?> total requests</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                <th class="px-5 py-3 font-bold">Request No.</th>
                <th class="px-5 py-3 font-bold">Department</th>
                <th class="px-5 py-3 font-bold">Project</th>
                <th class="px-5 py-3 font-bold">Amount</th>
                <th class="px-5 py-3 font-bold">Fiscal Year</th>
                <th class="px-5 py-3 font-bold">Status</th>
                <th class="px-5 py-3 font-bold">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($budgetRequests as $request): ?>
              <tr class="hover:bg-brand-light/40 transition">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($request['request_number'] ?? $request['request_no'] ?? '') ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($request['department_name']) ?></td>
                <td class="px-5 py-3 text-slate-500">
                  <?= htmlspecialchars($request['project_title']) ?>
                  <?php if (!empty($request['supporting_document'])): ?>
                    <a href="../treasury/<?= htmlspecialchars($request['supporting_document']) ?>" target="_blank" class="block text-[10px] text-brand-dark font-bold hover:underline mt-1"><i class="fa-solid fa-paperclip mr-1"></i>View attachment</a>
                  <?php endif; ?>
                </td>
                <td class="px-5 py-3 font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($request['requested_amount']) ?></td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($request['fiscal_year']) ?> - <?= htmlspecialchars($request['quarter']) ?></td>
                <td class="px-5 py-3">
                  <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase
                    <?= $request['status'] === 'Approved' ? 'bg-emerald-100 text-emerald-700' : 
                       ($request['status'] === 'Pending' ? 'bg-amber-100 text-amber-700' : 
                       ($request['status'] === 'Released' ? 'bg-teal-100 text-teal-700' : 
                       ($request['status'] === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'))) ?>">
                    <?= htmlspecialchars($request['status']) ?>
                  </span>
                </td>
                <td class="px-5 py-3 text-slate-500"><?= date('M j, Y g:i A', strtotime($request['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($budgetRequests)): ?>
              <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No budget requests yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>