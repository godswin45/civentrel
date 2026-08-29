<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Business Permit Transactions';
$activePage = 'treasury-business';
$errorMsg = null;
$successMsg = null;
$activeTab = $_GET['tab'] ?? 'renewal';
if (!in_array($activeTab, ['renewal', 'retirement'], true)) $activeTab = 'renewal';

$editingApp = null;
if (isset($_GET['edit'])) {
    $editingApp = $treasuryService->getBusinessApp($_GET['edit']);
    if (!$editingApp) {
        $errorMsg = 'Application not found.';
    } else {
        $activeTab = $editingApp['transaction_type'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'submit_application') {
            $type = $_POST['transaction_type'] === 'retirement' ? 'retirement' : 'renewal';
            foreach (['business_name', 'owner_name'] as $required) {
                if (trim($_POST[$required] ?? '') === '') throw new Exception('Please complete all required fields.');
            }
            $app = $treasuryService->createBusinessApp([
                'transaction_type' => $type,
                'business_name'    => trim($_POST['business_name']),
                'owner_name'       => trim($_POST['owner_name']),
                'barangay'         => trim($_POST['barangay'] ?? ''),
                'line_of_business' => trim($_POST['line_of_business'] ?? ''),
                'permit_no'        => trim($_POST['permit_no'] ?? ''),
                'fund_id'          => 'BSF',
                'files'            => $_FILES,
                'submitted_by'     => $headerUser['full_name'] ?? null,
            ]);
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'action' => 'create',
                    'table_name' => 'tr_business_apps',
                    'record_id' => $app['id'] ?? null,
                    'new_values' => json_encode($app)
                ]);
            }
            $successMsg = 'Application ' . $app['application_no'] . ' submitted for review.';
            header('Location: business.php?tab=' . $type . '&ok=1&msg=' . urlencode($successMsg));
            exit;
        } elseif (($_POST['action'] ?? '') === 'edit_application_details') {
            $app = $treasuryService->getBusinessApp((int) $_POST['app_id']);
            if (!$app) throw new Exception('Application not found.');
            foreach (['business_name', 'owner_name'] as $required) {
                if (trim($_POST[$required] ?? '') === '') throw new Exception('Business name and owner name are required.');
            }
            $treasuryService->setBusinessAppStatus($app['id'], $app['status'], [
                'business_name'    => trim($_POST['business_name']),
                'owner_name'       => trim($_POST['owner_name']),
                'barangay'         => trim($_POST['barangay'] ?? '') ?: null,
                'line_of_business' => trim($_POST['line_of_business'] ?? '') ?: null,
                'permit_no'        => trim($_POST['permit_no'] ?? '') ?: null,
            ]);
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'action' => 'update',
                    'table_name' => 'tr_business_apps',
                    'record_id' => $app['id'],
                    'new_values' => json_encode([
                        'business_name' => trim($_POST['business_name']),
                        'owner_name' => trim($_POST['owner_name']),
                        'barangay' => trim($_POST['barangay'] ?? '') ?: null,
                        'line_of_business' => trim($_POST['line_of_business'] ?? '') ?: null,
                        'permit_no' => trim($_POST['permit_no'] ?? '') ?: null,
                    ])
                ]);
            }
            
            $successMsg = 'Application ' . $app['application_no'] . ' updated.';
            header('Location: business.php?tab=' . $app['transaction_type'] . '&edit=' . $app['id'] . '&ok=1&msg=' . urlencode($successMsg));
            exit;
        } elseif (($_POST['action'] ?? '') === 'update_application') {
            $app = $treasuryService->getBusinessApp($_POST['app_id']);
            if (!$app) throw new Exception('Application not found.');
            $newDocs = $treasuryService->saveBusinessDocuments($app['application_no'], $_FILES, $treasuryService->getBusinessChecklist($app['transaction_type']));
            $existingDocs = is_array($app['documents']) ? $app['documents'] : [];
            $allDocs = array_merge($existingDocs, $newDocs);
            $treasuryService->setBusinessAppStatus($app['id'], $app['status'], ['documents' => json_encode($allDocs)]);
            
            // Log the transaction
            if ($auditService) {
                $auditService->logTransaction([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'username' => $headerUser['full_name'] ?? 'System',
                    'action' => 'update',
                    'table_name' => 'tr_business_apps',
                    'record_id' => $app['id'],
                    'new_values' => json_encode(['documents_updated' => count($newDocs)])
                ]);
            }
            
            $successMsg = 'Application ' . $app['application_no'] . ' updated with ' . count($newDocs) . ' new document(s).';
            header('Location: business.php?tab=' . $app['transaction_type'] . '&edit=' . $app['id'] . '&ok=1&msg=' . urlencode($successMsg));
            exit;
        } elseif (($_POST['action'] ?? '') === 'add_followup') {
            $app = $treasuryService->getBusinessApp($_POST['app_id']);
            if (!$app) throw new Exception('Application not found.');
            $existingNotes = $app['follow_up_notes'] ?? '';
            $newNote = date('M j, Y g:i A') . ' - ' . ($headerUser['full_name'] ?? 'Staff') . ": " . trim($_POST['followup_note']);
            $treasuryService->setBusinessAppStatus($app['id'], $app['status'], ['follow_up_notes' => $existingNotes . "\n" . $newNote]);
            $successMsg = 'Follow-up note added for application ' . $app['application_no'] . '.';
            header('Location: business.php?tab=' . $app['transaction_type'] . '&edit=' . $app['id'] . '&ok=1&msg=' . urlencode($successMsg));
            exit;
        } elseif (($_POST['action'] ?? '') === 'advance_status') {
            $app = $treasuryService->getBusinessApp($_POST['app_id']);
            if (!$app) throw new Exception('Application not found.');
            $next = ['Submitted' => 'For Payment', 'For Payment' => 'Released'][$app['status']] ?? $app['status'];
            $treasuryService->setBusinessAppStatus($app['id'], $next);
            $successMsg = 'Application ' . $app['application_no'] . ' moved to "' . $next . '".';
            header('Location: business.php?tab=' . $app['transaction_type'] . '&edit=' . $app['id'] . '&ok=1&msg=' . urlencode($successMsg));
            exit;
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}
if (isset($_GET['ok']) && isset($_GET['msg'])) $successMsg = $_GET['msg'];

try {
    $renewals = $treasuryService->listBusinessApps('renewal');
    $retirements = $treasuryService->listBusinessApps('retirement');
    $auditService = $auditService ?? null;
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $renewals = []; $retirements = [];
    $auditService = null;
}

$checklists = ['renewal' => $treasuryService->getBusinessChecklist('renewal'), 'retirement' => $treasuryService->getBusinessChecklist('retirement')];
$statusColor = ['Submitted' => 'bg-sky-50 text-sky-600', 'For Payment' => 'bg-amber-50 text-amber-600', 'Paid' => 'bg-emerald-50 text-emerald-600', 'Released' => 'bg-emerald-50 text-emerald-600'];
$reviewApp = $editingApp;
$reviewChecklist = $reviewApp ? ($checklists[$reviewApp['transaction_type']] ?? []) : [];
$reviewDocuments = [];
if ($reviewApp) {
    $docs = $reviewApp['documents'] ?? [];
    if (is_string($docs)) {
        $docs = json_decode($docs, true) ?: [];
    }
    $reviewDocuments = is_array($docs) ? $docs : [];
}
$nextStatusLabel = null;
if ($reviewApp) {
    $nextStatusLabel = ['Submitted' => 'For Payment', 'For Payment' => 'Released'][$reviewApp['status']] ?? null;
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
            <span class="text-brand-dark">Business Permit Transactions</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-file-signature text-brand-dark"></i>
            Business Permit Transactions
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Renewal and Retirement of Business (closure / resignation), per the Citizen's Charter — Business Permits and Licensing Office &amp; City Treasury Department.
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

      <!-- Tabs -->
      <div class="border-b border-slate-200">
        <nav class="flex space-x-8">
          <a href="?tab=renewal" class="<?= $activeTab === 'renewal' ? 'border-brand-medium text-brand-dark' : 'border-transparent text-slate-500 hover:text-slate-700' ?> border-b-2 pb-3 text-xs font-bold uppercase tracking-wider transition">Renewal</a>
          <a href="?tab=retirement" class="<?= $activeTab === 'retirement' ? 'border-brand-medium text-brand-dark' : 'border-transparent text-slate-500 hover:text-slate-700' ?> border-b-2 pb-3 text-xs font-bold uppercase tracking-wider transition">Retirement of Business (Closure / Resignation)</a>
        </nav>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Form -->
        <form method="post" enctype="multipart/form-data" class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 space-y-4 h-fit">
          <input type="hidden" name="action" value="submit_application">
          <input type="hidden" name="transaction_type" value="<?= $activeTab ?>">
          <h2 class="text-sm font-extrabold text-slate-800 pb-1">
            <?= $activeTab === 'renewal' ? 'Business Renewal Application' : 'Business Retirement Application' ?>
          </h2>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Business name</label>
            <input type="text" name="business_name" required placeholder="e.g. Dela Cruz Sari-Sari Store"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Owner name</label>
            <input type="text" name="owner_name" required placeholder="e.g. Marites Dela Cruz"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Barangay</label>
            <input type="text" name="barangay" placeholder="e.g. Barangay 123"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Line of business</label>
            <input type="text" name="line_of_business" placeholder="e.g. Retail trade"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <?php if ($activeTab === 'renewal'): ?>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500">Previous permit number</label>
            <input type="text" name="permit_no" placeholder="e.g. BP-2025-001234"
              class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>
          <?php endif; ?>

          <div class="space-y-2">
            <label class="text-xs font-semibold text-gray-500">Required documents</label>
            <?php foreach ($checklists[$activeTab] as $field => $label): ?>
            <div class="text-[11px]">
              <label class="flex items-start space-x-2 cursor-pointer">
                <input type="file" name="<?= $field ?>" class="hidden" onchange="this.nextElementSibling.textContent = this.files[0]?.name || 'Choose file...'">
                <span class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 transition w-full truncate">Choose file...</span>
              </label>
              <p class="text-slate-400 mt-0.5 ml-1"><?= htmlspecialchars($label) ?></p>
            </div>
            <?php endforeach; ?>
          </div>

          <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
            Submit application
          </button>
        </form>

        <!-- List -->
        <div class="lg:col-span-3 bg-white border border-slate-200/80 rounded-2xl shadow-xs">
          <div class="p-5 border-b border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-800">
              <?= $activeTab === 'renewal' ? 'Renewal Applications' : 'Retirement Applications' ?>
            </h2>
            <span class="text-[11px] text-slate-400"><?= count($activeTab === 'renewal' ? $renewals : $retirements) ?> applications</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
                  <th class="px-5 py-3 font-bold">App No.</th>
                  <th class="px-5 py-3 font-bold">Business</th>
                  <th class="px-5 py-3 font-bold">Owner</th>
                  <th class="px-5 py-3 font-bold">Status</th>
                  <th class="px-5 py-3 font-bold"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($activeTab === 'renewal' ? $renewals : $retirements as $app): ?>
                <tr class="hover:bg-brand-light/40 transition">
                  <td class="px-5 py-3 font-mono text-slate-500"><?= htmlspecialchars($app['application_no']) ?></td>
                  <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($app['business_name']) ?></td>
                  <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($app['owner_name']) ?></td>
                  <td class="px-5 py-3">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $statusColor[$app['status']] ?? 'bg-slate-50 text-slate-600' ?>"><?= htmlspecialchars($app['status']) ?></span>
                  </td>
                  <td class="px-5 py-3 text-right">
                    <a href="?tab=<?= $activeTab ?>&edit=<?= $app['id'] ?>" class="text-[11px] font-bold text-brand-dark hover:underline">Review</a>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($activeTab === 'renewal' ? $renewals : $retirements)): ?>
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No applications yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <?php if ($reviewApp): ?>
      <div id="applicationReviewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto border border-slate-200">
          <div class="sticky top-0 bg-white border-b border-slate-100 px-6 py-4 flex items-start justify-between gap-4 z-10">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Application Review</p>
              <h3 class="text-lg font-black text-slate-900 mt-1"><?= htmlspecialchars($reviewApp['application_no']) ?></h3>
              <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $statusColor[$reviewApp['status']] ?? 'bg-slate-50 text-slate-600' ?>"><?= htmlspecialchars($reviewApp['status']) ?></span>
                <span class="text-[11px] text-slate-500 capitalize"><?= htmlspecialchars($reviewApp['transaction_type']) ?></span>
              </div>
            </div>
            <a href="?tab=<?= htmlspecialchars($activeTab) ?>" class="text-slate-400 hover:text-slate-600 p-1" title="Close">
              <i class="fa-solid fa-times text-lg"></i>
            </a>
          </div>

          <div class="p-6 space-y-6">
            <!-- Edit application details -->
            <section class="space-y-3">
              <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Application Details</h4>
              <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="hidden" name="action" value="edit_application_details">
                <input type="hidden" name="app_id" value="<?= (int) $reviewApp['id'] ?>">
                <div class="space-y-1 md:col-span-2">
                  <label class="text-xs font-semibold text-gray-500">Business name</label>
                  <input type="text" name="business_name" required value="<?= htmlspecialchars($reviewApp['business_name']) ?>"
                    class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium">
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-500">Owner name</label>
                  <input type="text" name="owner_name" required value="<?= htmlspecialchars($reviewApp['owner_name']) ?>"
                    class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium">
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-500">Barangay</label>
                  <input type="text" name="barangay" value="<?= htmlspecialchars($reviewApp['barangay'] ?? '') ?>"
                    class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium">
                </div>
                <div class="space-y-1 md:col-span-2">
                  <label class="text-xs font-semibold text-gray-500">Line of business</label>
                  <input type="text" name="line_of_business" value="<?= htmlspecialchars($reviewApp['line_of_business'] ?? '') ?>"
                    class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium">
                </div>
                <?php if ($reviewApp['transaction_type'] === 'renewal'): ?>
                <div class="space-y-1 md:col-span-2">
                  <label class="text-xs font-semibold text-gray-500">Previous permit number</label>
                  <input type="text" name="permit_no" value="<?= htmlspecialchars($reviewApp['permit_no'] ?? '') ?>"
                    class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium">
                </div>
                <?php endif; ?>
                <div class="md:col-span-2 flex justify-end">
                  <button type="submit" class="py-2.5 px-4 bg-brand-dark hover:opacity-90 text-white font-bold rounded-lg text-xs transition">Save changes</button>
                </div>
              </form>
            </section>

            <!-- Submitted documents -->
            <section class="space-y-3">
              <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Submitted Documents</h4>
              <?php if (!empty($reviewDocuments)): ?>
              <ul class="space-y-2">
                <?php foreach ($reviewDocuments as $doc): ?>
                <li class="flex items-center justify-between gap-3 p-3 bg-slate-50 border border-slate-100 rounded-lg text-xs">
                  <div class="min-w-0">
                    <p class="font-semibold text-slate-700 truncate"><?= htmlspecialchars($doc['label'] ?? $doc['field'] ?? 'Document') ?></p>
                    <p class="text-slate-400 truncate"><?= htmlspecialchars($doc['original'] ?? $doc['filename'] ?? '') ?></p>
                  </div>
                  <?php if (!empty($doc['filename'])): ?>
                  <a href="uploads/<?= htmlspecialchars(preg_replace('/[^A-Za-z0-9\-]/', '', $reviewApp['application_no'])) ?>/<?= htmlspecialchars($doc['filename']) ?>" target="_blank" class="shrink-0 text-brand-dark font-bold hover:underline">Open</a>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php else: ?>
              <p class="text-xs text-slate-400 bg-slate-50 border border-slate-100 rounded-lg p-3">No documents uploaded yet.</p>
              <?php endif; ?>

              <form method="post" enctype="multipart/form-data" class="space-y-2 border border-dashed border-slate-200 rounded-xl p-4 bg-slate-50/50">
                <input type="hidden" name="action" value="update_application">
                <input type="hidden" name="app_id" value="<?= (int) $reviewApp['id'] ?>">
                <p class="text-xs font-semibold text-slate-600">Upload additional documents</p>
                <?php foreach ($reviewChecklist as $field => $label): ?>
                <div class="text-[11px]">
                  <label class="flex items-start space-x-2 cursor-pointer">
                    <input type="file" name="<?= htmlspecialchars($field) ?>" class="hidden" onchange="this.nextElementSibling.textContent = this.files[0]?.name || 'Choose file...'">
                    <span class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 transition w-full truncate">Choose file...</span>
                  </label>
                  <p class="text-slate-400 mt-0.5 ml-1"><?= htmlspecialchars($label) ?></p>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="py-2 px-4 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold rounded-lg text-xs transition">Upload files</button>
              </form>
            </section>

            <!-- Follow-up for submitter -->
            <section class="space-y-3">
              <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Follow-up Requirements</h4>
              <p class="text-[11px] text-slate-500">Add notes the applicant must address (missing documents, corrections, etc.).</p>
              <?php if (!empty(trim($reviewApp['follow_up_notes'] ?? ''))): ?>
              <div class="bg-amber-50 border border-amber-100 rounded-lg p-3 text-xs text-amber-900 whitespace-pre-line"><?= htmlspecialchars(trim($reviewApp['follow_up_notes'])) ?></div>
              <?php else: ?>
              <p class="text-xs text-slate-400">No follow-up notes yet.</p>
              <?php endif; ?>
              <form method="post" class="space-y-2">
                <input type="hidden" name="action" value="add_followup">
                <input type="hidden" name="app_id" value="<?= (int) $reviewApp['id'] ?>">
                <textarea name="followup_note" required rows="3" placeholder="e.g. Please submit updated Barangay Clearance and Fire Safety Certificate."
                  class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium resize-none"></textarea>
                <button type="submit" class="py-2.5 px-4 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg text-xs transition">Send follow-up note</button>
              </form>
            </section>

            <!-- Status actions -->
            <section class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
              <p class="text-[11px] text-slate-500">
                Submitted by <?= htmlspecialchars($reviewApp['submitted_by'] ?? 'Unknown') ?>
                <?php if (!empty($reviewApp['created_at'])): ?>
                  · <?= date('M j, Y g:i A', strtotime($reviewApp['created_at'])) ?>
                <?php endif; ?>
              </p>
              <?php if ($nextStatusLabel): ?>
              <form method="post" onsubmit="return confirm('Move this application to &quot;<?= htmlspecialchars($nextStatusLabel) ?>&quot;?');">
                <input type="hidden" name="action" value="advance_status">
                <input type="hidden" name="app_id" value="<?= (int) $reviewApp['id'] ?>">
                <button type="submit" class="py-2.5 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-xs transition">
                  Move to <?= htmlspecialchars($nextStatusLabel) ?>
                </button>
              </form>
              <?php endif; ?>
            </section>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <!-- Transaction History -->
      <?php 
      $module = 'business_permit';
      $limit = 5;
      include __DIR__ . '/../../includes/transaction_history.php';
      ?>
    </main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>