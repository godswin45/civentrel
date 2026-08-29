<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../login.php');
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

// Calculate monthly collections
$thisMonth = date('Y-m');
$monthlyCollections = array_filter($allCollections, fn($c) => substr($c['created_at'], 0, 7) === $thisMonth);
$monthlyTotal = array_sum(array_column($monthlyCollections, 'amount'));

// Calculate collection by revenue source
$revenueSources = [];
foreach ($allCollections as $collection) {
    $source = $collection['revenue_source'];
    if (!isset($revenueSources[$source])) {
        $revenueSources[$source] = 0;
    }
    $revenueSources[$source] += $collection['amount'];
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

      <!-- Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Today's Collections</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($todayTotal) ?></h3>
            <p class="text-[11px] text-emerald-600 font-semibold"><?= count($todayCollections) ?> receipts today</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-sack-dollar text-sm"></i>
          </div>
        </div>

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

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-purple-500"></div>
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Monthly Collections</span>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight"><?= $treasuryService->formatPeso($monthlyTotal) ?></h3>
            <p class="text-[11px] text-purple-600 font-semibold"><?= count($monthlyCollections) ?> receipts this month</p>
          </div>
          <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-slate-100 transition">
            <i class="fa-solid fa-calendar-days text-sm"></i>
          </div>
        </div>

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
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-800">Fund Balances</h2>
            <a href="disbursement.php" class="text-[11px] font-bold text-brand-dark hover:underline">Manage &rarr;</a>
          </div>
          <div class="p-5 space-y-5">
            <?php
            $maxBal = max(array_column($funds, 'balance') ?: [1]);
            foreach ($funds as $f):
              $pct = max(6, round(($f['balance'] / max($maxBal, 1)) * 100));
            ?>
            <div>
              <div class="flex items-baseline justify-between mb-1.5">
                <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($f['name']) ?> <span class="text-slate-400 font-medium">· <?= htmlspecialchars($f['code']) ?></span></span>
                <span class="text-xs font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($f['balance']) ?></span>
              </div>
              <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-brand-medium to-brand-dark" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($funds)): ?>
              <p class="text-xs text-slate-400">No fund data yet — run the SQL schema.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-800">Revenue Sources</h2>
          </div>
          <div class="p-5 space-y-3">
            <?php if (!empty($revenueSources)): ?>
              <?php foreach ($revenueSources as $source => $amount): ?>
              <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-700"><?= htmlspecialchars($source) ?></span>
                <span class="text-xs font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($amount) ?></span>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-xs text-slate-400">No revenue data yet</p>
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

      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-800">Recent Transactions</h2>
          <a href="collection.php" class="text-[11px] font-bold text-brand-dark hover:underline">View all &rarr;</a>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                <th class="px-5 py-3 font-bold">OR No.</th>
                <th class="px-5 py-3 font-bold">Payer</th>
                <th class="px-5 py-3 font-bold">Source</th>
                <th class="px-5 py-3 font-bold text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($recent as $c): ?>
              <tr class="hover:bg-brand-light/40 transition">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($c['or_number']) ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700 cursor-pointer hover:bg-brand-light/30 rounded"
                    onclick="showReceiptDetails(<?= htmlspecialchars(json_encode($c)) ?>)">
                  <?= htmlspecialchars($c['payer_name']) ?>
                </td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($c['revenue_source']) ?></td>
                <td class="px-5 py-3 text-right font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($c['amount']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recent)): ?>
              <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">No collections recorded yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Transaction History -->
      <?php 
      $module = 'treasury';
      $limit = 8;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>

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
      <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold text-slate-900">Edit Receipt</h3>
          <button onclick="hideEditModal()" class="text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
        <form id="editReceiptForm" class="space-y-4">
          <input type="hidden" id="editReceiptId">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Payer Name</label>
            <input type="text" id="editPayerName" required class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Amount (₱)</label>
            <input type="number" id="editAmount" min="0" step="0.01" required class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Payment Mode</label>
            <select id="editPaymentMode" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
              <option value="cash">Cash</option>
              <option value="check">Check</option>
              <option value="online">Online / E-wallet</option>
            </select>
          </div>
          <div class="flex gap-2">
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
      const id = document.getElementById('editReceiptId').value;
      const payerName = document.getElementById('editPayerName').value;
      const amount = document.getElementById('editAmount').value;
      const paymentMode = document.getElementById('editPaymentMode').value;
      
      fetch('edit_collection.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id + '&payer_name=' + encodeURIComponent(payerName) + '&amount=' + amount + '&payment_mode=' + paymentMode
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          hideEditModal();
          location.reload();
        } else {
          alert('Error: ' + data.error);
        }
      })
      .catch(error => {
        alert('Error: ' + error);
      });
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