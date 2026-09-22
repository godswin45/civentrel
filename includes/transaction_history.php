<?php
/**
 * Transaction History Component
 * Reusable component for displaying transaction history across treasury pages
 */

$canAccessAudit = !empty($headerUser['is_superadmin']) || !empty($headerUser['is_global_access']) || (isset($hasResourceAccess) && $hasResourceAccess(['audit', 'audit log', 'user activity', 'login history', 'data change']));
if (!$canAccessAudit) {
    return; // Hide transaction history for regular staff without audit permissions
}

if (!isset($module)) {
    $module = 'treasury';
}

if (!isset($limit)) {
    $limit = 10;
}

try {
    $recentTransactions = $auditService->getRecentTransactions($module, $limit);
} catch (Exception $e) {
    $recentTransactions = [];
}
?>

<div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-extrabold text-slate-800">Transaction History</h2>
    <div class="flex items-center space-x-2">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Recent Activity</span>
        <span class="bg-brand-light text-brand-dark text-[10px] font-bold px-2 py-0.5 rounded-full"><?= count($recentTransactions) ?> records</span>
    </div>
  </div>
  
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead>
        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
          <th class="px-5 py-3 font-bold">Date & Time</th>
          <th class="px-5 py-3 font-bold">User</th>
          <th class="px-5 py-3 font-bold">Action</th>
          <th class="px-5 py-3 font-bold">Module</th>
          <th class="px-5 py-3 font-bold">IP Address</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($recentTransactions as $transaction): ?>
        <tr class="hover:bg-brand-light/40 transition">
          <td class="px-5 py-3 text-slate-500">
            <div class="font-mono text-xs"><?= date('M d, Y', strtotime($transaction['created_at'])) ?></div>
            <div class="text-[10px] text-slate-400"><?= date('h:i A', strtotime($transaction['created_at'])) ?></div>
          </td>
          <td class="px-5 py-3 font-semibold text-slate-700">
            <?= htmlspecialchars($transaction['username']) ?>
          </td>
          <td class="px-5 py-3">
            <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase
              <?php 
              $actionColors = [
                  'create' => 'bg-emerald-100 text-emerald-700',
                  'update' => 'bg-blue-100 text-blue-700',
                  'delete' => 'bg-red-100 text-red-700',
                  'approve' => 'bg-purple-100 text-purple-700',
                  'reject' => 'bg-red-100 text-red-700',
                  'disburse' => 'bg-orange-100 text-orange-700',
                  'collect' => 'bg-teal-100 text-teal-700',
                  'payment' => 'bg-green-100 text-green-700',
              ];
              $actionColor = $actionColors[strtolower($transaction['action'])] ?? 'bg-slate-100 text-slate-700';
              echo $actionColor;
              ?>">
              <?= htmlspecialchars($transaction['action']) ?>
            </span>
          </td>
          <td class="px-5 py-3 text-slate-500">
            <?= htmlspecialchars($transaction['table_name']) ?>
          </td>
          <td class="px-5 py-3 font-mono text-slate-500">
            <?= htmlspecialchars($transaction['ip_address']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentTransactions)): ?>
        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No transaction history yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <?php if (count($recentTransactions) >= $limit): ?>
  <div class="p-4 border-t border-slate-100 text-center">
    <a href="reports.php" class="text-[11px] font-bold text-brand-dark hover:underline">
      View All Transactions &rarr;
    </a>
  </div>
  <?php endif; ?>
</div>