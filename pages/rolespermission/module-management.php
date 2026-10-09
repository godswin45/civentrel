<?php
$basePath = '../../';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto relative pb-28 bg-[#F8FAFC]">

  <!-- Breadcrumb & Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
    <div class="space-y-1">
      <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
        <span>Role & Permissions</span>
        <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
        <span class="text-brand-dark">Module Management</span>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5 mt-4">
        <i class="fa-solid fa-cubes text-brand-dark"></i>
        System Module Management
      </h1>
      <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
        Define, structure, and categorize central platform modules to serve as the foundation for Role-Based Access Control (RBAC).
      </p>
    </div>

    <!-- Primary Action Button -->
    <?php if (!empty($headerUser['is_superadmin']) || !empty($headerUser['is_global_access']) || (in_array('CREATE', $headerUser['granted_actions'] ?? []))): ?>
    <div class="shrink-0">
      <button 
        type="button"
        onclick="openCreateModuleModal()" 
        class="bg-[#86B6F6] hover:bg-[#6fa5f5] text-slate-900 font-extrabold px-4.5 py-2.5 rounded-xl text-xs transition duration-200 shadow-xs flex items-center gap-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-medium/30 border border-[#72a6eb]"
      >
        <i class="fa-solid fa-plus text-xs"></i>
        <span>Create New Module</span>
      </button>
    </div>
    <?php endif; ?>
  </div>

  <!-- REAL PAGE CONTENT -->
  <div id="moduleRealContent" class="space-y-6">

    <!-- RBAC Overview Metric Cards (3 columns) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <!-- Total System Modules -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Registered</span>
          <h3 id="metricTotalModules" class="text-2xl font-black text-slate-900 tracking-tight">0</h3>
          <p class="text-[11px] text-slate-500 font-medium">System Modules Registered</p>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-brand-light border border-brand-border/60 flex items-center justify-center text-brand-dark shrink-0">
          <i class="fa-solid fa-cubes text-lg"></i>
        </div>
      </div>

      <!-- Active RBAC Scopes -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Active Scopes</span>
          <h3 id="metricActiveModules" class="text-2xl font-black text-emerald-600 tracking-tight">0</h3>
          <p class="text-[11px] text-slate-500 font-medium">Active Functional Modules</p>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
          <i class="fa-solid fa-circle-check text-lg"></i>
        </div>
      </div>

      <!-- Inactive & Archived Modules -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Inactive & Archived</span>
          <h3 id="metricInactiveModules" class="text-2xl font-black text-amber-600 tracking-tight">0</h3>
          <p class="text-[11px] text-slate-500 font-medium">Inactive or Archived Modules</p>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shrink-0">
          <i class="fa-solid fa-box-archive text-lg"></i>
        </div>
      </div>
    </div>

    <!-- Module Directory Workspace -->
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

      <!-- Table Toolbar / Filters -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
        <div class="relative flex-1 max-w-md">
          <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input 
            type="text" 
            id="moduleSearchInput" 
            oninput="filterModules()" 
            placeholder="Search modules by Module Name..." 
            class="pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-xs w-full bg-white focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition"
          >
        </div>
      </div>

      <!-- Datatable Container -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
              <th class="px-6 py-4">Module Name</th>
              <th class="px-6 py-4">Description</th>
              <th class="px-6 py-4 text-center">Status</th>
              <th class="px-6 py-4">Created At</th>
              <th class="px-6 py-4">Updated At</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="moduleTableBody" class="divide-y divide-slate-100 text-xs text-slate-700">
            <!-- Populated dynamically by JS -->
          </tbody>
        </table>
      </div>

      <!-- Table Footer / Pagination -->
      <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-400">
        <div id="paginationText">
          Showing 0 to 0 of 0 modules
        </div>
        <div class="flex items-center space-x-1" id="paginationControls">
          <!-- Page buttons rendered by JS -->
        </div>
      </div>

    </div>

  </div> <!-- End #moduleRealContent -->

</main>

<!-- MODAL 1: CREATE / EDIT MODULE MODAL -->
<div id="moduleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs opacity-0 pointer-events-none transition-all duration-300">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform scale-95 transition-all duration-300" id="moduleModalCard">
    
    <!-- Modal Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
      <div class="flex items-center gap-2.5">
        <div class="h-8 w-8 rounded-xl bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark font-bold text-xs">
          <i class="fa-solid fa-cubes"></i>
        </div>
        <div>
          <h3 id="modalHeaderTitle" class="text-sm font-black text-slate-900 tracking-tight">Create New System Module</h3>
          <p class="text-[10px] text-slate-400 font-medium">Configure module parameters and status.</p>
        </div>
      </div>
      <button type="button" onclick="closeModuleModal()" class="h-8 w-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
        <i class="fa-solid fa-xmark text-sm"></i>
      </button>
    </div>

    <!-- Modal Form -->
    <form id="moduleForm" onsubmit="handleSaveModule(event)" class="p-6 space-y-4">
      <input type="hidden" id="formModuleId" value="">

      <!-- Module Name -->
      <div class="space-y-1.5">
        <label for="moduleName" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Module Name</label>
        <input 
          type="text" 
          id="moduleName" 
          required 
          placeholder="e.g. Health Services" 
          class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition"
        >
      </div>

      <!-- Status & Created At Row -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Status Selector -->
        <div class="space-y-1.5">
          <label for="moduleStatus" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Status</label>
          <select 
            id="moduleStatus" 
            required 
            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:border-brand-medium transition cursor-pointer"
          >
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Archived">Archived</option>
          </select>
        </div>

        <!-- Created At (Non-editable) -->
        <div class="space-y-1.5">
          <label for="moduleCreatedAt" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Created At</label>
          <input 
            type="text" 
            id="moduleCreatedAt" 
            readonly 
            disabled 
            placeholder="Auto-generated on save" 
            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs text-slate-500 bg-slate-50 font-mono cursor-not-allowed"
          >
        </div>
      </div>



      <!-- Description -->
      <div class="space-y-1.5">
        <label for="moduleDesc" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Functional Scope Description</label>
        <textarea 
          id="moduleDesc" 
          rows="3" 
          placeholder="Describe the functional scope and operational purpose of this system module..." 
          class="w-full p-3.5 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition leading-relaxed"
        ></textarea>
      </div>

      <!-- Modal Actions -->
      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
        <button 
          type="button" 
          onclick="closeModuleModal()" 
          class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-xs transition cursor-pointer"
        >
          Cancel
        </button>
        <button 
          type="submit" 
          class="px-5 py-2 bg-[#86B6F6] hover:bg-[#6fa5f5] text-slate-900 font-extrabold rounded-xl text-xs transition shadow-xs cursor-pointer flex items-center gap-1.5 border border-[#72a6eb]"
        >
          <i class="fa-solid fa-floppy-disk text-xs"></i>
          <span>Save Module</span>
        </button>
      </div>

    </form>

  </div>
</div>

<!-- MODAL 2: CONFIRM ARCHIVE MODAL -->
<div id="archiveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs opacity-0 pointer-events-none transition-all duration-300">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 transition-all duration-300" id="archiveModalCard">
    <div class="p-6 space-y-4 text-center">
      
      <!-- Warning Icon -->
      <div class="h-14 w-14 rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center mx-auto text-xl shadow-2xs">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>

      <div class="space-y-1">
        <h3 class="text-base font-black text-slate-900 tracking-tight">Archive System Module?</h3>
        <p id="archiveTargetName" class="text-xs font-bold text-brand-dark">Module: Health Services</p>
      </div>

      <!-- Warning Callout Box -->
      <div class="bg-amber-50/80 border border-amber-200/80 rounded-xl p-3.5 text-left text-xs leading-relaxed text-amber-800 flex items-start gap-2.5">
        <i class="fa-solid fa-circle-exclamation text-amber-600 text-sm mt-0.5 shrink-0"></i>
        <span>Archiving this module will restrict all RBAC role permissions associated with it across the CIVENTRAL network.</span>
      </div>

      <!-- Action Buttons -->
      <div class="pt-2 flex items-center justify-end gap-2.5">
        <button 
          type="button" 
          onclick="closeArchiveModal()" 
          class="w-1/2 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
        >
          Cancel
        </button>
        <button 
          type="button" 
          onclick="confirmArchiveModule()" 
          class="w-1/2 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5"
        >
          <i class="fa-solid fa-box-archive text-xs"></i>
          <span>Confirm Archive</span>
        </button>
      </div>

    </div>
  </div>
</div>

<!-- TOAST POPUP NOTIFICATION -->
<div id="toast" class="fixed bottom-4 right-4 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-3.5 rounded-xl shadow-lg flex items-center gap-3 transform translate-y-4 opacity-0 pointer-events-none transition-all duration-300">
  <div class="h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px]">
    <i class="fa-solid fa-check"></i>
  </div>
  <span id="toastMsg" class="tracking-wide">Action executed successfully.</span>
</div>

<script src="<?php echo $basePath ?? '../'; ?>assets/js/rolespermission/module-management.js"></script>

<?php include '../../includes/footer.php'; ?>
