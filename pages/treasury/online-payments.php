<?php
require_once __DIR__ . '/../../src/bootstrap.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle  = 'Online Payments Management';
$activePage = 'treasury-online-payments';
$errorMsg   = null;
$successMsg = null;

// ── Manual Confirm / Reject ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $ref    = trim($_POST['payment_reference'] ?? '');
    $id     = (int) ($_POST['payment_id'] ?? 0);

    try {
        if ($action === 'confirm' && $ref) {
            $treasuryService->processOnlinePayment($ref, [
                'status'    => 'success',
                'reference' => 'MANUAL-' . strtoupper(substr(uniqid(), -6)),
            ]);
            $successMsg = "Payment <strong>{$ref}</strong> has been manually confirmed and an OR was issued.";
        } elseif ($action === 'reject' && $id) {
            $treasuryRepo->updateOnlinePaymentStatus($id, 'failed', []);
            $successMsg = "Payment has been marked as failed.";
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

// ── Load payments ─────────────────────────────────────────────────────────
try {
    $onlinePayments = $treasuryService->getAllOnlinePayments();
} catch (Exception $e) {
    $errorMsg       = $e->getMessage();
    $onlinePayments = [];
}

// Aggregate counts per status
$statusCounts = ['completed' => 0, 'pending' => 0, 'processing' => 0, 'failed' => 0];
foreach ($onlinePayments as $p) {
    $s = strtolower($p['status'] ?? '');
    if (isset($statusCounts[$s])) $statusCounts[$s]++;
}
$totalAmount = array_sum(array_column(
    array_filter($onlinePayments, fn($p) => $p['status'] === 'completed'),
    'amount'
));

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
            Monitor, filter, and manually confirm or reject online payment transactions from citizens.
          </p>
        </div>
      </div>

      <!-- Flash messages -->
      <?php if ($successMsg): ?>
      <div id="flash-ok" class="flex items-start justify-between gap-3 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl px-4 py-3 text-xs">
        <div class="flex items-start gap-2"><i class="fa-solid fa-circle-check mt-0.5 text-emerald-500"></i><span><?= $successMsg ?></span></div>
        <button onclick="document.getElementById('flash-ok').remove()" class="opacity-40 hover:opacity-100"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-xs flex items-start gap-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <?php
        $cards = [
          ['label'=>'Total','val'=>count($onlinePayments),'sub'=>'All transactions','bar'=>'bg-blue-500','icon'=>'fa-receipt'],
          ['label'=>'Completed','val'=>$statusCounts['completed'],'sub'=>$treasuryService->formatPeso($totalAmount),'bar'=>'bg-emerald-500','icon'=>'fa-check'],
          ['label'=>'Pending','val'=>$statusCounts['pending'],'sub'=>'Awaiting processing','bar'=>'bg-amber-500','icon'=>'fa-clock'],
          ['label'=>'Processing','val'=>$statusCounts['processing'],'sub'=>'Gateway in progress','bar'=>'bg-blue-400','icon'=>'fa-arrows-rotate'],
          ['label'=>'Failed','val'=>$statusCounts['failed'],'sub'=>'Payment errors','bar'=>'bg-red-500','icon'=>'fa-xmark'],
        ];
        foreach ($cards as $card): ?>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs flex items-center justify-between group relative overflow-hidden cursor-pointer"
             onclick="setStatusFilter('<?= strtolower($card['label']) ?>')">
          <div class="absolute top-0 left-0 w-1.5 h-full <?= $card['bar'] ?>"></div>
          <div class="space-y-0.5 pl-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block"><?= $card['label'] ?></span>
            <h3 class="text-xl font-black text-slate-900"><?= $card['val'] ?></h3>
            <p class="text-[11px] text-slate-400 font-semibold"><?= $card['sub'] ?></p>
          </div>
          <div class="h-9 w-9 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-slate-100 transition">
            <i class="fa-solid <?= $card['icon'] ?> text-sm"></i>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Payments Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs">
        <!-- Table header + controls -->
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <h2 class="text-sm font-extrabold text-slate-800">Payment Transactions</h2>
            <span id="op-count-badge" class="inline-flex items-center text-[10px] font-black px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
              <?= count($onlinePayments) ?> records
            </span>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <!-- Search -->
            <div class="relative">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
              <input type="text" id="op-search" placeholder="Ref # or citizen…"
                class="pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-lg w-44 focus:ring-2 focus:ring-brand-medium/30 focus:border-brand-medium outline-none transition"
                oninput="filterPayments()">
            </div>
            <!-- Status filter -->
            <select id="op-status" onchange="filterPayments()"
              class="text-xs border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-medium/30 outline-none transition bg-white text-slate-600">
              <option value="">All statuses</option>
              <option value="pending">Pending</option>
              <option value="processing">Processing</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
            </select>
            <button id="op-clear-btn" onclick="clearOPFilters()"
              class="hidden text-[11px] font-bold text-slate-400 hover:text-brand-dark px-2 py-2 rounded-lg transition">
              <i class="fa-solid fa-xmark"></i> Clear
            </button>
          </div>
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
                <th class="px-5 py-3 font-bold">OR #</th>
                <th class="px-5 py-3 font-bold">Date</th>
                <th class="px-5 py-3 font-bold">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50" id="op-tbody">
              <?php foreach ($onlinePayments as $p):
                $status    = strtolower($p['status'] ?? '');
                $canAct    = in_array($status, ['pending', 'processing']);
                $statusCls = match($status) {
                  'completed'  => 'bg-emerald-100 text-emerald-700',
                  'pending'    => 'bg-amber-100 text-amber-700',
                  'processing' => 'bg-blue-100 text-blue-700',
                  'failed'     => 'bg-red-100 text-red-700',
                  default      => 'bg-slate-100 text-slate-600',
                };
              ?>
              <tr class="op-row hover:bg-slate-50/60 transition"
                  data-ref="<?= strtolower(htmlspecialchars($p['payment_reference'] ?? '')) ?>"
                  data-citizen="<?= strtolower(htmlspecialchars($p['citizen_name'] ?? '')) ?>"
                  data-status="<?= $status ?>">
                <td class="px-5 py-3 font-mono text-brand-dark font-bold"><?= htmlspecialchars($p['payment_reference'] ?? '—') ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($p['citizen_name'] ?? '—') ?></td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($p['payment_source'] ?? '—') ?></td>
                <td class="px-5 py-3 font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($p['amount']) ?></td>
                <td class="px-5 py-3 text-slate-500 uppercase text-[10px] font-bold"><?= htmlspecialchars($p['payment_gateway'] ?? '—') ?></td>
                <td class="px-5 py-3">
                  <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase <?= $statusCls ?>"><?= $status ?></span>
                </td>
                <td class="px-5 py-3 font-mono text-slate-400"><?= $p['or_number'] ? htmlspecialchars($p['or_number']) : '—' ?></td>
                <td class="px-5 py-3 text-slate-400 whitespace-nowrap"><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></td>
                <td class="px-5 py-3">
                  <?php if ($canAct): ?>
                  <div class="flex items-center gap-1.5">
                    <!-- Confirm -->
                    <form method="post" onsubmit="return confirm('Manually confirm this payment and issue an OR?')">
                      <input type="hidden" name="action" value="confirm">
                      <input type="hidden" name="payment_reference" value="<?= htmlspecialchars($p['payment_reference']) ?>">
                      <button type="submit" class="inline-flex items-center gap-1 bg-emerald-600 hover:opacity-90 text-white font-bold px-2.5 py-1.5 rounded-lg text-[10px] transition shadow-sm">
                        <i class="fa-solid fa-check"></i> Confirm
                      </button>
                    </form>
                    <!-- Reject -->
                    <form method="post" onsubmit="return confirm('Mark this payment as failed? This cannot be undone.')">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" class="inline-flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-700 font-bold px-2.5 py-1.5 rounded-lg text-[10px] transition">
                        <i class="fa-solid fa-xmark"></i> Reject
                      </button>
                    </form>
                  </div>
                  <?php else: ?>
                  <span class="text-[10px] text-slate-300 font-semibold">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($onlinePayments)): ?>
              <tr>
                <td colspan="9" class="px-5 py-14 text-center">
                  <div class="flex flex-col items-center gap-3">
                    <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                      <i class="fa-solid fa-credit-card text-2xl text-slate-300"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-400">No online payments yet</p>
                    <p class="text-[11px] text-slate-400">Payments submitted through the citizen portal will appear here.</p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>

              <!-- No-results row -->
              <tr id="op-no-results" class="hidden">
                <td colspan="9" class="px-5 py-12 text-center">
                  <div class="flex flex-col items-center gap-3">
                    <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                      <i class="fa-solid fa-filter-circle-xmark text-2xl text-slate-300"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-400">No matching payments</p>
                    <button onclick="clearOPFilters()" class="inline-flex items-center gap-2 mt-1 bg-white border border-slate-200 text-slate-600 text-xs font-bold px-4 py-2 rounded-lg transition hover:bg-slate-50">
                      <i class="fa-solid fa-xmark"></i> Clear Filters
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>
function filterPayments() {
  const q      = document.getElementById('op-search').value.trim().toLowerCase();
  const status = document.getElementById('op-status').value.toLowerCase();
  const rows   = document.querySelectorAll('#op-tbody .op-row');
  const noRes  = document.getElementById('op-no-results');
  const badge  = document.getElementById('op-count-badge');
  const clear  = document.getElementById('op-clear-btn');

  let visible = 0;
  rows.forEach(r => {
    const matchText   = !q      || r.dataset.ref.includes(q) || r.dataset.citizen.includes(q);
    const matchStatus = !status || r.dataset.status === status;
    const show        = matchText && matchStatus;
    r.classList.toggle('hidden', !show);
    if (show) visible++;
  });

  noRes.classList.toggle('hidden', visible > 0);
  clear.classList.toggle('hidden', !q && !status);
  badge.textContent = visible + ' record' + (visible !== 1 ? 's' : '');
  badge.className   = 'inline-flex items-center text-[10px] font-black px-2 py-0.5 rounded-full '
    + (visible === 0 ? 'bg-red-50 text-red-500' : 'bg-slate-100 text-slate-600');
}

function clearOPFilters() {
  document.getElementById('op-search').value = '';
  document.getElementById('op-status').value = '';
  filterPayments();
}

function setStatusFilter(val) {
  const sel = document.getElementById('op-status');
  sel.value = (sel.value === val) ? '' : val; // toggle
  filterPayments();
}
</script>