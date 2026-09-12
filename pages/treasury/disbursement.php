<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Treasury / Disbursement';
$activePage = 'treasury-disbursement';
$errorMsg = null;
$successMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'create_voucher') {
            $amount = (float) ($_POST['amount'] ?? 0);
            if ($amount <= 0) throw new Exception('Amount must be greater than zero.');
          $purposeDocument = $treasuryService->saveVoucherPurposeDocument($_FILES['purpose_document'] ?? []);
            $createdVoucher = $treasuryService->createVoucher([
                'payee'      => trim($_POST['payee'] ?? ''),
                'purpose'    => trim($_POST['purpose'] ?? ''),
            'purpose_document' => $purposeDocument,
                'fund_id'    => $_POST['fund_id'] ?? '',
                'amount'     => $amount,
                'created_by' => $headerUser['full_name'] ?? null,
            ]);
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'module' => 'disbursement',
                    'action' => 'create',
                    'table_name' => 'tr_disbursements',
                    'record_id' => $createdVoucher['id'] ?? null,
                    'new_values' => json_encode($createdVoucher)
                ]);
            }
            
            $successMsg = 'Voucher submitted for release.';
        } elseif (($_POST['action'] ?? '') === 'release_voucher') {
          if (!$treasuryService->verifyReleaseCode((string) ($_POST['release_code'] ?? ''))) {
            throw new Exception('Invalid release confirmation code.');
          }
            $treasuryService->releaseVoucher($_POST['voucher_id']);
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'module' => 'disbursement',
                    'action' => 'disburse',
                    'table_name' => 'tr_disbursements',
                    'record_id' => $_POST['voucher_id'],
                    'new_values' => json_encode(['status' => 'disbursed'])
                ]);
            }
            
            $successMsg = 'Voucher released and fund debited.';
        }
        header('Location: disbursement.php?ok=1&msg=' . urlencode($successMsg));
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}
if (isset($_GET['ok']) && isset($_GET['msg'])) {
    $successMsg = $_GET['msg'];
}

try {
    $funds = $treasuryService->getFunds();
    $vouchers = $treasuryService->getAllVouchers();
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = []; $vouchers = [];
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
            <span class="text-brand-dark">Disbursement</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-money-bill-transfer text-brand-dark"></i>
            Treasury / Disbursement
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Fund custody, disbursement vouchers, and release processing.
          </p>
        </div>
      </div>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($successMsg): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-circle-check mt-0.5"></i><span><?= htmlspecialchars($successMsg) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($_GET['imported'])): ?>
      <div class="bg-sky-50 border border-sky-200 text-sky-700 rounded-xl p-4 text-xs font-medium flex items-start space-x-2">
        <i class="fa-solid fa-file-import mt-0.5"></i><span><?= htmlspecialchars($_GET['imported']) ?></span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-xs p-5 space-y-5 h-fit">
          <h2 class="text-sm font-extrabold text-slate-800">Fund Position</h2>
          <?php foreach ($funds as $f): ?>
          <div class="border border-slate-100 rounded-lg p-3.5 bg-slate-50/60">
            <div class="flex justify-between items-baseline">
              <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($f['name']) ?></span>
              <span class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($f['code']) ?></span>
            </div>
            <div class="text-lg font-mono font-black text-brand-dark mt-1"><?= $treasuryService->formatPeso($f['balance']) ?></div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($funds)): ?>
            <p class="text-xs text-slate-400">No fund data yet — run the SQL schema.</p>
          <?php endif; ?>
        </div>

        <form method="post" enctype="multipart/form-data" class="lg:col-span-3 bg-white border border-slate-200 rounded-xl shadow-xs p-5 space-y-4">
          <input type="hidden" name="action" value="create_voucher">
          <h2 class="text-sm font-extrabold text-slate-800 pb-1">New Disbursement Voucher</h2>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Payee</label>
            <input type="text" name="payee" required placeholder="e.g. ABC Construction Supply"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Purpose</label>
            <textarea name="purpose" rows="3" placeholder="e.g. Barangay road repair, materials"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition"></textarea>
            <p class="text-[11px] text-slate-400">Type the purpose, or attach a supporting purpose document below.</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Purpose document <span class="font-normal text-slate-400">(optional)</span></label>
            <input type="file" name="purpose_document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-xs text-slate-600 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
            <p class="text-[11px] text-slate-400">PDF, DOC, DOCX, JPG, or PNG up to 10 MB.</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Fund source</label>
              <select name="fund_id" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
                <?php foreach ($funds as $f): ?>
                  <option value="<?= htmlspecialchars($f['code']) ?>"><?= htmlspecialchars($f['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500">Amount (₱)</label>
              <input type="number" name="amount" min="1" step="0.01" required placeholder="0.00"
                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono">
            </div>
          </div>
          <button type="submit" class="py-3 px-5 bg-brand-dark hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
            Submit voucher
          </button>
          <p class="text-[11px] text-slate-400">New vouchers start as <strong>Pending</strong> until released below.</p>
        </form>
      </div>

      <div class="bg-white border border-slate-200 rounded-xl shadow-xs p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="text-sm font-extrabold text-slate-800">Import voucher data</h2>
            <p class="text-[11px] text-slate-400 mt-1">CSV, Excel, or JSON, up to 1,000 rows. Required: payee, purpose, amount.</p>
          </div>
          <form method="post" action="import-financial-data.php" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="target" value="disbursement">
            <input type="file" name="import_file" accept=".csv,.xlsx,.json" required class="max-w-xs text-xs text-slate-500">
            <button type="submit" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white font-bold px-3 py-2 rounded-lg text-xs transition"><i class="fa-solid fa-file-import"></i> Import</button>
          </form>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-xl shadow-xs">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-800">Disbursement Vouchers</h2>
          <span class="text-[11px] text-slate-400 font-semibold"><?= count($vouchers) ?> vouchers</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                <th class="px-5 py-3 font-bold">DV No.</th>
                <th class="px-5 py-3 font-bold">Payee</th>
                <th class="px-5 py-3 font-bold">Purpose</th>
                <th class="px-5 py-3 font-bold">Fund</th>
                <th class="px-5 py-3 font-bold text-right">Amount</th>
                <th class="px-5 py-3 font-bold">Status</th>
                <th class="px-5 py-3 font-bold"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($vouchers as $v): ?>
              <tr class="hover:bg-brand-light/40 transition">
                <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($v['dv_number'] ?? $v['voucher_no'] ?? '') ?></td>
                <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($v['payee'] ?? '') ?></td>
                <td class="px-5 py-3 text-slate-500">
                  <?= htmlspecialchars($v['purpose']) ?>
                  <?php if (!empty($v['purpose_document'])): ?>
                    <a href="<?= htmlspecialchars($v['purpose_document']) ?>" target="_blank" class="block text-[10px] text-brand-dark font-bold hover:underline mt-1"><i class="fa-solid fa-paperclip mr-1"></i>View attachment</a>
                  <?php endif; ?>
                </td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars(strtoupper($v['fund_code'] ?? ($v['fund_id'] ?? ''))) ?></td>
                <td class="px-5 py-3 text-right font-mono font-bold text-slate-800"><?= $treasuryService->formatPeso($v['amount']) ?></td>
                <td class="px-5 py-3">
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= strtolower($v['status'])==='pending' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' ?>"><?= htmlspecialchars($v['status']) ?></span>
                </td>
                <td class="px-5 py-3 text-right">
                  <?php if (strtolower($v['status']) === 'pending'): ?>
                  <button type="button" onclick="openReleaseModal(<?= (int) $v['id'] ?>, '<?= htmlspecialchars(addslashes((string) ($v['payee'] ?? ''))) ?>', '<?= number_format((float) $v['amount'], 2, '.', '') ?>')" class="text-[11px] font-bold text-brand-dark hover:underline">Release</button>
                  <?php else: ?>
                  <span class="text-[10px] text-slate-400"><?= $v['disbursement_date'] ? date('M j, Y', strtotime($v['disbursement_date'])) : '-' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($vouchers)): ?>
              <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No vouchers yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Transaction History -->
      <?php 
      $module = 'disbursement';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>

    <div id="releaseModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Authorization required</p>
            <h2 class="mt-1 text-lg font-black text-slate-900">Confirm voucher release</h2>
          </div>
          <button type="button" onclick="closeReleaseModal()" class="text-slate-400 hover:text-slate-700" title="Close">
            <i class="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>
        <p class="mt-4 text-sm leading-relaxed text-slate-600">Enter the designated release code to release <strong id="releaseModalAmount"></strong> to <strong id="releaseModalPayee"></strong>.</p>
        <form method="post" class="mt-5 space-y-4">
          <input type="hidden" name="action" value="release_voucher">
          <input type="hidden" name="voucher_id" id="releaseVoucherId">
          <div>
            <label for="releaseCode" class="mb-1.5 block text-xs font-bold text-slate-600">Confirmation code</label>
            <input type="password" name="release_code" id="releaseCode" inputmode="numeric" autocomplete="off" required maxlength="32"
              class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm tracking-[0.2em] text-slate-900 focus:border-brand-medium focus:outline-none focus:ring-2 focus:ring-brand-medium/20">
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" onclick="closeReleaseModal()" class="rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200">Cancel</button>
            <button type="submit" class="rounded-lg bg-brand-dark px-4 py-2.5 text-xs font-bold text-white hover:opacity-90">Confirm release</button>
          </div>
        </form>
      </div>
    </div>
    <script>
      function openReleaseModal(voucherId, payee, amount) {
        document.getElementById('releaseVoucherId').value = voucherId;
        document.getElementById('releaseModalPayee').textContent = payee;
        document.getElementById('releaseModalAmount').textContent = '₱' + amount;
        document.getElementById('releaseCode').value = '';
        const modal = document.getElementById('releaseModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('releaseCode').focus();
      }

      function closeReleaseModal() {
        const modal = document.getElementById('releaseModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>