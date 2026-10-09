<?php
$basePath = '../../';
include '../../includes/header.php';
include '../../includes/sidebar.php';

$canEdit = false;
if (!empty($headerUser['is_superadmin']) || !empty($headerUser['is_global_access'])) {
    $canEdit = true;
} else {
    $permsMap = $_SESSION['user_permissions_map'] ?? [];
    if (isset($permsMap['citizen directory']) && in_array('EDIT', $permsMap['citizen directory'])) {
        $canEdit = true;
    }
}
?>
<script>
  window.canEditCitizenDirectory = <?php echo json_encode($canEdit); ?>;
</script>


<main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

  <!-- Breadcrumb -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
    <div class="space-y-1">
      <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
        <span>Citizen Management</span>
        <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
        <span class="text-brand-dark">Citizen Directory</span>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
        <i class="fa-solid fa-address-book text-brand-dark"></i>
        Citizen Account Directory
      </h1>
      <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
        Administer registered citizen portal accounts, monitor active sessions, manage security locks, and reset credentials.
      </p>
    </div>

    <!-- Export -->
    <div class="shrink-0">
      <button onclick="exportCitizensCsv()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 cursor-pointer transition shadow-xs">
        <i class="fa-solid fa-file-excel"></i>
        <span>Export Excel</span>
      </button>
    </div>
  </div>

  <!-- FULL CONTENT SKELETON LOADER -->
  <div id="citizenSkeleton" class="space-y-6 transition-all duration-500 opacity-100 pointer-events-auto">
    <!-- Metric Cards Skeleton -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php for($k=0; $k<4; $k++): ?>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-2 w-full">
          <div class="skeleton-loader h-3 w-28 rounded-md"></div>
          <div class="skeleton-loader h-7 w-20 rounded-lg"></div>
        </div>
        <div class="skeleton-loader h-10 w-10 rounded-lg shrink-0 ml-3"></div>
      </div>
      <?php endfor; ?>
    </div>

    <!-- Filter Control Skeleton -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="skeleton-loader h-4 w-40 rounded-md"></div>
        <div class="skeleton-loader h-4 w-24 rounded-md"></div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="skeleton-loader h-9 w-full rounded-xl"></div>
        <div class="skeleton-loader h-9 w-full rounded-xl"></div>
        <div class="skeleton-loader h-9 w-full rounded-xl"></div>
        <div class="skeleton-loader h-9 w-full rounded-xl"></div>
      </div>
    </div>

    <!-- Datatable Skeleton -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-20 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-36 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-28 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-24 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-24 rounded-md"></div></th>
              <th class="px-6 py-4 text-right"><div class="skeleton-loader h-3 w-16 rounded-md ml-auto"></div></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100/80">
            <?php for($i=0; $i<5; $i++): ?>
            <tr class="animate-pulse">
              <td class="px-6 py-4"><div class="skeleton-loader h-3.5 w-24 rounded-md"></div></td>
              <td class="px-6 py-4">
                <div class="space-y-1">
                  <div class="skeleton-loader h-3.5 w-36 rounded-md"></div>
                  <div class="skeleton-loader h-2.5 w-24 rounded-md"></div>
                </div>
              </td>
              <td class="px-6 py-4"><div class="skeleton-loader h-3.5 w-28 rounded-md"></div></td>
              <td class="px-6 py-4"><div class="skeleton-loader h-5 w-20 rounded-full"></div></td>
              <td class="px-6 py-4"><div class="skeleton-loader h-3.5 w-24 rounded-md"></div></td>
              <td class="px-6 py-4 text-right"><div class="skeleton-loader h-7 w-20 rounded-lg ml-auto"></div></td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- REAL PAGE CONTENT -->
  <div id="citizenRealContent" class="space-y-6 hidden opacity-0 transition-all duration-700 ease-out transform translate-y-2">

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-[#0f172a]"></div>
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Citizens</span>
          <h3 class="text-2xl font-black text-slate-900 tracking-tight" id="metricTotal">0</h3>
        </div>
        <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-450 group-hover:bg-slate-100 transition">
          <i class="fa-solid fa-users text-sm"></i>
        </div>
      </div>
      
      <!-- Active  -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Active Accounts</span>
          <h3 class="text-2xl font-black text-slate-900 tracking-tight" id="metricActive">0</h3>
        </div>
        <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-emerald-500 group-hover:bg-emerald-50 transition">
          <i class="fa-solid fa-circle-user text-sm"></i>
        </div>
      </div>

      <!-- Inactive -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-slate-400"></div>
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Inactive Accounts</span>
          <h3 class="text-2xl font-black text-slate-900 tracking-tight" id="metricInactive">0</h3>
        </div>
        <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-slate-100 transition">
          <i class="fa-solid fa-user-slash text-sm"></i>
        </div>
      </div>

      <!-- Locked -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-rose-500"></div>
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Locked Accounts</span>
          <h3 class="text-2xl font-black text-slate-900 tracking-tight" id="metricLocked">0</h3>
        </div>
        <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-rose-500 group-hover:bg-rose-50 transition">
          <i class="fa-solid fa-user-lock text-sm"></i>
        </div>
      </div>
    </div>

    <!-- Advanced Control  -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center space-x-2">
          <i class="fa-solid fa-sliders text-brand-dark text-xs"></i>
          <h3 class="text-xs font-black uppercase tracking-wider text-slate-700">Advanced Filter Controls</h3>
        </div>
        <button onclick="resetFilters()" class="text-slate-400 hover:text-rose-600 text-xs font-bold transition flex items-center gap-1 cursor-pointer">
          <i class="fa-solid fa-trash-can text-[10px]"></i>
          <span>Reset Filters</span>
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input type="text" id="citizenSearch" oninput="filterCitizenTable()" placeholder="Search ID, name, email..." class="pl-8 pr-3 py-2 border border-slate-200 rounded-xl text-xs w-full bg-slate-50/50 focus:bg-white focus:outline-none focus:border-brand-medium transition">
        </div>

        <!-- Dropdown -->
        <select id="statusFilter" onchange="filterCitizenTable()" class="border border-slate-200 rounded-xl px-3 py-2 text-xs w-full bg-slate-50/50 focus:bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-semibold text-slate-650">
          <option value="All">All Statuses</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Locked">Locked</option>
        </select>

        <!-- Barangay Dropdown -->
        <select id="barangayFilter" onchange="filterCitizenTable()" class="border border-slate-200 rounded-xl px-3 py-2 text-xs w-full bg-slate-50/50 focus:bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-semibold text-slate-650">
          <option value="All">All Barangays</option>
          <option value="Barangay 171 (Bagumbong)">Barangay 171 (Bagumbong)</option>
          <option value="Barangay 178 (Camarin)">Barangay 178 (Camarin)</option>
          <option value="Barangay 8 (Tondo Border Area)">Barangay 8 (Tondo Border Area)</option>
        </select>

        <!-- Registration Date -->
        <input type="date" id="dateFilter" onchange="filterCitizenTable()" class="border border-slate-200 rounded-xl px-3 py-2 text-xs w-full bg-slate-50/50 focus:bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-semibold text-slate-650">
      </div>
    </div>

    <!-- Citizen Roster -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
              <th class="px-6 py-4">Citizen ID</th>
              <th class="px-6 py-4">Full Name & Contact</th>
              <th class="px-6 py-4">Barangay</th>
              <th class="px-6 py-4">Account Status</th>
              <th class="px-6 py-4">Activity Logs</th>
              <?php if ($canEdit): ?>
                <th class="px-6 py-4 text-right">Actions</th>
              <?php else: ?>
                <th class="px-6 py-4 text-right">View Details</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody id="citizenTableBody" class="divide-y divide-slate-100/80 text-xs">
          
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-400">
        <div id="citizenPaginationText">
          Showing 0 to 0 of 0 accounts
        </div>
        <div class="flex items-center space-x-1">
          <button class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 cursor-not-allowed transition" disabled>
            <i class="fa-solid fa-chevron-left text-[9px]"></i>
          </button>
          <button class="px-3 py-1.5 rounded-lg bg-brand-light border border-brand-border text-brand-dark font-extrabold">1</button>
          <button class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 cursor-not-allowed transition" disabled>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
          </button>
        </div>
      </div>
    </div>

  </div> <!-- End #citizenRealContent -->

</main>

</main>

<!-- MODALS SECTION -->

<!-- VIEW ACCOUNT  -->
<div id="viewCitizenModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fa-solid fa-id-card-clip text-brand-medium"></i>
        <h3 class="font-extrabold text-sm tracking-tight uppercase">Citizen Profile Details</h3>
      </div>
      <button onclick="closeModal('viewCitizenModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
      
      <div class="border-b border-slate-100 pb-3.5 flex items-center justify-between gap-4">
        <div class="space-y-1">
          <h4 id="viewName" class="font-extrabold text-sm text-slate-950">Juan Dela Cruz</h4>
          <span id="viewId" class="text-[10px] font-bold text-slate-400 font-mono">CIT-2026-8942</span>
        </div>
        <span id="viewStatusBadge" class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full border">Active</span>
      </div>

  
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-slate-50 border border-slate-200/50 rounded-xl p-3.5 space-y-1">
          <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Registration Date</span>
          <p id="viewRegDate" class="text-xs font-extrabold text-slate-800">Jan 12, 2026</p>
        </div>
        <div class="bg-slate-50 border border-slate-200/50 rounded-xl p-3.5 space-y-1">
          <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Last Active Hub</span>
          <p id="viewLastLogin" class="text-xs font-extrabold text-slate-800">2 hours ago</p>
        </div>
      </div>

    
      <div class="space-y-3">
        <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-wider block flex items-center gap-1.5">
          <i class="fa-solid fa-network-wired text-slate-450"></i>
        </h5>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="border border-slate-150 rounded-xl p-3 space-y-2 bg-white flex flex-col justify-between">
            <span class="text-[10px] font-extrabold text-slate-700 leading-tight">Scholarship Portal</span>
            <span id="serviceScholarship" class="text-[9px] font-black tracking-wide px-2 py-0.5 rounded border self-start">Active</span>
          </div>

          <div class="border border-slate-150 rounded-xl p-3 space-y-2 bg-white flex flex-col justify-between">
            <span class="text-[10px] font-extrabold text-slate-700 leading-tight">Business Permit</span>
            <span id="servicePermit" class="text-[9px] font-black tracking-wide px-2 py-0.5 rounded border self-start">No App</span>
          </div>

          <div class="border border-slate-150 rounded-xl p-3 space-y-2 bg-white flex flex-col justify-between">
            <span class="text-[10px] font-extrabold text-slate-700 leading-tight">Social Services Aid</span>
            <span id="serviceWelfare" class="text-[9px] font-black tracking-wide px-2 py-0.5 rounded border self-start">Pending</span>
          </div>
        </div>
      </div>
    </div>
    <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end">
      <button onclick="closeModal('viewCitizenModal')" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold cursor-pointer transition">Close Directory Profile</button>
    </div>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div id="resetCitizenModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fa-solid fa-key text-brand-medium"></i>
        <h3 class="font-extrabold text-sm tracking-tight uppercase">Reset Citizen Password</h3>
      </div>
      <button onclick="closeModal('resetCitizenModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="p-6 space-y-4">
      <div class="flex items-start gap-3">
        <div class="h-10 w-10 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0">
          <i class="fa-solid fa-circle-question text-base animate-pulse"></i>
        </div>
        <div class="space-y-1">
          <h4 class="font-bold text-slate-900 text-xs">Generate Recovery Key?</h4>
          <p id="resetConfirmText" class="text-xs text-slate-500 leading-relaxed">Are you sure you want to reset the password for Juan Dela Cruz?</p>
        </div>
      </div>

      <div id="resetPasswordContainer" class="bg-slate-50 border border-slate-150 rounded-xl p-4 space-y-3">
        <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Temporary Reset Credentials</p>
        <div class="flex items-stretch gap-2">
          <input type="text" id="tempCitizenPass" readonly value="CaloocanCiv#2026" class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full bg-white focus:outline-none font-mono font-bold text-slate-700 tracking-wide">
          <button onclick="copyCitizenPass()" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition shadow-xs cursor-pointer">
            <i class="fa-solid fa-copy"></i>
            <span>Copy</span>
          </button>
        </div>
        <p class="text-[10px] text-slate-400">Provide this temporary security key to the citizen. They will be forced to compile a new password immediately upon first dashboard portal load.</p>
      </div>
    </div>
    <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end space-x-2">
      <button onclick="closeModal('resetCitizenModal')" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer">Cancel</button>
      <button onclick="generateCitizenPassword()" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition cursor-pointer shadow-xs">Generate Temporary Password</button>
    </div>
  </div>
</div>

<!-- LOCK/UNLOCK STATUS MODAL -->
<div id="lockCitizenModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i id="lockModalIcon" class="fa-solid fa-user-lock text-brand-medium"></i>
        <h3 id="lockModalTitle" class="font-extrabold text-sm tracking-tight uppercase">Lock Citizen Credentials</h3>
      </div>
      <button onclick="closeModal('lockCitizenModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form id="lockCitizenForm" onsubmit="handleExecuteLock(event)">
      <input type="hidden" id="lockCitizenIdRef">
      <div class="p-6 space-y-4">
        <div class="flex items-start gap-3">
          <div id="lockIconBox" class="h-10 w-10 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 shrink-0">
            <i class="fa-solid fa-user-lock text-base"></i>
          </div>
          <div class="space-y-1">
            <h4 id="lockModalHeader" class="font-bold text-slate-900 text-xs">Place Security Access Block?</h4>
            <p id="lockModalText" class="text-xs text-slate-500 leading-relaxed">Confirm locking Juan Dela Cruz's account. This prevents citizen profile verification logins immediately.</p>
          </div>
        </div>

        <div class="space-y-1.5" id="lockReasonContainer">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Security Action Reason</label>
          <select id="lockReason" class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-semibold text-slate-700">
            <option value="Suspicious Activity Detected">Suspicious Activity Detected (SSO Flag)</option>
            <option value="Administrative Hold">Administrative Hold (Review Pending)</option>
            <option value="Multiple Failed Login Attempts">Multiple Failed Login Attempts (Brute Lock)</option>
            <option value="Compliance Hold">Compliance Hold (Municipal Hold)</option>
          </select>
        </div>
      </div>
      <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end space-x-2">
        <button type="button" onclick="closeModal('lockCitizenModal')" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-650 font-bold px-4 py-2.5 rounded-xl text-xs transition cursor-pointer">Cancel</button>
        <button type="submit" id="lockModalSubmitBtn" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer shadow-xs">Lock Account</button>
      </div>
    </form>
  </div>
</div>

<!-- TOAST POPUP NOTIFICATION -->

<div id="toast" class="fixed bottom-4 right-4 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-3.5 rounded-xl shadow-lg flex items-center gap-3 transform translate-y-4 opacity-0 pointer-events-none transition-all duration-300">
  <div class="h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px]">
    <i class="fa-solid fa-check"></i>
  </div>
  <span id="toastMsg" class="tracking-wide">Action executed successfully.</span>
</div>

<script src="<?php echo $basePath ?? '../'; ?>assets/js/citizen/citizen-directory.js"></script>

<?php include '../../includes/footer.php'; ?>
