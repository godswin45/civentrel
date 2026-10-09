<?php
$basePath = '../../';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<script>
  window.currentUserRoleId = <?php echo json_encode($_SESSION['role_id'] ?? null); ?>;
  window.currentUserRoleName = <?php echo json_encode($headerUser['role'] ?? ''); ?>;
</script>

<main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto relative pb-28 bg-[#F8FAFC]">

  <!-- Breadcrumb & Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
    <div class="space-y-1">
      <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
        <span>Role & Permissions</span>
        <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
        <span class="text-brand-dark">System Roles</span>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5 mt-4">
        <i class="fa-solid fa-user-shield text-brand-dark"></i>
        System Role Management
      </h1>
      <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
        Define, configure, and manage system access roles, role prefixes, global access permissions, and system role protection based on the CIVENTRAL database schema.
      </p>
    </div>

    <!-- Primary Action Button -->
    <?php if (!empty($headerUser['is_superadmin']) || !empty($headerUser['is_global_access']) || (in_array('CREATE', $headerUser['granted_actions'] ?? []))): ?>
    <div class="shrink-0">
      <button 
        type="button"
        onclick="openCreateModal()" 
        class="bg-[#86B6F6] hover:bg-[#6fa5f5] text-slate-900 font-extrabold px-4.5 py-2.5 rounded-xl text-xs transition duration-200 shadow-xs flex items-center gap-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-medium/30 border border-[#72a6eb]"
      >
        <i class="fa-solid fa-plus text-xs"></i>
        <span>Create New Role</span>
      </button>
    </div>
    <?php endif; ?>
  </div>

  <!-- FULL CONTENT SKELETON LOADER -->
  <div id="rolesSkeleton" class="space-y-6 transition-all duration-500 opacity-100 pointer-events-auto">
    <!-- Metric Cards Skeleton -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php for($i=0; $i<4; $i++): ?>
      <div class="glass-panel rounded-2xl p-5 flex items-center justify-between dark:bg-slate-900/85 dark:border-slate-800/80">
        <div class="space-y-2.5 w-full">
          <div class="skeleton-loader h-3 w-28 rounded-md"></div>
          <div class="skeleton-loader h-7 w-20 rounded-lg"></div>
          <div class="skeleton-loader h-2.5 w-36 rounded-md"></div>
        </div>
        <div class="skeleton-loader h-11 w-11 rounded-xl shrink-0 ml-3"></div>
      </div>
      <?php endfor; ?>
    </div>

    <!-- Table Skeleton -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
      <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-4">
        <div class="skeleton-loader h-10 w-80 rounded-xl"></div>
        <div class="skeleton-loader h-10 w-32 rounded-xl"></div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-24 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-28 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-20 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-16 rounded-md"></div></th>
              <th class="px-6 py-4 text-right"><div class="skeleton-loader h-3 w-20 rounded-md ml-auto"></div></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php for($k=0; $k<5; $k++): ?>
            <tr class="animate-pulse">
              <td class="px-6 py-4"><div class="skeleton-loader h-3.5 w-24 rounded-md"></div></td>
              <td class="px-6 py-4">
                <div class="space-y-1.5">
                  <div class="skeleton-loader h-3.5 w-36 rounded-md"></div>
                  <div class="skeleton-loader h-2.5 w-28 rounded-md"></div>
                </div>
              </td>
              <td class="px-6 py-4"><div class="skeleton-loader h-5 w-20 rounded-full"></div></td>
              <td class="px-6 py-4"><div class="skeleton-loader h-5 w-16 rounded-full"></div></td>
              <td class="px-6 py-4 text-right"><div class="skeleton-loader h-7 w-20 rounded-lg ml-auto"></div></td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- REAL PAGE CONTENT -->
  <div id="rolesRealContent" class="space-y-6 hidden opacity-0 transition-all duration-700 ease-out transform translate-y-2">

    <!-- Database RBAC Summary Metric Cards (4 Columns) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      
      <!-- Total Defined Roles Card -->
      <div 
        onclick="filterByCard('ALL')" 
        id="cardTotalRoles"
        class="role-metric-card active-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
        title="Click to view all registered system roles"
      >
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-teal-500 to-brand-dark opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
        <div class="flex items-start justify-between">
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-brand-dark dark:group-hover:text-cyan-400 transition-colors block">Total Registered Roles</span>
            <h3 id="statTotalRoles" class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">0</h3>
          </div>
          <div class="h-12 w-12 rounded-2xl bg-cyan-50 dark:bg-cyan-950/60 border border-cyan-200/80 dark:border-cyan-800/50 flex items-center justify-center text-brand-dark dark:text-cyan-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
            <i class="fa-solid fa-users-gear text-lg"></i>
          </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-brand-dark animate-pulse"></span>
            Defined System Roles
          </p>
          <span class="text-[10px] font-black text-brand-dark dark:text-cyan-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
            View All <i class="fa-solid fa-arrow-right text-[9px]"></i>
          </span>
        </div>
      </div>

      <!-- Global Scope Roles Card -->
      <div 
        onclick="filterByCard('GLOBAL')" 
        id="cardGlobalRoles"
        class="role-metric-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
        title="Click to filter Global Scope Roles"
      >
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
        <div class="flex items-start justify-between">
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors block">Global Scope Roles</span>
            <h3 id="statGlobalRoles" class="text-3xl font-black text-blue-600 dark:text-blue-400 tracking-tight">0</h3>
          </div>
          <div class="h-12 w-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/50 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
            <i class="fa-solid fa-globe text-lg"></i>
          </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
            City-Wide Access Granted
          </p>
          <span class="text-[10px] font-black text-blue-600 dark:text-blue-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
            Filter Scope <i class="fa-solid fa-filter text-[9px]"></i>
          </span>
        </div>
      </div>

      <!-- Active Status Roles Card -->
      <div 
        onclick="filterByCard('ACTIVE')" 
        id="cardActiveRoles"
        class="role-metric-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
        title="Click to filter Active Status Roles"
      >
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 via-teal-500 to-emerald-600 opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
        <div class="flex items-start justify-between">
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors block">Active Status Roles</span>
            <h3 id="statActiveRoles" class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">0</h3>
          </div>
          <div class="h-12 w-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/80 dark:border-emerald-800/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
            <i class="fa-solid fa-circle-check text-lg"></i>
          </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            Operational Access Enabled
          </p>
          <span class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
            Filter Active <i class="fa-solid fa-check text-[9px]"></i>
          </span>
        </div>
      </div>

      <!-- Protected System Roles Card -->
      <div 
        onclick="filterByCard('SYSTEM')" 
        id="cardSystemRoles"
        class="role-metric-card bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative overflow-hidden flex flex-col justify-between"
        title="Click to filter Protected System Core Roles"
      >
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 via-orange-500 to-amber-600 opacity-90 group-hover:h-1.5 transition-all duration-300"></div>
        <div class="flex items-start justify-between">
          <div class="space-y-1">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors block">Protected System Roles</span>
            <h3 id="statSystemRoles" class="text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tight">0</h3>
          </div>
          <div class="h-12 w-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200/80 dark:border-amber-800/50 flex items-center justify-center text-amber-600 dark:text-amber-400 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shrink-0 shadow-xs">
            <i class="fa-solid fa-lock text-lg"></i>
          </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            Immutable Core Roles
          </p>
          <span class="text-[10px] font-black text-amber-600 dark:text-amber-400 flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-all transform group-hover:translate-x-0.5">
            Filter Core <i class="fa-solid fa-shield-halved text-[9px]"></i>
          </span>
        </div>
      </div>

    </div>

    <!-- Role Directory Table & Filters Workspace -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
      
      <!-- Status Tabs Header -->
      <div class="px-5 pt-3.5 pb-0 border-b border-slate-200/80 bg-slate-50/50 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center space-x-1" id="statusTabsContainer">
          <button type="button" onclick="switchStatusTab('Active')" data-status-tab="Active" class="status-tab-btn px-4 py-2.5 rounded-t-xl text-xs font-black transition-all cursor-pointer bg-white text-[#176B87] border-t-2 border-[#86B6F6] border-x border-slate-200/80 shadow-2xs">
            <i class="fa-solid fa-circle-check text-emerald-500 mr-1.5 text-[10px]"></i>
            Active <span id="tabCountActive" class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-extrabold">0</span>
          </button>

          <button type="button" onclick="switchStatusTab('Inactive')" data-status-tab="Inactive" class="status-tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition-all cursor-pointer text-slate-500 hover:text-slate-800 hover:bg-slate-100/60">
            <i class="fa-solid fa-circle-minus text-slate-400 mr-1.5 text-[10px]"></i>
            Inactive <span id="tabCountInactive" class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600 font-bold">0</span>
          </button>

          <button type="button" onclick="switchStatusTab('Archived')" data-status-tab="Archived" class="status-tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition-all cursor-pointer text-slate-500 hover:text-slate-800 hover:bg-slate-100/60">
            <i class="fa-solid fa-box-archive text-amber-500 mr-1.5 text-[10px]"></i>
            Archived <span id="tabCountArchived" class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-800 font-bold">0</span>
          </button>

          <button type="button" onclick="switchStatusTab('ALL')" data-status-tab="ALL" class="status-tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition-all cursor-pointer text-slate-500 hover:text-slate-800 hover:bg-slate-100/60">
            <i class="fa-solid fa-layer-group text-blue-500 mr-1.5 text-[10px]"></i>
            All <span id="tabCountAll" class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-800 font-bold">0</span>
          </button>
        </div>
      </div>

      <!-- Control Panel & Filters Toolbar -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
        <!-- Search Input -->
        <div class="relative flex-1 max-w-md">
          <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input 
            type="text" 
            id="roleSearchInput" 
            oninput="filterRoles()" 
            placeholder="Search roles by Name, Prefix (e.g. SADM), or Description..."  
          class="pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-xs w-full bg-white focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition"
        >
      </div>

      <!-- Filters Group -->
      <div class="flex items-center gap-3">
        <!-- Global Access Filter -->
        <select 
          id="globalAccessFilterSelect" 
          onchange="filterRoles()" 
          class="px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white text-slate-700 focus:outline-none focus:border-brand-medium transition cursor-pointer font-medium"
        >
          <option value="ALL">All Access Scopes</option>
          <option value="GLOBAL">Global City Access</option>
          <option value="DEPARTMENT">Department Scoped</option>
        </select>
      </div>
    </div>

    <!-- Structured Roles Datatable -->
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-400">
            <th class="px-6 py-3.5">Role Designation & Prefix</th>
            <th class="px-6 py-3.5">Scope Description & Access Level</th>
            <th class="px-6 py-3.5">Created Date</th>
            <th class="px-6 py-3.5 text-center">Status</th>
            <th class="px-6 py-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="rolesTableBody" class="divide-y divide-slate-100 text-xs font-medium">
          <!-- Dynamically populated by JS -->
        </tbody>
      </table>
    </div>

    <!-- Table Footer / Pagination -->
    <div class="bg-slate-50/80 px-6 py-3.5 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] font-bold text-slate-500">
      <div id="rolesPaginationText">
        Showing 0 to 0 of 0 defined roles
      </div>
      <div class="flex items-center space-x-1" id="rolesPaginationControls">
        <!-- Page buttons rendered by JS -->
      </div>
    </div>

  </div> <!-- End #rolesRealContent -->

</main>

<!-- MODALS SECTION -->

<!-- 1. CREATE / EDIT ROLE MODAL -->
<div id="roleModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden transform scale-95 transition-all duration-300 border border-slate-200">
    
    <!-- Modal Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
      <div class="flex items-center gap-2.5">
        <div class="h-8 w-8 rounded-xl bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark font-bold text-xs">
          <i id="roleModalIcon" class="fa-solid fa-user-shield"></i>
        </div>
        <div>
          <h3 id="roleModalTitle" class="text-sm font-black text-slate-900 tracking-tight">Create System Access Role</h3>
          <p class="text-[10px] text-slate-400 font-medium">Configure database role attributes and global access scope.</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('roleModal')" class="h-8 w-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
        <i class="fa-solid fa-xmark text-sm"></i>
      </button>
    </div>

    <!-- Form -->
    <form id="roleForm" onsubmit="handleSaveRole(event)" class="p-6 space-y-4">
      <input type="hidden" id="roleIdRef">

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Role Name -->
        <div class="sm:col-span-2 space-y-1.5">
          <label for="roleName" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Role Designation Name</label>
          <input 
            type="text" 
            id="roleName" 
            required 
            oninput="autoGenerateRolePrefix(this.value)" 
            placeholder="e.g. Department Administrator" 
            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition font-medium"
          >
        </div>

        <!-- Role Prefix -->
        <div class="space-y-1.5">
          <label for="rolePrefix" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Role Prefix</label>
          <input 
            type="text" 
            id="rolePrefix" 
            required 
            maxlength="10" 
            oninput="this.dataset.manual='true'" 
            placeholder="DADM" 
            class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs font-mono font-bold text-brand-dark bg-slate-50/50 uppercase focus:bg-white focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition"
          >
        </div>
      </div>

      <!-- Global Access Toggle Checkbox -->
      <?php if (!empty($headerUser['is_superadmin'])): ?>
      <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 space-y-1">
        <label class="flex items-center gap-2.5 cursor-pointer">
          <input type="checkbox" id="roleIsGlobalAccess" class="h-4 w-4 rounded border-slate-300 text-brand-dark focus:ring-brand-dark/20 accent-brand-dark cursor-pointer">
          <span class="text-xs font-extrabold text-slate-800">Grant Global City-Wide Access Scope</span>
        </label>
        <p class="text-[10px] text-slate-500 font-medium pl-6 leading-relaxed">
          When enabled (<code class="font-mono text-brand-dark">is_global_access = TRUE</code>), users under this role can access cross-departmental operations and city-wide metrics.
        </p>
      </div>
      <?php else: ?>
      <input type="hidden" id="roleIsGlobalAccess" value="0">
      <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 space-y-1 opacity-65">
        <div class="flex items-center gap-2 text-slate-600 text-xs font-extrabold">
          <i class="fa-solid fa-lock text-slate-400"></i>
          <span>Global Access Scope (Superadmin Clearance Required)</span>
        </div>
        <p class="text-[10px] text-slate-500 font-medium leading-relaxed">
          Only Super Administrators can grant or modify Global City-Wide Access scope.
        </p>
      </div>
      <?php endif; ?>

      <!-- Operational Scope Description -->
      <div class="space-y-1.5">
        <label for="roleDesc" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Operational Scope Description</label>
        <textarea 
          id="roleDesc" 
          required 
          rows="3" 
          placeholder="Describe the functional scope, authorization boundaries, and unit operational duties for this role..." 
          class="w-full p-3.5 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition leading-relaxed"
        ></textarea>
      </div>

      <!-- Status -->
      <div class="space-y-1.5">
        <label for="roleStatus" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Operational Status</label>
        <select 
          id="roleStatus" 
          required 
          class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:border-brand-medium transition cursor-pointer font-bold"
        >
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Archived">Archived</option>
        </select>
      </div>

      <!-- Modal Footer -->
      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
        <button 
          type="button" 
          onclick="closeModal('roleModal')" 
          class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-xs transition cursor-pointer"
        >
          Cancel
        </button>
        <button 
          type="submit" 
          class="px-5 py-2 bg-[#86B6F6] hover:bg-[#6fa5f5] text-slate-900 font-extrabold rounded-xl text-xs transition shadow-xs cursor-pointer flex items-center gap-1.5 border border-[#72a6eb]"
        >
          <i class="fa-solid fa-floppy-disk text-xs"></i>
          <span>Save Role</span>
        </button>
      </div>

    </form>

  </div>
</div>

<!-- 2. STATUS CHANGE CONFIRMATION MODAL -->
<div id="statusConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden transform scale-95 transition-all duration-300 border border-slate-200">
    
    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fa-solid fa-circle-minus text-amber-400"></i>
        <h3 class="font-extrabold text-sm tracking-tight uppercase">Confirm Role Deactivation</h3>
      </div>
      <button type="button" onclick="closeModal('statusConfirmModal')" class="text-slate-400 hover:text-white transition cursor-pointer text-sm">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="p-6 space-y-4">
      <div class="flex items-start gap-3.5">
        <div class="h-10 w-10 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shrink-0 shadow-2xs">
          <i class="fa-solid fa-triangle-exclamation text-base"></i>
        </div>
        <div class="space-y-1">
          <h4 class="font-bold text-slate-900 text-xs">Deactivate Role Capabilities?</h4>
          <p id="statusConfirmMessage" class="text-xs text-slate-500 leading-relaxed">
            Are you sure you want to deactivate this system role? All assigned users may lose active operational access permissions across CIVENTRAL network.
          </p>
        </div>
      </div>
    </div>

    <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-end space-x-2">
      <button type="button" onclick="closeModal('statusConfirmModal')" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer">Cancel</button>
      <button type="button" onclick="executeStatusDeactivate()" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer shadow-xs">Deactivate Role</button>
    </div>

  </div>
</div>

<!-- TOAST POPUP NOTIFICATION CONTAINER -->
<div id="toast" class="fixed bottom-4 right-4 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-3.5 rounded-xl shadow-lg flex items-center gap-3 transform translate-y-4 opacity-0 pointer-events-none transition-all duration-300">
  <div class="h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px]">
    <i class="fa-solid fa-check"></i>
  </div>
  <span id="toastMsg" class="tracking-wide">Action executed successfully.</span>
</div>

<script src="<?php echo $basePath ?? '../'; ?>assets/js/rolespermission/roles-management.js"></script>

<?php include '../../includes/footer.php'; ?>
