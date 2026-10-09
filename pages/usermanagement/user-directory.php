<?php
$basePath = '../../';
require_once __DIR__ . '/../../src/bootstrap.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<script>
  window.currentUserId     = <?php echo json_encode($_SESSION['user_id']     ?? null); ?>;
  window.currentEmployeeId = <?php echo json_encode($_SESSION['employee_id'] ?? null); ?>;
  window.currentUserEmail  = <?php echo json_encode($_SESSION['email']       ?? null); ?>;
</script>


<main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

  <!-- Breadcrumb & Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
    <div class="space-y-1">
      <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
        <span>User Management</span>
        <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
        <span class="text-brand-dark">User Directory</span>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
        <i class="fa-solid fa-users-gear text-brand-dark"></i>
        User Directory
      </h1>
      <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
        Coordinate LGU staff access profiles, assign civic department branches, manage system capabilities, and review security log timelines.
      </p>
    </div>
  </div>

  <!-- Dashboard Metrics Header (Stats Cards) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
    <!-- Stat Card 1: Total Users -->
    <div 
      onclick="filterUserCard('ALL')"
      id="cardTotalUsers"
      class="user-metric-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
      title="Click to view all user profiles"
    >
      <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-teal-500 to-brand-dark opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
      <div class="flex items-start justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-brand-dark dark:group-hover:text-cyan-400 transition-colors block">Total Users</span>
          <h3 id="statTotalUsers" class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">0</h3>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-cyan-50 dark:bg-cyan-950/60 border border-cyan-200/80 dark:border-cyan-800/50 flex items-center justify-center text-brand-dark dark:text-cyan-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
          <i class="fa-solid fa-users text-lg"></i>
        </div>
      </div>
      <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
        <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-brand-dark animate-pulse"></span>
          Registered staff profiles
        </p>
        <span class="text-[10px] font-black text-brand-dark dark:text-cyan-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
          View All <i class="fa-solid fa-arrow-right text-[9px]"></i>
        </span>
      </div>
    </div>

    <!-- Stat Card 2: Active Accounts -->
    <div 
      onclick="filterUserCard('ACTIVE')"
      id="cardActiveUsers"
      class="user-metric-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
      title="Click to filter active accounts"
    >
      <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 via-teal-500 to-emerald-600 opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
      <div class="flex items-start justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors block">Active Accounts</span>
          <div class="flex items-baseline space-x-2">
            <h3 id="statActiveUsers" class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">0</h3>
            <span id="statActiveBadge" class="text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 px-1.5 py-0.5 rounded-md flex items-center gap-1">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span> 0%
            </span>
          </div>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/80 dark:border-emerald-800/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
          <i class="fa-solid fa-user-check text-lg"></i>
        </div>
      </div>
      <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
        <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          Active system access grants
        </p>
        <span class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
          Filter Active <i class="fa-solid fa-filter text-[9px]"></i>
        </span>
      </div>
    </div>

    <!-- Stat Card 3: Deactivated Accounts -->
    <div 
      onclick="filterUserCard('DEACTIVATED')"
      id="cardDeactiveUsers"
      class="user-metric-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
      title="Click to manage deactivated account status file"
    >
      <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-400 via-red-500 to-rose-600 opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
      <div class="flex items-start justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-rose-600 dark:group-hover:text-rose-400 transition-colors block">Deactivated Accounts</span>
          <h3 id="statDeactivatedUsers" class="text-3xl font-black text-rose-600 dark:text-rose-400 tracking-tight">0</h3>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200/80 dark:border-rose-800/50 flex items-center justify-center text-rose-600 dark:text-rose-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
          <i class="fa-solid fa-user-slash text-lg"></i>
        </div>
      </div>
      <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
        <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
          Suspended account credentials
        </p>
        <span class="text-[10px] font-black text-rose-600 dark:text-rose-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
          Account Status <i class="fa-solid fa-arrow-right-to-bracket text-[9px]"></i>
        </span>
      </div>
    </div>

    <!-- Stat Card 4: Active Departments -->
    <div 
      onclick="filterUserCard('DEPTS')"
      id="cardDepts"
      class="user-metric-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
      title="Click to open Department Directory management file"
    >
      <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
      <div class="flex items-start justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors block">Active Departments</span>
          <h3 id="statDepts" class="text-3xl font-black text-blue-600 dark:text-blue-400 tracking-tight">0</h3>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/50 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
          <i class="fa-solid fa-sitemap text-lg"></i>
        </div>
      </div>
      <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
        <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
          Represented city offices
        </p>
        <span class="text-[10px] font-black text-blue-600 dark:text-blue-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
          Department File <i class="fa-solid fa-arrow-right-to-bracket text-[9px]"></i>
        </span>
      </div>
    </div>
  </div>

  <!-- Action Bar -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
    <!-- Left Side: Search & Filters -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1">
      <!-- Search Bar -->
      <div class="relative flex-1 max-w-md">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" id="searchInput" placeholder="Search by name, email, employee ID..." class="pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-xs w-full bg-slate-50/50 focus:bg-white focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition">
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-2">
        <!-- Role Filter -->
        <select id="roleFilter" class="border border-slate-200 rounded-xl text-xs px-3 py-2.5 bg-slate-50/50 hover:bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-semibold text-slate-600 max-w-[130px]">
          <option value="">All Roles</option>
        </select>

        <!-- Department Filter -->
        <select id="deptFilter" class="border border-slate-200 rounded-xl text-xs px-3 py-2.5 bg-slate-50/50 hover:bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-semibold text-slate-600 max-w-[200px]">
          <option value="">All Departments</option>
        </select>
      </div>
    </div>

    <!-- Right Side: Action Buttons -->
    <div class="flex items-center gap-2 shrink-0">
      <!-- Export to CSV -->
      <button onclick="exportToCSV()" class="border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 cursor-pointer transition">
        <i class="fa-solid fa-download"></i>
        <span>Export</span>
      </button>
    </div>
  </div>

  <!-- Responsive Datatable -->
  <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
            <th class="px-6 py-4">User Details</th>
            <th class="px-6 py-4">Employee ID</th>
            <th class="px-6 py-4">Department & Position</th>
            <th class="px-6 py-4">Role</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="directoryTableBody" class="divide-y divide-slate-100/80 text-xs">
          <!-- Dynamically populated by JS -->
        </tbody>
      </table>
    </div>

    <!-- Table Footer / Pagination -->
    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-400">
      <div id="paginationText">
        Showing 0 to 0 of 0 profiles
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

</main>

<!-- MODALSSSS -->

<!--  VIEW PROFILE MODAL -->
<div id="viewModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fa-solid fa-id-card text-brand-medium"></i>
        <h3 class="font-extrabold text-sm tracking-tight uppercase">Staff Profile Details</h3>
      </div>
      <button onclick="closeModal('viewModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
      <!-- Profile -->
      <div class="flex items-center space-x-4 bg-slate-50 p-4 border border-slate-150 rounded-2xl">
        <div id="viewInitialsBox" class="h-16 w-16 rounded-2xl bg-brand-light text-brand-dark flex items-center justify-center font-black text-xl border border-brand-border shrink-0 overflow-hidden relative">
          <span id="viewInitialsText">JS</span>
          <img id="viewAvatarImg" src="" alt="Profile Photo" class="hidden w-full h-full object-cover">
        </div>
        <div class="flex-1 space-y-1">
          <h4 id="viewName" class="text-base font-black text-slate-900 leading-snug">Joshua Suruiz</h4>
          <p id="viewEmail" class="text-xs font-medium text-slate-500">joshua.suruiz@caloocancity.gov.ph</p>
          <div class="flex items-center space-x-2 mt-1">
            <span id="viewRoleBadge" class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full border">Superadmin</span>
            <span id="viewStatusBadge" class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full border">Active</span>
          </div>
        </div>
      </div>

      <!-- Info Details -->
      <div class="grid grid-cols-2 gap-y-4 gap-x-2 border-b border-slate-100 pb-5">
        <div>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Employee ID</span>
          <span id="viewEmpId" class="text-xs font-mono font-bold text-slate-700">EMP-2026-0001</span>
        </div>
        <div>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Contact Phone</span>
          <span id="viewPhone" class="text-xs font-semibold text-slate-700">+63 917 123 4567</span>
        </div>
        <div>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Department Assignment</span>
          <span id="viewDept" class="text-xs font-bold text-slate-700">ICT Department</span>
        </div>
        <div>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Official Designation</span>
          <span id="viewPosition" class="text-xs font-semibold text-slate-700">Chief Technology Officer</span>
        </div>
        <div>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Profile Generated On</span>
          <span id="viewCreated" class="text-xs font-semibold text-slate-700">2026-01-10</span>
        </div>
      </div>

      <!-- System Permissions -->
      <div class="space-y-3">
        <div class="flex items-center space-x-2 text-slate-700">
          <i class="fa-solid fa-shield-halved text-xs text-brand-dark"></i>
          <span class="text-xs font-black uppercase tracking-wider">Access Permissions Matrix</span>
        </div>
        <div id="viewPermissionsList" class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
        </div>
      </div>
    </div>
    <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end">
      <button onclick="closeModal('viewModal')" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2 rounded-xl text-xs font-bold cursor-pointer transition">Close Profile</button>
    </div>
  </div>
</div>

<!-- EDIT -->
<div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fa-solid fa-user-pen text-brand-medium"></i>
        <h3 class="font-extrabold text-sm tracking-tight uppercase">Modify Staff Account</h3>
      </div>
      <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form id="editStaffForm" onsubmit="handleEditStaff(event)">
      <input type="hidden" id="editEmpIdRef">
      
      <!-- Input fields container -->
      <div id="editFormFields" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
        <div class="grid grid-cols-1 gap-4">
          <!-- Name -->
          <div class="space-y-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Full Name</label>
            <input type="text" id="editName" required class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full focus:outline-none focus:border-brand-medium transition">
          </div>
          <!-- Email -->
          <div class="space-y-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Email Address</label>
            <input type="email" id="editEmail" required class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full focus:outline-none focus:border-brand-medium transition">
          </div>
          <!-- Phone -->
          <div class="space-y-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Contact Number</label>
            <input type="text" id="editPhone" required class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full focus:outline-none focus:border-brand-medium transition">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <!-- Department -->
            <div class="space-y-1.5">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Department</label>
              <select id="editDept" required class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer">
                <option value="">Select Department...</option>
              </select>
            </div>
            <!-- Position -->
            <div class="space-y-1.5">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Position</label>
              <select id="editPosition" required class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer">
                <option value="">Select Position...</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <!-- Role -->
            <div class="space-y-1.5">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">System Role</label>
              <select id="editRole" required class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer">
                <option value="">Select Role...</option>
              </select>
            </div>
            <!-- Status -->
            <div class="space-y-1.5">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Access Status</label>
              <select id="editStatus" required class="border border-slate-200 rounded-xl px-3 py-2.5 text-xs w-full bg-white focus:outline-none focus:border-brand-medium transition cursor-pointer font-bold">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Pending">Pending</option>
                <option value="Locked">Locked</option>
                <option value="Archived">Archived</option>
              </select>
            </div>
          </div>

          <!-- Feature Permissions -->
          <div class="pt-4 mt-2 border-t border-slate-100 space-y-3">
            <div class="flex items-center justify-between">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Feature Access Permissions</label>
              <span class="text-[9px] text-slate-400 font-bold bg-slate-100 px-2 py-0.5 rounded-full">Optional</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="editFeaturePermissions">
              <!-- Treasury -->
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Revenue Collection</span>
                <input type="checkbox" name="feature_permissions" value="revenue_collection" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Disbursements</span>
                <input type="checkbox" name="feature_permissions" value="disbursements" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Business Tax & Permits</span>
                <input type="checkbox" name="feature_permissions" value="business_tax" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Market Stall Leasing</span>
                <input type="checkbox" name="feature_permissions" value="market_stall" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Financial Reports</span>
                <input type="checkbox" name="feature_permissions" value="financial_reports" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              
              <!-- Budget -->
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Budget Approvals</span>
                <input type="checkbox" name="feature_permissions" value="budget_approvals" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Department Requests</span>
                <input type="checkbox" name="feature_permissions" value="department_requests" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>

              <!-- Admin -->
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">User Management</span>
                <input type="checkbox" name="feature_permissions" value="user_management" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Citizen Management</span>
                <input type="checkbox" name="feature_permissions" value="citizen_management" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
              <label class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 cursor-pointer transition">
                <span class="text-xs font-bold text-slate-700">Audit Logs</span>
                <input type="checkbox" name="feature_permissions" value="audit_logs" class="feature-toggle accent-brand-medium w-4 h-4 cursor-pointer">
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Reset Password Confirmation Sub-Pane -->
      <div id="resetPasswordPane" class="hidden p-6 space-y-5 text-center">
        <div class="h-12 w-12 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 mx-auto">
          <i class="fa-solid fa-triangle-exclamation text-lg"></i>
        </div>
        <div class="space-y-2">
          <h4 class="font-extrabold text-sm text-slate-900">Reset User Credentials?</h4>
          <p id="resetConfirmMessage" class="text-xs text-slate-500 leading-relaxed">
            Are you sure you want to reset the user's password?
          </p>
        </div>
        
        <!-- Action Buttons for Reset Password Confirm Pane -->
        <div id="resetConfirmButtons" class="flex items-center justify-center space-x-2 pt-2">
          <button type="button" onclick="cancelResetPassword()" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer">Cancel</button>
          <button type="button" onclick="confirmResetPassword()" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">Confirm Reset</button>
        </div>

        <!-- OTP Input Field Container -->
        <div id="otpFieldContainer" class="hidden space-y-4 pt-3 border-t border-slate-100">
          <div class="space-y-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Enter Temporary 6-Digit OTP</label>
            <div class="flex justify-center items-center">
              <input type="text" id="otpInput" maxlength="6" placeholder="0 0 0 0 0 0" class="border border-slate-200 rounded-xl px-4 py-2.5 text-center text-sm font-mono tracking-[0.4em] font-bold w-48 bg-slate-50 focus:bg-white focus:outline-none focus:border-brand-medium transition">
            </div>
          </div>
          <p class="text-[10px] text-slate-400">An authorization code has been dispatched to the user's registered mailbox.</p>
          <div class="flex justify-center gap-2 pt-2">
            <button type="button" onclick="verifyOTP()" class="bg-[#0f172a] hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">Verify & Reset</button>
            <button type="button" onclick="cancelResetPassword()" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer">Close</button>
          </div>
        </div>
      </div>

      <!-- Main Edit Modal Footer -->
      <div id="editModalFooter" class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-between">
        <!-- Reset Password Button on Left -->
        <button type="button" onclick="triggerResetPassword()" class="text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-100 px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
          <i class="fa-solid fa-key text-[10px]"></i>
          <span>Reset Password</span>
        </button>
        <div class="flex items-center space-x-2">
          <button type="button" onclick="closeModal('editModal')" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold cursor-pointer transition">Cancel</button>
          <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold cursor-pointer transition shadow-xs">Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- CONFIRM ARCHIVE USER MODAL -->
<div id="archiveModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="p-6 space-y-4 text-center">
      <div class="h-14 w-14 rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center mx-auto text-xl shadow-2xs">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>
      <div class="space-y-1">
        <h3 class="text-base font-black text-slate-900 tracking-tight">Archive User Account?</h3>
        <p id="archiveTargetUserName" class="text-xs font-bold text-brand-dark">User Account</p>
      </div>
      <div class="bg-amber-50/80 border border-amber-200/80 rounded-xl p-3.5 text-left text-xs leading-relaxed text-amber-800 flex items-start gap-2.5">
        <i class="fa-solid fa-circle-exclamation text-amber-600 text-sm mt-0.5 shrink-0"></i>
        <span>Archiving this user profile will revoke active session access and soft-delete the user account across CIVENTRAL services.</span>
      </div>
      <div class="pt-2 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeModal('archiveModal')" class="w-1/2 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer">
          Cancel
        </button>
        <button type="button" onclick="confirmArchiveUser()" class="w-1/2 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5">
          <i class="fa-solid fa-box-archive text-xs"></i>
          <span>Confirm Archive</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- 4. LOGIN HISTORY MODAL -->
<div id="historyModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 overflow-hidden transform scale-95 transition-all duration-300">
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fa-solid fa-clock-rotate-left text-brand-medium"></i>
        <h3 class="font-extrabold text-sm tracking-tight uppercase">Security Login Audit</h3>
      </div>
      <button onclick="closeModal('historyModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
      <div class="space-y-1">
        <h4 id="historyStaffName" class="text-sm font-bold text-slate-900">Joshua Suruiz</h4>
        <p class="text-xs text-slate-500">Security history audits log all credentials verification attempts across active terminal hubs.</p>
      </div>

      <div class="border border-slate-200/80 rounded-xl overflow-hidden">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
              <th class="px-4 py-3">Date & Time</th>
              <th class="px-4 py-3">IP Address</th>
              <th class="px-4 py-3">Device / Platform</th>
              <th class="px-4 py-3 text-right">Status</th>
            </tr>
          </thead>
          <tbody id="historyTableBody" class="divide-y divide-slate-150 text-slate-600">
            <!-- Populated by JS -->
          </tbody>
        </table>
      </div>
    </div>
    <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end">
      <button onclick="closeModal('historyModal')" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2 rounded-xl text-xs font-bold cursor-pointer transition">Close Audit</button>
    </div>
  </div>
</div>
\
<div id="toast" class="fixed bottom-4 right-4 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-3.5 rounded-xl shadow-lg flex items-center gap-3 transform translate-y-4 opacity-0 pointer-events-none transition-all duration-300">
  <div class="h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px]">
    <i class="fa-solid fa-check"></i>
  </div>
  <span id="toastMsg" class="tracking-wide">Action completed successfully.</span>
</div>
\
<script src="<?php echo $basePath ?? '../'; ?>assets/js/usermanagement/user-directory.js?v=<?php echo time(); ?>"></script>

<?php include '../../includes/footer.php'; ?>
