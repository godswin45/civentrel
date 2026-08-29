<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Budget Approvals';
$activePage = 'budget-approvals';
$errorMsg = null;
$successMsg = null;

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'approve') {
            $request = $treasuryService->approveBudgetRequest((int)$_POST['request_id'], $headerUser['full_name'] ?? 'Treasury Admin');
            $successMsg = 'Budget request approved successfully!';
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'action' => 'approve',
                    'table_name' => 'tr_budget_requests',
                    'record_id' => $_POST['request_id'],
                    'new_values' => json_encode(['status' => 'approved', 'approved_by' => $headerUser['full_name']])
                ]);
            }
        } elseif ($_POST['action'] === 'reject') {
            $treasuryService->updateBudgetRequestStatus((int)$_POST['request_id'], 'Rejected', [
                'justification' => trim($_POST['justification'] ?? '')
            ]);
            $successMsg = 'Budget request rejected.';
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'action' => 'reject',
                    'table_name' => 'tr_budget_requests',
                    'record_id' => $_POST['request_id'],
                    'new_values' => json_encode(['status' => 'rejected', 'rejection_reason' => trim($_POST['justification'] ?? '')])
                ]);
            }
        } elseif ($_POST['action'] === 'release') {
            $treasuryService->releaseBudget((int)$_POST['request_id']);
            $successMsg = 'Budget released successfully!';
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'action' => 'disburse',
                    'table_name' => 'tr_budget_requests',
                    'record_id' => $_POST['request_id'],
                    'new_values' => json_encode(['status' => 'released'])
                ]);
            }
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

try {
    $budgetRequests = $treasuryService->getAllBudgetRequests();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $budgetRequests = [];
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
            <span class="text-brand-dark">Approvals</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-check-double text-brand-dark"></i>
            Budget Approvals
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Review, approve, and reject departmental budget requests.
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

      <!-- Budget Requests Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Pending Budget Requests</h2>
          <span class="text-[11px] text-slate-400"><?= count(array_filter($budgetRequests, fn($r) => $r['status'] === 'Pending')) ?> pending requests</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                <th class="px-5 py-3 font-bold">Request No.</th>
                <th class="px-5 py-3 font-bold">Department</th>
                <th class="px-5 py-3 font-bold">Project</th>
                <th class="px-5 py-3 font-bold">Amount</th>
                <th class="px-5 py-3 font-bold">Justification</th>
                <th class="px-5 py-3 font-bold">Status</th>
                <th class="px-5 py-3 font-bold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($budgetRequests as $request): ?>
              <tr class="hover:bg-brand-light/40 transition">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($request['request_number'] ?? $request['request_no'] ?? '') ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($request['department_name']) ?></td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($request['project_title'] ?? $request['description'] ?? '') ?></td>
                <td class="px-5 py-3 font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($request['requested_amount']) ?></td>
                <td class="px-5 py-3 text-slate-500 max-w-xs truncate"><?= htmlspecialchars($request['justification'] ?? '-') ?></td>
                <td class="px-5 py-3">
                  <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase
                    <?= strtolower($request['status']) === 'approved' ? 'bg-emerald-100 text-emerald-700' : 
                       (strtolower($request['status']) === 'pending' ? 'bg-amber-100 text-amber-700' : 
                       (strtolower($request['status']) === 'released' ? 'bg-teal-100 text-teal-700' : 
                       (strtolower($request['status']) === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'))) ?>">
                    <?= htmlspecialchars($request['status']) ?>
                  </span>
                </td>
                <td class="px-5 py-3">
                  <?php if (strtolower($request['status']) === 'pending'): ?>
                    <div class="flex items-center gap-2">
                      <form method="post" class="inline">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition">Approve</button>
                      </form>
                      <button onclick="showRejectModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['request_number'] ?? $request['request_no'] ?? '') ?>')" class="bg-red-600 hover:bg-red-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition">Reject</button>
                    </div>
                  <?php elseif (strtolower($request['status']) === 'approved'): ?>
                    <form method="post" class="inline">
                      <input type="hidden" name="action" value="release">
                      <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                      <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-3 py-1. rounded-lg text-xs transition">Release</button>
                    </form>
                  <?php else: ?>
                    <span class="text-slate-400 text-xs">No actions</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($budgetRequests)): ?>
              <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">No budget requests yet.</td></tr>
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

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
      <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold text-slate-900 mb-4">Reject Budget Request</h3>
        <p class="text-sm text-slate-500 mb-4">Please provide a reason for rejection:</p>
        <form method="post">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="request_id" id="rejectRequestId">
          <textarea name="justification" rows="3" required placeholder="Reason for rejection" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition mb-4"></textarea>
          <div class="flex justify-end gap-3">
            <button type="button" onclick="hideRejectModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">Cancel</button>
            <button type="submit" class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg transition">Reject</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    function showRejectModal(requestId, requestNumber) {
        document.getElementById('rejectRequestId').value = requestId;
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function hideRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>