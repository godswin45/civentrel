<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'My Budget Requests';
$activePage = 'budget-my-requests';
$errorMsg = null;
$successMsg = null;
$createdRequest = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    try {
        $amount = (float) ($_POST['requested_amount'] ?? 0);
        if ($amount <= 0) throw new Exception('Amount must be greater than zero.');

        $createdRequest = $treasuryService->createBudgetRequest([
            'department_name' => trim($_POST['department_name'] ?? ''),
            'department_code' => trim($_POST['department_code'] ?? ''),
            'project_title' => trim($_POST['project_title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'requested_amount' => $amount,
            'fund_id' => $_POST['fund_id'] ?? 'GF',
            'fiscal_year' => (int)($_POST['fiscal_year'] ?? date('Y')),
            'quarter' => $_POST['quarter'] ?? 'Q1',
            'requested_by' => $headerUser['full_name'] ?? null,
            'justification' => trim($_POST['justification'] ?? ''),
        ]);
        
        // Log the transaction
        if ($auditService) {
            $auditService->logTransaction([
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $headerUser['full_name'] ?? 'System',
                'module' => 'budget',
                'action' => 'create',
                'table_name' => 'tr_budget_requests',
                'record_id' => $createdRequest['id'] ?? null,
                'new_values' => json_encode($createdRequest)
            ]);
        }

        $successMsg = 'Budget request created successfully!';
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

try {
    $budgetRequests = $treasuryService->getAllBudgetRequests();
    $funds = $treasuryService->getFunds();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $budgetRequests = [];
    $funds = [];
    $auditService = null;
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
            <span class="text-brand-dark">My Requests</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-file-invoice text-brand-dark"></i>
            My Budget Requests
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Submit budget requests and track their approval status.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($successMsg): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-check mt-0.5"></i><span><?= htmlspecialchars($successMsg) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($_GET['imported'])): ?>
      <div class="bg-sky-50 border border-sky-200 text-sky-700 rounded-2xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-file-import mt-0.5"></i><span><?= htmlspecialchars($_GET['imported']) ?></span>
      </div>
      <?php endif; ?>

      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="text-sm font-extrabold text-slate-800">Import budget requests</h2>
            <p class="text-[11px] text-slate-400 mt-1">CSV, Excel, or JSON, up to 1,000 rows. Required: department, project, amount.</p>
          </div>
          <form method="post" action="../treasury/import-financial-data.php" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="target" value="budget">
            <input type="file" name="import_file" accept=".csv,.xlsx,.json" required class="max-w-xs text-xs text-slate-500">
            <button type="submit" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white font-bold px-3 py-2 rounded-lg text-xs transition"><i class="fa-solid fa-file-import"></i> Import</button>
          </form>
        </div>
      </div>

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
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($budgetRequests, fn($r) => $r['status'] === 'Approved' || $r['status'] === 'Released')) ?></h3>
            <p class="text-[11px] text-emerald-600 font-semibold">Budget approved</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-check text-sm"></i>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-red-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Rejected</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= count(array_filter($budgetRequests, fn($r) => $r['status'] === 'Rejected')) ?></h3>
            <p class="text-[11px] text-red-600 font-semibold">Requests denied</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-times text-sm"></i>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Request Form -->
        <div class="lg:col-span-2">
          <form method="post" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 space-y-4">
            <input type="hidden" name="action" value="create_request">
            <h2 class="text-sm font-extrabold text-slate-800 pb-1">Create New Budget Request</h2>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Department Name</label>
                <input type="text" name="department_name" required placeholder="e.g. City Engineering"
                  class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Department Code</label>
                <input type="text" name="department_code" required placeholder="e.g. ENG"
                  class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Project Title</label>
              <input type="text" name="project_title" required placeholder="e.g. Road Rehabilitation Project"
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Description</label>
              <textarea name="description" rows="3" placeholder="Project description and objectives"
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-3">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Fiscal Year</label>
                <select name="fiscal_year" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                  <option value="2026">2026</option>
                  <option value="2025">2025</option>
                  <option value="2024">2024</option>
                </select>
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Quarter</label>
                <select name="quarter" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                  <option value="Q1">Q1</option>
                  <option value="Q2">Q2</option>
                  <option value="Q3">Q3</option>
                  <option value="Q4">Q4</option>
                </select>
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-500">Fund</label>
                <select name="fund_id" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                  <?php foreach ($funds as $f): ?>
                    <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['code']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Requested Amount (₱)</label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-gray-400 text-sm font-bold">₱</span>
                <input type="number" name="requested_amount" min="1" step="0.01" required placeholder="0.00"
                  class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono">
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Justification</label>
              <textarea name="justification" rows="2" placeholder="Why this budget is needed"
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition"></textarea>
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
              Submit Budget Request
            </button>
          </form>
        </div>

        <!-- Info Panel -->
        <div class="lg:col-span-1">
          <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
            <h2 class="text-sm font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Budget Request Process</h2>
            
            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="h-8 w-8 rounded-full bg-amber-100 border border-amber-200 flex items-center justify-center text-amber-600 flex-shrink-0">
                  <span class="text-xs font-bold">1</span>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">Submit Request</h3>
                  <p class="text-[11px] text-slate-500">Department submits budget request</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="h-8 w-8 rounded-full bg-blue-100 border border-blue-200 flex items-center justify-center text-blue-600 flex-shrink-0">
                  <span class="text-xs font-bold">2</span>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">Treasury Review</h3>
                  <p class="text-[11px] text-slate-500">Treasury admin reviews and approves</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="h-8 w-8 rounded-full bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-600 flex-shrink-0">
                  <span class="text-xs font-bold">3</span>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">Approval</h3>
                  <p class="text-[11px text-slate-500">Budget approved if funds available</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="h-8 w-8 rounded-full bg-teal-100 border border-teal-200 flex items-center justify-center text-teal-600 flex-shrink-0">
                  <span class="text-xs font-bold">4</span>
                </div>
                <div>
                  <h3 class="text-xs font-bold text-slate-800">Release</h3>
                  <p class="text-[11px text-slate-500">Funds released to department</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Budget Requests Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">My Budget Requests</h2>
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
                <th class="px-5 py-3 font-bold">Status</th>
                <th class="px-5 py-3 font-bold">Details</th>
                <th class="px-5 py-3 font-bold">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($budgetRequests as $request): ?>
              <tr class="hover:bg-brand-light/40 transition">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($request['request_number'] ?? $request['request_no'] ?? '') ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($request['department_name']) ?></td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($request['project_title'] ?? $request['description'] ?? '') ?></td>
                <td class="px-5 py-3 font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($request['requested_amount']) ?></td>
                <td class="px-5 py-3">
                  <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase
                    <?= $request['status'] === 'Approved' ? 'bg-emerald-100 text-emerald-700' : 
                       ($request['status'] === 'Pending' ? 'bg-amber-100 text-amber-700' : 
                       ($request['status'] === 'Released' ? 'bg-teal-100 text-teal-700' : 
                       ($request['status'] === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'))) ?>">
                    <?= htmlspecialchars($request['status']) ?>
                  </span>
                </td>
                <td class="px-5 py-3">
                  <?php if ($request['status'] === 'Rejected' && !empty($request['justification'])): ?>
                    <div class="text-red-600 text-xs max-w-xs truncate" title="<?= htmlspecialchars($request['justification']) ?>">
                      <i class="fa-solid fa-circle-exclamation mr-1"></i>
                      <?= htmlspecialchars(substr($request['justification'], 0, 50)) ?>...
                    </div>
                  <?php elseif ($request['status'] === 'Approved'): ?>
                    <div class="text-emerald-600 text-xs">
                      <i class="fa-solid fa-check-circle mr-1"></i>
                      Approved by <?= htmlspecialchars($request['approved_by'] ?? 'Treasury') ?>
                    </div>
                  <?php elseif ($request['status'] === 'Released'): ?>
                    <div class="text-teal-600 text-xs">
                      <i class="fa-solid fa-paper-plane mr-1"></i>
                      Funds released
                    </div>
                  <?php else: ?>
                    <span class="text-slate-400 text-xs">Under review</span>
                  <?php endif; ?>
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
      <!-- Transaction History -->
      <?php 
      $module = 'budget';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>