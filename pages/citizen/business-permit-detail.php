<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Allow anonymous access for citizens
$pageTitle = 'Business Permit Application Details';
$errorMsg = null;
$successMsg = null;

$applicationNo = $_GET['app'] ?? '';
if (empty($applicationNo)) {
    header('Location: business-permit.php');
    exit;
}

try {
    $application = $treasuryService->getBusinessAppByNo($applicationNo);
    if (!$application) {
        throw new Exception('Application not found.');
    }
    
    $checklist = $treasuryService->getBusinessChecklist($application['transaction_type']);
    $documents = is_array($application['documents']) ? $application['documents'] : [];
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $application = null;
    $checklist = [];
    $documents = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_documents') {
    try {
        if (!$application) throw new Exception('Application not found.');
        
        $newDocs = $treasuryService->saveBusinessDocuments($application['application_no'], $_FILES, $checklist);
        $existingDocs = is_array($application['documents']) ? $application['documents'] : [];
        $allDocs = array_merge($existingDocs, $newDocs);
        $treasuryService->setBusinessAppStatus($application['id'], $application['status'], ['documents' => json_encode($allDocs)]);
        
        $successMsg = 'Documents uploaded successfully. ' . count($newDocs) . ' new document(s) added.';
        
        // Refresh application data
        $application = $treasuryService->getBusinessAppByNo($applicationNo);
        $documents = is_array($application['documents']) ? $application['documents'] : [];
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Business Permit Application Details - Caloocan City</title>
  <link rel="icon" type="image/png" href="../../assets/images/logo.png">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style type="text/tailwindcss">
    @theme {
      --color-brand-light: #EEF5FF;
      --color-brand-border: #B4D4FF;
      --color-brand-medium: #86B6F6;
      --color-brand-dark: #176B87;
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

  <!-- Header -->
  <nav class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <img src="../../assets/images/logo.png" alt="Civentral Logo" class="h-10 w-auto object-contain">
        <div class="flex flex-col">
          <span class="text-xl font-black text-brand-dark tracking-wider uppercase leading-none">CIVENTRAL</span>
          <span class="text-[10px] font-bold text-brand-medium tracking-widest uppercase mt-0.5">Caloocan City Portal</span>
        </div>
      </div>
      <a href="business-permit.php" class="text-sm font-semibold text-slate-600 hover:text-brand-dark transition">
        <i class="fa-solid fa-arrow-left mr-2"></i>Back to Applications
      </a>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-6">

    <?php if ($errorMsg): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 text-sm font-medium flex items-start space-x-2 mb-6 max-w-3xl mx-auto">
      <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($errorMsg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-4 text-sm font-medium flex items-start space-x-2 mb-6 max-w-3xl mx-auto">
      <i class="fa-solid fa-circle-check mt-0.5"></i><span><?= htmlspecialchars($successMsg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($application): ?>
    <div class="text-center mb-8">
      <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center justify-center gap-3">
        <i class="fa-solid fa-file-signature text-brand-dark"></i>
        Application Details
      </h1>
      <p class="text-slate-500 mt-2">Application Number: <span class="font-mono font-bold text-brand-dark"><?= htmlspecialchars($application['application_no']) ?></span></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
      <!-- Application Info -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6">
          <h2 class="text-lg font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Application Information</h2>
          
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-slate-400 text-xs mb-1">Business Name</p>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($application['business_name']) ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs mb-1">Owner Name</p>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($application['owner_name']) ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs mb-1">Barangay</p>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($application['barangay'] ?? 'N/A') ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs mb-1">Line of Business</p>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($application['line_of_business'] ?? 'N/A') ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs mb-1">Transaction Type</p>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($application['transaction_type']) ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs mb-1">Status</p>
              <?php
              $statusClass = 'bg-slate-100 text-slate-700';
              if ($application['status'] === 'Released') $statusClass = 'bg-emerald-100 text-emerald-700';
              elseif ($application['status'] === 'Paid') $statusClass = 'bg-blue-100 text-blue-700';
              elseif ($application['status'] === 'For Payment') $statusClass = 'bg-amber-100 text-amber-700';
              elseif ($application['status'] === 'Submitted') $statusClass = 'bg-slate-100 text-slate-700';
              ?>
              <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?= $statusClass ?>">
                <?= htmlspecialchars($application['status']) ?>
              </span>
            </div>
            <div>
              <p class="text-slate-400 text-xs mb-1">Submitted Date</p>
              <p class="font-semibold text-slate-800"><?= date('M j, Y g:i A', strtotime($application['created_at'])) ?></p>
            </div>
            <div>
              <p class="text-slate-400 text-xs mb-1">Permit Number</p>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($application['permit_no'] ?? 'N/A') ?></p>
            </div>
          </div>
        </div>

        <!-- Document Upload Section -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6">
          <h2 class="text-lg font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Upload Additional Documents</h2>
          
          <form method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload_documents">
            
            <p class="text-xs text-slate-500">Upload additional documents if requested by staff or if you need to add missing requirements.</p>
            
            <?php foreach ($checklist as $field => $label): ?>
            <div class="text-xs">
              <label class="flex items-start space-x-2 cursor-pointer">
                <input type="file" name="<?= $field ?>" class="hidden" onchange="this.nextElementSibling.textContent = this.files[0]?.name || 'Choose file...'">
                <span class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 transition w-full truncate">Choose file...</span>
              </label>
              <p class="text-slate-400 mt-0.5 ml-1"><?= htmlspecialchars($label) ?></p>
              <?php if (isset($documents[$field])): ?>
                <p class="text-emerald-600 text-xs mt-1 ml-1"><i class="fa-solid fa-check-circle mr-1"></i>Already uploaded: <?= htmlspecialchars($documents[$field]) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
              Upload Documents
            </button>
          </form>
        </div>

        <!-- Current Documents -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6">
          <h2 class="text-lg font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Current Documents</h2>
          
          <?php if (empty($documents)): ?>
            <p class="text-slate-400 text-sm">No documents uploaded yet.</p>
          <?php else: ?>
            <div class="space-y-2 text-sm">
              <?php foreach ($documents as $field => $filename): ?>
              <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                <div class="flex items-center gap-3">
                  <i class="fa-solid fa-file text-brand-medium"></i>
                  <div>
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($checklist[$field] ?? $field) ?></p>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($filename) ?></p>
                  </div>
                </div>
                <span class="text-emerald-600 text-xs font-semibold"><i class="fa-solid fa-check-circle mr-1"></i>Uploaded</span>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Status & Actions -->
      <div class="lg:col-span-1">
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6 mb-6">
          <h2 class="text-sm font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Application Status</h2>
          
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark flex-shrink-0">
                <span class="text-xs font-bold">1</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Submitted</h3>
                <p class="text-[11px] text-slate-500">Application received</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full <?= $application['status'] === 'Submitted' ? 'bg-slate-100 border-slate-200 text-slate-400' : 'bg-blue-100 border-blue-200 text-blue-600' ?> flex items-center justify-center flex-shrink-0">
                <span class="text-xs font-bold">2</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Document Review</h3>
                <p class="text-[11px] text-slate-500">Staff verifying documents</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full <?= $application['status'] === 'Submitted' || $application['status'] === 'For Payment' ? 'bg-slate-100 border-slate-200 text-slate-400' : 'bg-amber-100 border-amber-200 text-amber-600' ?> flex items-center justify-center flex-shrink-0">
                <span class="text-xs font-bold">3</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Payment</h3>
                <p class="text-[11px] text-slate-500">Pay required fees</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full <?= $application['status'] === 'Released' ? 'bg-emerald-100 border-emerald-200 text-emerald-600' : 'bg-slate-100 border-slate-200 text-slate-400' ?> flex items-center justify-center flex-shrink-0">
                <span class="text-xs font-bold">4</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Released</h3>
                <p class="text-[11px] text-slate-500">Permit issued</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6">
          <h2 class="text-sm font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Actions</h2>
          
          <div class="space-y-3">
            <?php if ($application['status'] === 'For Payment'): ?>
              <a href="online-payment.php" class="block w-full bg-brand-medium hover:opacity-90 text-white font-bold px-4 py-3 rounded-lg text-sm transition text-center">
                <i class="fa-solid fa-credit-card mr-2"></i>Pay Now
              </a>
            <?php endif; ?>
            
            <?php if ($application['status'] === 'Released'): ?>
              <button class="block w-full bg-emerald-600 hover:opacity-90 text-white font-bold px-4 py-3 rounded-lg text-sm transition">
                <i class="fa-solid fa-download mr-2"></i>Download Permit
              </button>
            <?php endif; ?>
            
            <button class="block w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-3 rounded-lg text-sm transition">
              <i class="fa-solid fa-print mr-2"></i>Print Application
            </button>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="text-center py-12">
      <i class="fa-solid fa-exclamation-circle text-4xl text-slate-400 mb-3"></i>
      <p class="text-slate-500">Application not found. <a href="business-permit.php" class="text-brand-medium font-semibold">Return to applications</a></p>
    </div>
    <?php endif; ?>

  </main>

  <footer class="bg-slate-900 text-white py-8 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center">
      <p class="text-sm text-slate-400">© 2026 City of Caloocan. All rights reserved.</p>
    </div>
  </footer>

</body>
</html>