<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$basePath = '../../';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

  <!-- Breadcrumb & Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
    <div class="space-y-1">
      <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
        <span>Department Management</span>
        <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
        <span class="text-brand-dark">Departments</span>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
        <i class="fa-solid fa-building text-brand-dark"></i>
        Department Directory
      </h1>
      <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
        Manage, configure, and monitor all municipal departments integrated into the CIVENTRAL portal network.
      </p>
    </div>

    <!-- Create Button -->
    <?php if (!empty($headerUser['is_superadmin']) || !empty($headerUser['is_global_access']) || (in_array('CREATE', $headerUser['granted_actions'] ?? []))): ?>
    <div class="shrink-0">
      <button onclick="openCreateModal()" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 cursor-pointer transition shadow-xs">
        <i class="fa-solid fa-plus text-[10px]"></i>
        <span>Create Department</span>
      </button>
    </div>
    <?php endif; ?>
  </div>

  <!-- FULL CONTENT SKELETON LOADER -->
  <div id="deptSkeleton" class="space-y-6 transition-all duration-500 opacity-100 pointer-events-auto">
    <!-- Summary Cards Skeleton -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
      <?php for($s=0; $s<3; $s++): ?>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-2 w-full">
          <div class="skeleton-loader h-3 w-28 rounded-md"></div>
          <div class="skeleton-loader h-7 w-24 rounded-lg"></div>
        </div>
        <div class="skeleton-loader h-10 w-10 rounded-lg shrink-0 ml-3"></div>
      </div>
      <?php endfor; ?>
    </div>

    <!-- Search Bar Skeleton -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
      <div class="skeleton-loader h-10 w-full max-w-md rounded-xl"></div>
      <div class="skeleton-loader h-10 w-36 rounded-xl"></div>
    </div>

    <!-- Datatable Skeleton -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-32 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-36 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-28 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-20 rounded-md"></div></th>
              <th class="px-6 py-4 text-right"><div class="skeleton-loader h-3 w-16 rounded-md ml-auto"></div></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100/80">
            <?php for($k=0; $k<5; $k++): ?>
            <tr class="animate-pulse">
              <td class="px-6 py-4"><div class="skeleton-loader h-4 w-40 rounded-md"></div></td>
              <td class="px-6 py-4"><div class="skeleton-loader h-4 w-32 rounded-md"></div></td>
              <td class="px-6 py-4"><div class="skeleton-loader h-4 w-48 rounded-md"></div></td>
              <td class="px-6 py-4"><div class="skeleton-loader h-5 w-20 rounded-full"></div></td>
              <td class="px-6 py-4 text-right"><div class="skeleton-loader h-7 w-20 rounded-lg ml-auto"></div></td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- REAL PAGE CONTENT -->
  <div id="deptRealContent" class="space-y-6 hidden opacity-0 transition-all duration-700 ease-out transform translate-y-2">

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
      <!-- Total Departments Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-brand-dark"></div>
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Departments</span>
          <h3 id="statTotalDepts" class="text-2xl font-black text-slate-900 tracking-tight">0 Active</h3>
        </div>
        <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-450 group-hover:bg-brand-light group-hover:text-brand-dark transition duration-350">
          <i class="fa-solid fa-sitemap text-sm"></i>
        </div>
      </div>

      <!-- Integrations Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">System Integrations</span>
          <h3 id="statIntegrations" class="text-2xl font-black text-slate-900 tracking-tight">100% Core Connected</h3>
        </div>
        <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-450 group-hover:bg-emerald-50 group-hover:text-emerald-700 transition duration-350">
          <i class="fa-solid fa-link text-sm"></i>
        </div>
      </div>

      <!-- Admins Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between group relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-500"></div>
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Assigned Administrators</span>
          <h3 id="statAssignedAdmins" class="text-2xl font-black text-slate-900 tracking-tight">0 Assigned</h3>
        </div>
        <div class="h-10 w-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-450 group-hover:bg-amber-50 group-hover:text-amber-700 transition duration-350">
          <i class="fa-solid fa-user-shield text-sm"></i>
        </div>
      </div>
    </div>

    <!-- Search and Actions Roster Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
      <!-- Search Field -->
      <div class="relative flex-1 max-w-md">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" id="deptSearchInput" placeholder="Search by department name, code, or administrator..." class="pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-xs w-full bg-slate-50/50 focus:bg-white focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition">
      </div>
      
      <!-- Export Excel simulator -->
      <button onclick="exportDeptsCsv()" class="border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 cursor-pointer transition">
        <i class="fa-solid fa-download"></i>
        <span>Export Directory</span>
      </button>
    </div>

    <!-- Datatable list -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
              <th class="px-6 py-4 w-1/3">Department Details</th>
              <th class="px-6 py-4">Department Administrator</th>
              <th class="px-6 py-4">Access Scope Modules</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="deptsTableBody" class="divide-y divide-slate-100/80 text-xs">
            <!-- Dynamically Populated by JS -->
          </tbody>
        </table>
      </div>

      <!-- Table Footer / Pagination -->
      <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-400">
        <div id="deptsPaginationText">
          Showing 0 to 0 of 0 departments
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

  </div> <!-- End #deptRealContent -->

</main>


<!-- 1. CREATE / EDIT DEPARTMENT MODAL -->
<div id="deptModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i id="deptModalIcon" class="fa-solid fa-building text-brand-medium"></i>
        <h3 id="deptModalTitle" class="font-extrabold text-sm tracking-tight uppercase">Create Department</h3>
      </div>
      <button onclick="closeModal('deptModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form id="deptForm" onsubmit="handleSaveDept(event)">
      <input type="hidden" id="deptIdRef">
      <div class="p-6 space-y-4">
        <!-- Department Name -->
        <div class="space-y-1.5">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Department Name</label>
          <input type="text" id="deptName" required placeholder="e.g. Business Permits & Licensing Office" class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full focus:outline-none focus:border-brand-medium transition">
        </div>
        <!-- Department Code -->
        <div class="space-y-1.5">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Department System Code</label>
          <input type="text" id="deptCode" required placeholder="e.g. BPLO" class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full focus:outline-none focus:border-brand-medium transition uppercase font-bold tracking-wider">
        </div>
        <!-- Department Admin selector -->
        <div class="space-y-1.5">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Department Administrator</label>
          <select id="deptAdmin" class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-semibold text-slate-700">
            <option value="">Unassigned</option>
            <!-- Dynamically populated from live users by JS -->
          </select>
        </div>
        <!-- Description -->
        <div class="space-y-1.5">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Operational Description</label>
          <textarea id="deptDesc" rows="3" placeholder="Describe the municipal operations handled by this department unit..." class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full focus:outline-none focus:border-brand-medium transition resize-none"></textarea>
        </div>
      </div>
      <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end space-x-2">
        <button type="button" onclick="closeModal('deptModal')" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-650 font-bold cursor-pointer transition">Cancel</button>
        <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold cursor-pointer transition shadow-xs">Save Department</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. VIEW DETAILS INFO MODAL -->
<div id="viewDeptModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fa-solid fa-circle-info text-brand-medium"></i>
        <h3 class="font-extrabold text-sm tracking-tight uppercase">Department Parameters Summary</h3>
      </div>
      <button onclick="closeModal('viewDeptModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
      
      <!-- Primary Title Header -->
      <div class="border-b border-slate-100 pb-3 space-y-1">
        <div class="flex items-center gap-2">
          <span id="viewDeptCodeBadge" class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-black border border-slate-200">EDU</span>
          <h4 id="viewDeptName" class="font-extrabold text-sm text-slate-900">Education & Scholarship Management</h4>
        </div>
        <p id="viewDeptDesc" class="text-xs text-slate-500 leading-relaxed">System department managing city scholars, school distributions, and educational activities.</p>
      </div>

      <!-- Quick Metadata Grid -->
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-slate-50 border border-slate-200/40 rounded-xl p-3.5 space-y-1">
          <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Connected Staff</span>
          <p id="viewDeptStaffCount" class="text-sm font-extrabold text-slate-800">12 registered staff</p>
        </div>
        <div class="bg-slate-50 border border-slate-200/40 rounded-xl p-3.5 space-y-1">
          <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Integration Port</span>
          <p class="text-sm font-extrabold text-emerald-600 flex items-center gap-1">
            <i class="fa-solid fa-circle-check text-[10px]"></i>
            <span>Active REST SSO</span>
          </p>
        </div>
      </div>

      <!-- Detail list -->
      <div class="space-y-3">
        <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Recent Admin Logs</h5>
        <div class="border border-slate-200/60 rounded-xl overflow-hidden text-xs">
          <div class="bg-slate-50 border-b border-slate-100 px-4 py-2.5 flex items-center justify-between font-bold text-slate-500">
            <span>Action Taken</span>
            <span>Date & Time</span>
          </div>
          <div class="divide-y divide-slate-100 text-slate-650">
            <div class="px-4 py-2.5 flex justify-between">
              <span>Admin credentials profile assigned</span>
              <span class="text-slate-450 font-medium">2026-07-15 14:02:10</span>
            </div>
            <div class="px-4 py-2.5 flex justify-between">
              <span>Scopes synchronized to SSO Token</span>
              <span class="text-slate-450 font-medium">2026-07-12 09:30:45</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Scope Scopes List -->
      <div class="space-y-2">
        <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Core Module Scopes Authorized</h5>
        <div id="viewDeptScopesContainer" class="flex flex-wrap gap-1.5">
          <!-- JS Badges -->
        </div>
      </div>
    </div>
    <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end">
      <button onclick="closeModal('viewDeptModal')" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold cursor-pointer transition">Close Summary</button>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- TOAST POPUP CONTAINER -->
<!-- ========================================================================= -->
<div id="toast" class="fixed bottom-4 right-4 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-3.5 rounded-xl shadow-lg flex items-center gap-3 transform translate-y-4 opacity-0 pointer-events-none transition-all duration-300">
  <div class="h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px]">
    <i class="fa-solid fa-check"></i>
  </div>
  <span id="toastMsg" class="tracking-wide">Action executed successfully.</span>
</div>

<?php include '../../includes/footer.php'; ?>
