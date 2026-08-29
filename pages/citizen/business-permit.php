<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Allow anonymous access for citizens
$pageTitle = 'Business Permit Application';
$activePage = 'citizen-business-permit';
$errorMsg = null;
$successMsg = null;
$createdApplication = null;

// Get checklists from treasury service
$renewalChecklist = $treasuryService->getBusinessChecklist('renewal');
$retirementChecklist = $treasuryService->getBusinessChecklist('retirement');

// Get user's applications (if logged in, otherwise show empty)
$userApplications = [];
if (!empty($_SESSION['user_id']) || !empty($_SESSION['employee_id'])) {
    try {
        $userApplications = $treasuryService->listBusinessApps('renewal');
        $retirements = $treasuryService->listBusinessApps('retirement');
        $userApplications = array_merge($userApplications, $retirements);
    } catch (Exception $e) {
        $userApplications = [];
    }
}

// Function to mask application number for security
function maskApplicationNumber($appNo) {
    if (strlen($appNo) <= 6) return '***';
    $parts = explode('-', $appNo);
    if (count($parts) >= 3) {
        return $parts[0] . '-******-' . end($parts);
    }
    return substr($appNo, 0, 3) . '******' . substr($appNo, -3);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_application') {
    try {
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
            'submitted_by'     => trim($_POST['owner_name']) ?? 'Citizen Applicant',
        ]);

        $createdApplication = $app;
        $successMsg = 'Business permit application submitted successfully! Your application number is: ' . $app['application_no'];
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

try {
    $funds = $treasuryService->getFunds();
} catch (Exception $e) {
    $errorMsg = $errorMsg ?? $e->getMessage();
    $funds = [];
}

// Citizen-friendly layout (no admin sidebar)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Business Permit Application - Caloocan City</title>
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
      <a href="../../index.php" class="text-sm font-semibold text-slate-600 hover:text-brand-dark transition">
        <i class="fa-solid fa-arrow-left mr-2"></i>Back to Home
      </a>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-6">

    <div class="text-center mb-8">
      <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center justify-center gap-3">
        <i class="fa-solid fa-file-signature text-brand-dark"></i>
        Business Permit Application
      </h1>
      <p class="text-slate-500 mt-2 max-w-2xl mx-auto">
        Apply for business permit renewal or retirement online. Upload required documents and track your application status.
      </p>
    </div>

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

    <?php if ($createdApplication): ?>
    <div class="bg-gradient-to-r from-brand-dark to-slate-900 text-white rounded-2xl p-6 shadow-md mb-8 max-w-3xl mx-auto">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold">Application Submitted Successfully!</h2>
          <p class="text-brand-light/80 text-sm mt-1">Your application number is:</p>
          <p class="text-2xl font-mono font-bold mt-2 text-brand-light"><?= htmlspecialchars($createdApplication['application_no']) ?></p>
        </div>
        <div class="text-right">
          <i class="fa-solid fa-circle-check text-4xl text-emerald-400"></i>
        </div>
      </div>
      <div class="mt-4 pt-4 border-t border-white/20">
        <p class="text-sm text-brand-light/90 mb-2"><i class="fa-solid fa-exclamation-triangle mr-2"></i><strong>IMPORTANT:</strong> Please save or remember your application number. You will need it to access your application and upload additional documents.</p>
        <div class="flex gap-3 mt-3">
          <button onclick="copyApplicationNumber('<?= htmlspecialchars($createdApplication['application_no']) ?>')" class="bg-white text-brand-dark hover:bg-brand-light font-bold px-4 py-2 rounded-lg text-sm transition flex items-center gap-2">
            <i class="fa-solid fa-copy"></i> Copy Number
          </button>
          <button onclick="showApplicationAccessModal()" class="bg-brand-medium hover:opacity-90 text-white font-bold px-4 py-2 rounded-lg text-sm transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-right"></i> Access Application
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="border-b border-slate-200 max-w-4xl mx-auto">
      <nav class="flex space-x-8">
        <button onclick="switchTab('renewal')" id="tab-renewal" class="border-brand-medium text-brand-dark border-b-2 pb-3 text-sm font-bold uppercase tracking-wider transition cursor-pointer">Renewal</button>
        <button onclick="switchTab('retirement')" id="tab-retirement" class="border-transparent text-slate-500 hover:text-slate-700 border-b-2 pb-3 text-sm font-bold uppercase tracking-wider transition cursor-pointer">Retirement of Business (Closure / Resignation)</button>
      </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
      <!-- Application Form -->
      <div class="lg:col-span-2">
        <form method="post" enctype="multipart/form-data" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6 space-y-4">
          <input type="hidden" name="action" value="submit_application">
          <input type="hidden" name="transaction_type" id="transaction_type" value="renewal">
          
          <h2 class="text-lg font-extrabold text-slate-800 pb-1" id="form-title">Business Renewal Application</h2>

          <div class="space-y-2">
            <label class="text-sm font-semibold text-gray-700">Business name</label>
            <input type="text" name="business_name" required placeholder="e.g. Dela Cruz Sari-Sari Store"
              class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-2">
            <label class="text-sm font-semibold text-gray-700">Owner name</label>
            <input type="text" name="owner_name" required placeholder="e.g. Marites Dela Cruz"
              class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-2">
            <label class="text-sm font-semibold text-gray-700">Barangay</label>
            <input type="text" name="barangay" placeholder="e.g. Barangay 123"
              class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-2">
            <label class="text-sm font-semibold text-gray-700">Line of business</label>
            <input type="text" name="line_of_business" placeholder="e.g. Retail trade"
              class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-2" id="permit_no_field">
            <label class="text-sm font-semibold text-gray-700">Previous permit number</label>
            <input type="text" name="permit_no" placeholder="e.g. BP-2025-001234"
              class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition">
          </div>

          <div class="space-y-2">
            <label class="text-sm font-semibold text-gray-700">Required documents</label>
            <p class="text-xs text-slate-500 mb-2">Please upload clear scans or photos of the following documents:</p>
            
            <div id="renewal-checklist">
              <?php foreach ($renewalChecklist as $field => $label): ?>
              <div class="text-xs">
                <label class="flex items-start space-x-2 cursor-pointer">
                  <input type="file" name="<?= $field ?>" class="hidden" onchange="this.nextElementSibling.textContent = this.files[0]?.name || 'Choose file...'">
                  <span class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 transition w-full truncate">Choose file...</span>
                </label>
                <p class="text-slate-400 mt-0.5 ml-1"><?= htmlspecialchars($label) ?></p>
              </div>
              <?php endforeach; ?>
            </div>

            <div id="retirement-checklist" class="hidden">
              <?php foreach ($retirementChecklist as $field => $label): ?>
              <div class="text-xs">
                <label class="flex items-start space-x-2 cursor-pointer">
                  <input type="file" name="<?= $field ?>" class="hidden" onchange="this.nextElementSibling.textContent = this.files[0]?.name || 'Choose file...'">
                  <span class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 transition w-full truncate">Choose file...</span>
                </label>
                <p class="text-slate-400 mt-0.5 ml-1"><?= htmlspecialchars($label) ?></p>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <button type="submit" class="w-full py-3 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition shadow-sm focus:outline-none">
            Submit Application
          </button>
        </form>
      </div>

      <!-- Information Panel -->
      <div class="lg:col-span-1">
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6 mb-6">
          <h2 class="text-sm font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Application Process</h2>
          
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark flex-shrink-0">
                <span class="text-xs font-bold">1</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Submit Application</h3>
                <p class="text-[11px] text-slate-500">Fill form and upload documents online</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full bg-blue-100 border border-blue-200 flex items-center justify-center text-blue-600 flex-shrink-0">
                <span class="text-xs font-bold">2</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Document Review</h3>
                <p class="text-[11px] text-slate-500">Staff verify your documents</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full bg-amber-100 border border-amber-200 flex items-center justify-center text-amber-600 flex-shrink-0">
                <span class="text-xs font-bold">3</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Payment</h3>
                <p class="text-[11px] text-slate-500">Pay fees via GCash/Maya online</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="h-8 w-8 rounded-full bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-600 flex-shrink-0">
                <span class="text-xs font-bold">4</span>
              </div>
              <div>
                <h3 class="text-xs font-bold text-slate-800">Approval</h3>
                <p class="text-[11px] text-slate-500">Receive your permit (digital or physical)</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6">
          <h2 class="text-sm font-extrabold text-slate-800 pb-3 border-b border-slate-100 mb-4">Important Notes</h2>
          
          <div class="space-y-3 text-xs text-slate-600">
            <div class="flex items-start gap-2">
              <i class="fa-solid fa-wifi text-brand-medium mt-0.5"></i>
              <p><strong>Fully Online:</strong> Submit application, upload documents, and pay fees - all online!</p>
            </div>
            <div class="flex items-start gap-2">
              <i class="fa-solid fa-info-circle text-brand-medium mt-0.5"></i>
              <p><strong>Barangay Clearance:</strong> Only physical document needed. Get this from your barangay before applying.</p>
            </div>
            <div class="flex items-start gap-2">
              <i class="fa-solid fa-file-pdf text-brand-medium mt-0.5"></i>
              <p><strong>Document Format:</strong> Upload clear scans or photos in PDF, JPG, or PNG format.</p>
            </div>
            <div class="flex items-start gap-2">
              <i class="fa-solid fa-clock text-brand-medium mt-0.5"></i>
              <p><strong>Processing Time:</strong> Applications are typically processed within 3-5 business days.</p>
            </div>
            <div class="flex items-start gap-2">
              <i class="fa-solid fa-credit-card text-brand-medium mt-0.5"></i>
              <p><strong>Payment:</strong> Pay fees via GCash/Maya when your application status is "For Payment".</p>
            </div>
            <div class="flex items-start gap-2">
              <i class="fa-solid fa-download text-brand-medium mt-0.5"></i>
              <p><strong>Permit Pickup:</strong> Final permit can be picked up at city hall or downloaded digitally.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Application Access Section -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs max-w-6xl mx-auto">
      <div class="p-5 border-b border-slate-100">
        <h2 class="text-sm font-extrabold text-slate-800">My Applications</h2>
        <span class="text-[11px] text-slate-400"><?= count($userApplications) ?> total applications</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50">
              <th class="px-5 py-3 font-bold">Application No.</th>
              <th class="px-5 py-3 font-bold">Business Name</th>
              <th class="px-5 py-3 font-bold">Type</th>
              <th class="px-5 py-3 font-bold">Status</th>
              <th class="px-5 py-3 font-bold">Submitted</th>
              <th class="px-5 py-3 font-bold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($userApplications as $app): ?>
            <tr class="hover:bg-brand-light/40 transition">
              <td class="px-5 py-3 font-mono text-slate-500"><?= maskApplicationNumber($app['application_no']) ?></td>
              <td class="px-5 py-3 font-semibold text-slate-700"><?= htmlspecialchars($app['business_name']) ?></td>
              <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($app['transaction_type']) ?></td>
              <td class="px-5 py-3">
                <?php
                $statusClass = 'bg-slate-100 text-slate-700';
                if ($app['status'] === 'Released') $statusClass = 'bg-emerald-100 text-emerald-700';
                elseif ($app['status'] === 'Paid') $statusClass = 'bg-blue-100 text-blue-700';
                elseif ($app['status'] === 'For Payment') $statusClass = 'bg-amber-100 text-amber-700';
                elseif ($app['status'] === 'Submitted') $statusClass = 'bg-slate-100 text-slate-700';
                ?>
                <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase <?= $statusClass ?>">
                  <?= htmlspecialchars($app['status']) ?>
                </span>
              </td>
              <td class="px-5 py-3 text-slate-500"><?= date('M j, Y g:i A', strtotime($app['created_at'])) ?></td>
              <td class="px-5 py-3">
                <?php if ($app['status'] === 'For Payment'): ?>
                  <a href="../../pages/citizen/online-payment.php" class="bg-brand-medium hover:opacity-90 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition">
                    Pay Now
                  </a>
                <?php else: ?>
                  <button class="text-brand-medium text-xs font-semibold hover:underline" onclick="showApplicationAccessModal()">Access Application</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($userApplications)): ?>
            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No applications yet. Submit your first business permit application above.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Application Access Modal -->
      <div id="applicationAccessModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4">
          <h3 class="text-lg font-bold text-slate-800 mb-4">Access Your Application</h3>
          <p class="text-sm text-slate-600 mb-4">Enter your application number to access your application details and upload additional documents.</p>
          
          <div class="space-y-2 mb-4">
            <label class="text-sm font-semibold text-gray-700">Application Number</label>
            <input type="text" id="applicationNumberInput" placeholder="e.g. BA-2026-34055E" 
              class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono uppercase">
          </div>
          
          <div class="flex gap-3">
            <button onclick="hideApplicationAccessModal()" class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-sm transition">
              Cancel
            </button>
            <button onclick="accessApplication()" class="flex-1 py-2.5 px-4 bg-brand-medium hover:opacity-90 text-white font-bold rounded-lg text-sm transition">
              Access
            </button>
          </div>
        </div>
      </div>

      <!-- Quick Access Section -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs max-w-6xl mx-auto mt-6">
        <div class="p-5 border-b border-slate-100">
          <h2 class="text-sm font-extrabold text-slate-800">Quick Access</h2>
          <span class="text-[11px] text-slate-400">Enter your full application number to access details and upload documents</span>
        </div>
        <div class="p-6">
          <div class="flex gap-4 items-center">
            <div class="flex-1">
              <input type="text" id="quickAccessInput" placeholder="Enter your full application number (e.g. BA-2026-34055E)" 
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition font-mono uppercase">
            </div>
            <button onclick="quickAccessApplication()" class="bg-brand-medium hover:opacity-90 text-white font-bold px-6 py-3 rounded-lg text-sm transition">
              <i class="fa-solid fa-search mr-2"></i>Access Application
            </button>
          </div>
          <p class="text-xs text-slate-500 mt-3"><i class="fa-solid fa-info-circle mr-1"></i>Enter your complete application number (shown in the success message after submission) to access your application details.</p>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-slate-900 text-white py-8 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center">
      <p class="text-sm text-slate-400">© 2026 City of Caloocan. All rights reserved.</p>
    </div>
  </footer>

  <script>
  function switchTab(tab) {
    const transactionType = document.getElementById('transaction_type');
    const formTitle = document.getElementById('form-title');
    const permitNoField = document.getElementById('permit_no_field');
    const renewalChecklist = document.getElementById('renewal-checklist');
    const retirementChecklist = document.getElementById('retirement-checklist');
    const tabRenewal = document.getElementById('tab-renewal');
    const tabRetirement = document.getElementById('tab-retirement');

    if (tab === 'renewal') {
      transactionType.value = 'renewal';
      formTitle.textContent = 'Business Renewal Application';
      permitNoField.classList.remove('hidden');
      renewalChecklist.classList.remove('hidden');
      retirementChecklist.classList.add('hidden');
      tabRenewal.classList.add('border-brand-medium', 'text-brand-dark');
      tabRenewal.classList.remove('border-transparent', 'text-slate-500');
      tabRetirement.classList.remove('border-brand-medium', 'text-brand-dark');
      tabRetirement.classList.add('border-transparent', 'text-slate-500');
    } else {
      transactionType.value = 'retirement';
      formTitle.textContent = 'Business Retirement Application';
      permitNoField.classList.add('hidden');
      renewalChecklist.classList.add('hidden');
      retirementChecklist.classList.remove('hidden');
      tabRetirement.classList.add('border-brand-medium', 'text-brand-dark');
      tabRetirement.classList.remove('border-transparent', 'text-slate-500');
      tabRenewal.classList.remove('border-brand-medium', 'text-brand-dark');
      tabRenewal.classList.add('border-transparent', 'text-slate-500');
    }
  }

  function showApplicationAccessModal() {
    document.getElementById('applicationAccessModal').classList.remove('hidden');
  }

  function hideApplicationAccessModal() {
    document.getElementById('applicationAccessModal').classList.add('hidden');
    document.getElementById('applicationNumberInput').value = '';
  }

  function accessApplication() {
    const appNo = document.getElementById('applicationNumberInput').value.trim().toUpperCase();
    if (appNo) {
      window.location.href = 'business-permit-detail.php?app=' + appNo;
    } else {
      alert('Please enter your application number.');
    }
  }

  function quickAccessApplication() {
    const appNo = document.getElementById('quickAccessInput').value.trim().toUpperCase();
    if (appNo) {
      window.location.href = 'business-permit-detail.php?app=' + appNo;
    } else {
      alert('Please enter your application number.');
    }
  }

  function copyApplicationNumber(appNo) {
    navigator.clipboard.writeText(appNo).then(() => {
      alert('Application number copied to clipboard: ' + appNo);
    }).catch(() => {
      alert('Failed to copy. Please manually copy: ' + appNo);
    });
  }
  </script>
</body>
</html>