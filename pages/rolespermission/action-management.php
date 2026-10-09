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
        <span class="text-brand-dark">Action Operations</span>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5 mt-4">
        <i class="fa-solid fa-bolt text-brand-dark"></i>
        System Action Operations Management
      </h1>
      <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
        Define reusable operational verbs and system privileges that map onto resources to generate granular role permissions.
      </p>
    </div>

    <!-- Primary Action Button -->
    <?php if (!empty($headerUser['is_superadmin']) || !empty($headerUser['is_global_access']) || (in_array('CREATE', $headerUser['granted_actions'] ?? []))): ?>
    <div class="shrink-0">
      <button 
        type="button"
        onclick="openCreateActionModal()" 
        class="bg-[#86B6F6] hover:bg-[#6fa5f5] text-slate-900 font-extrabold px-4.5 py-2.5 rounded-xl text-xs transition duration-200 shadow-xs flex items-center gap-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-medium/30 border border-[#72a6eb]"
      >
        <i class="fa-solid fa-plus text-xs"></i>
        <span>Create New Action</span>
      </button>
    </div>
    <?php endif; ?>
  </div>

  <!-- FULL CONTENT SKELETON LOADER -->
  <div id="actionSkeleton" class="space-y-6 transition-all duration-500 opacity-100 pointer-events-auto">
    <!-- Cards Skeleton -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <?php for($i=0; $i<3; $i++): ?>
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
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-24 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-36 rounded-md"></div></th>
              <th class="px-6 py-4"><div class="skeleton-loader h-3 w-20 rounded-md"></div></th>
              <th class="px-6 py-4 text-right"><div class="skeleton-loader h-3 w-20 rounded-md ml-auto"></div></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php for($k=0; $k<5; $k++): ?>
            <tr class="animate-pulse">
              <td class="px-6 py-4"><div class="skeleton-loader h-3.5 w-24 rounded-md"></div></td>
              <td class="px-6 py-4"><div class="skeleton-loader h-3.5 w-48 rounded-md"></div></td>
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
  <div id="actionRealContent" class="space-y-6 hidden opacity-0 transition-all duration-700 ease-out transform translate-y-2">

    <!-- Action Overview Metric Cards (3 columns) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <!-- Total Registered Actions -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Registered</span>
          <h3 id="metricTotalActions" class="text-2xl font-black text-slate-900 tracking-tight">0</h3>
          <p class="text-[11px] text-slate-500 font-medium">System Action Verbs</p>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-brand-light border border-brand-border/60 flex items-center justify-center text-brand-dark shrink-0">
          <i class="fa-solid fa-bolt text-lg"></i>
        </div>
      </div>

      <!-- Active Actions -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Active Verbs</span>
          <h3 id="metricActiveActions" class="text-2xl font-black text-emerald-600 tracking-tight">0</h3>
          <p class="text-[11px] text-slate-500 font-medium">Active Action Privileges</p>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
          <i class="fa-solid fa-circle-check text-lg"></i>
        </div>
      </div>

      <!-- Inactive & Archived Actions -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Inactive / Archived</span>
          <h3 id="metricInactiveActions" class="text-2xl font-black text-amber-600 tracking-tight">0</h3>
          <p class="text-[11px] text-slate-500 font-medium">Disabled or Archived Actions</p>
        </div>
        <div class="h-12 w-12 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shrink-0">
          <i class="fa-solid fa-box-archive text-lg"></i>
        </div>
      </div>
    </div>

    <!-- Actions Directory Control Panel & Datatable Workspace -->
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

      <!-- Control Panel & Filters -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
        <!-- Search Input -->
        <div class="relative flex-1 max-w-md">
          <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input 
            type="text" 
            id="actionSearchInput" 
            oninput="filterActions()" 
            placeholder="Search actions by Action Name or Description..." 
            class="pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-xs w-full bg-white focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition"
          >
        </div>
      </div>

      <!-- Structured Action Operations Datatable -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
              <th class="px-6 py-4">Action Name</th>
              <th class="px-6 py-4">Description</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="actionTableBody" class="divide-y divide-slate-100 text-xs text-slate-700">
            <!-- Populated dynamically by JS -->
          </tbody>
        </table>
      </div>

      <!-- Table Footer / Pagination -->
      <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-400">
        <div id="paginationText">
          Showing 0 to 0 of 0 actions
        </div>
        <div class="flex items-center space-x-1" id="paginationControls">
          <!-- Page buttons rendered by JS -->
        </div>
      </div>

    </div>

  </div> <!-- End #actionRealContent -->

</main>

</main>

<!-- MODAL 1: CREATE / EDIT ACTION MODAL -->
<div id="actionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden transition-opacity duration-200">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform transition-all scale-95 opacity-0" id="actionModalCard">
    
    <!-- Modal Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
      <div class="flex items-center gap-2.5">
        <div class="h-8 w-8 rounded-xl bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark font-bold text-xs">
          <i class="fa-solid fa-bolt"></i>
        </div>
        <div>
          <h3 id="modalHeaderTitle" class="text-sm font-black text-slate-900 tracking-tight">Create New System Action</h3>
          <p class="text-[10px] text-slate-400 font-medium">Define operational verbs used for RBAC permission checks.</p>
        </div>
      </div>
      <button type="button" onclick="closeActionModal()" class="h-8 w-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
        <i class="fa-solid fa-xmark text-sm"></i>
      </button>
    </div>

    <!-- Modal Form -->
    <form id="actionForm" onsubmit="handleSaveAction(event)" class="p-6 space-y-4">
      <input type="hidden" id="formActionId" value="">

      <!-- Action Name -->
      <div class="space-y-1.5">
        <label for="actionName" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Action Name</label>
        <input 
          type="text" 
          id="actionName" 
          required 
          placeholder="e.g. Approve" 
          class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition"
        >
      </div>

      <!-- Status & Created At Row -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Status Selector -->
        <div class="space-y-1.5">
          <label for="actionStatus" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Status</label>
          <select 
            id="actionStatus" 
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
          <label for="actionCreatedAt" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Created At</label>
          <input 
            type="text" 
            id="actionCreatedAt" 
            readonly 
            disabled 
            placeholder="Auto-generated on save" 
            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs text-slate-500 bg-slate-50 font-mono cursor-not-allowed"
          >
        </div>
      </div>

      <!-- Description -->
      <div class="space-y-1.5">
        <label for="actionDesc" class="text-[10px] font-black text-slate-500 uppercase tracking-wider block">Operational Boundary Description</label>
        <textarea 
          id="actionDesc" 
          rows="3" 
          placeholder="Grants privilege to validate and approve submitted citizen applications..." 
          class="w-full p-3.5 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/10 transition leading-relaxed"
        ></textarea>
      </div>

      <!-- Modal Actions -->
      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
        <button 
          type="button" 
          onclick="closeActionModal()" 
          class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-xs transition cursor-pointer"
        >
          Cancel
        </button>
        <button 
          type="submit" 
          class="px-5 py-2 bg-[#86B6F6] hover:bg-[#6fa5f5] text-slate-900 font-extrabold rounded-xl text-xs transition shadow-xs cursor-pointer flex items-center gap-1.5 border border-[#72a6eb]"
        >
          <i class="fa-solid fa-floppy-disk text-xs"></i>
          <span>Save Action</span>
        </button>
      </div>

    </form>

  </div>
</div>

<!-- MODAL 2: CONFIRM ARCHIVE MODAL -->
<div id="archiveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden transition-opacity duration-200">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform transition-all scale-95 opacity-0" id="archiveModalCard">
    <div class="p-6 space-y-4 text-center">
      
      <!-- Warning Icon -->
      <div class="h-14 w-14 rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center mx-auto text-xl shadow-2xs">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>

      <div class="space-y-1">
        <h3 class="text-base font-black text-slate-900 tracking-tight">Archive Operational Action?</h3>
        <p id="archiveTargetName" class="text-xs font-bold text-brand-dark">Action: Approve (act_approve)</p>
      </div>

      <!-- Warning Callout Box -->
      <div class="bg-amber-50/80 border border-amber-200/80 rounded-xl p-3.5 text-left text-xs leading-relaxed text-amber-800 flex items-start gap-2.5">
        <i class="fa-solid fa-circle-exclamation text-amber-600 text-sm mt-0.5 shrink-0"></i>
        <span>Archiving this action operation will automatically strip this privilege from all active resource permissions across CIVENTRAL roles.</span>
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
          onclick="confirmArchiveAction()" 
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

<script>
// INITIAL SYSTEM ACTION VERBS DATASTORE
// HELPER: GET NOW TIMESTAMP
function getNowTimestamp() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  const hours = String(now.getHours()).padStart(2, '0');
  const mins = String(now.getMinutes()).padStart(2, '0');
  const secs = String(now.getSeconds()).padStart(2, '0');
  return `${year}-${month}-${day} ${hours}:${mins}:${secs}`;
}

// INITIAL SYSTEM ACTION VERBS DATASTORE CONNECTED TO DATABASE
let systemActions = [];
let currentUserScope = null;
let archiveTargetActionId = null;

var currentTabStatus = 'Active';
var currentPage = 1;
var pageSize = 10;

function switchStatusTab(targetStatus) {
  currentTabStatus = targetStatus;
  currentPage = 1;

  document.querySelectorAll('.status-tab-btn').forEach(btn => {
    const tabVal = btn.getAttribute('data-status-tab');
    if (tabVal === targetStatus) {
      btn.className = "status-tab-btn px-4 py-2.5 rounded-t-xl text-xs font-black transition-all cursor-pointer bg-white text-[#176B87] border-t-2 border-[#86B6F6] border-x border-slate-200/80 shadow-2xs";
    } else {
      btn.className = "status-tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition-all cursor-pointer text-slate-500 hover:text-slate-800 hover:bg-slate-100/60";
    }
  });

  filterActions();
}

// FETCH ACTIONS FROM Database API
async function fetchActions() {
  try {
    const response = await fetch('../../api/employee/actions.php');
    const result = await response.json();
    if (result.status === 'success' && Array.isArray(result.data)) {
      systemActions = result.data.map(a => ({
        id: a.action_id,
        name: a.action_name,
        desc: a.description || '',
        status: a.status || 'Active',
        created_at: a.created_at ? a.created_at.replace('T', ' ').substring(0, 19) : '',
        updated_at: a.updated_at ? a.updated_at.replace('T', ' ').substring(0, 19) : ''
      }));
      currentUserScope = result.current_user || null;
      filterActions();
    } else {
      console.warn('Actions fetch notice:', result.message);
    }
  } catch (err) {
    console.error('Error fetching actions FROM DATABASE:', err);
    showToast('Network error connecting to Database.');
  } finally {
    hideActionSkeleton();
  }
}

// RENDER ACTIONS DATATABLE
function renderActionsTable(dataToRender = systemActions) {
  const tableBody = document.getElementById('actionTableBody');
  const emptyState = document.getElementById('emptyTableState');
  if (!tableBody) return;

  tableBody.innerHTML = '';

  const totalFiltered = dataToRender.length;
  const totalPages = Math.ceil(totalFiltered / pageSize) || 1;
  if (currentPage > totalPages) currentPage = totalPages;
  if (currentPage < 1) currentPage = 1;

  const startIndex = (currentPage - 1) * pageSize;
  const endIndex = Math.min(startIndex + pageSize, totalFiltered);
  const pageData = dataToRender.slice(startIndex, endIndex);

  if (totalFiltered === 0) {
    if (emptyState) emptyState.classList.remove('hidden');
    renderPaginationUI(0, 0, 0, 1);
    updateActionMetrics();
    return;
  } else {
    if (emptyState) emptyState.classList.add('hidden');
  }

  const isSuperAdmin = currentUserScope ? !!currentUserScope.is_superadmin : false;
  const grantedActions = currentUserScope ? (currentUserScope.granted_actions || []) : [];
  const canEdit = isSuperAdmin || grantedActions.includes('EDIT');

  pageData.forEach(act => {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50/60 transition group';

    // Status Badge HTML
    let statusBadgeHtml = '';
    const aStatus = act.status || 'Active';
    if (aStatus === 'Active') {
      statusBadgeHtml = `
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          Active
        </span>
      `;
    } else if (aStatus === 'Inactive') {
      statusBadgeHtml = `
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-600 border border-slate-200">
          <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
          Inactive
        </span>
      `;
    } else {
      statusBadgeHtml = `
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-50 text-amber-700 border border-amber-200">
          <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
          Archived
        </span>
      `;
    }

    const isChecked = aStatus === 'Active';
    const isArchived = aStatus === 'Archived';

    tr.innerHTML = `
      <td class="px-6 py-4">
        <span class="font-extrabold text-slate-900 tracking-tight block text-xs">${act.name}</span>
      </td>

      <td class="px-6 py-4 max-w-xs">
        <p class="text-xs text-slate-600 font-medium leading-relaxed">${act.desc || '<span class="text-slate-400 italic">No description</span>'}</p>
      </td>

      <td class="px-6 py-4 text-center">
        ${statusBadgeHtml}
      </td>

      <td class="px-6 py-4 text-slate-500 font-mono text-[10px] font-bold">
        ${act.created_at}
      </td>

      <td class="px-6 py-4 text-slate-500 font-mono text-[10px] font-bold">
        ${act.updated_at}
      </td>

      <td class="px-6 py-4 text-right">
        <div class="flex items-center justify-end gap-3">
          ${canEdit ? `
          <!-- iOS Toggle Switch -->
          <label class="relative inline-flex items-center cursor-pointer ${isArchived ? 'opacity-50 pointer-events-none' : ''}" title="Activate/Deactivate Action Verb">
            <input 
              type="checkbox" 
              ${isChecked ? 'checked' : ''} 
              onchange="toggleActionStatus(${act.id})" 
              class="sr-only peer"
            >
            <div class="w-8 h-4.5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-emerald-600"></div>
          </label>

          <!-- Edit Button -->
          <button 
            type="button" 
            onclick="openEditActionModal(${act.id})" 
            class="h-8 w-8 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-brand-dark flex items-center justify-center transition cursor-pointer"
            title="Edit Action Verb"
          >
            <i class="fa-solid fa-pen-to-square text-xs"></i>
          </button>

          <!-- Archive Button -->
          <button 
            type="button" 
            onclick="openArchiveActionModal(${act.id})" 
            class="h-8 w-8 rounded-lg border border-slate-200 hover:bg-amber-50 text-slate-400 hover:text-amber-600 flex items-center justify-center transition cursor-pointer ${isArchived ? 'opacity-40 cursor-not-allowed' : ''}"
            ${isArchived ? 'disabled' : ''}
            title="Archive Action"
          >
            <i class="fa-solid fa-box-archive text-xs"></i>
          </button>` : `<span class="text-[10px] text-slate-400 font-bold italic">Read-only</span>`}
        </div>
      </td>
    `;

    tableBody.appendChild(tr);
  });

  renderPaginationUI(startIndex, endIndex, totalFiltered, totalPages);
  updateActionMetrics();
}

function renderPaginationUI(startIndex, endIndex, totalFiltered, totalPages) {
  const paginationEl = document.getElementById('paginationText');
  const controlsEl = document.getElementById('paginationControls');

  if (paginationEl) {
    if (totalFiltered === 0) {
      paginationEl.innerText = "Showing 0 to 0 of 0 actions";
    } else {
      paginationEl.innerText = `Showing ${startIndex + 1} to ${endIndex} of ${totalFiltered} actions`;
    }
  }

  if (!controlsEl) return;
  controlsEl.innerHTML = '';

  if (totalPages <= 1) return;

  // Prev Button
  const prevBtn = document.createElement('button');
  prevBtn.className = `px-3 py-1.5 rounded-lg border text-xs font-bold transition flex items-center justify-center ${currentPage === 1 ? 'border-slate-200 bg-white text-slate-300 cursor-not-allowed' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-600 cursor-pointer'}`;
  prevBtn.disabled = currentPage === 1;
  prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-[9px]"></i>';
  prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; filterActions(); } };
  controlsEl.appendChild(prevBtn);

  // Page Numbers
  for (let i = 1; i <= totalPages; i++) {
    const pBtn = document.createElement('button');
    if (i === currentPage) {
      pBtn.className = "px-3 py-1.5 rounded-lg bg-[#86B6F6] border border-[#72a6eb] text-slate-900 font-black shadow-2xs text-xs";
    } else {
      pBtn.className = "px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-bold transition text-xs cursor-pointer";
    }
    pBtn.innerText = i;
    pBtn.onclick = () => { currentPage = i; filterActions(); };
    controlsEl.appendChild(pBtn);
  }

  // Next Button
  const nextBtn = document.createElement('button');
  nextBtn.className = `px-3 py-1.5 rounded-lg border text-xs font-bold transition flex items-center justify-center ${currentPage === totalPages ? 'border-slate-200 bg-white text-slate-300 cursor-not-allowed' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-600 cursor-pointer'}`;
  nextBtn.disabled = currentPage === totalPages;
  nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-[9px]"></i>';
  nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; filterActions(); } };
  controlsEl.appendChild(nextBtn);
}

// UPDATE METRIC CARDS AND TAB COUNTS
function updateActionMetrics() {
  const total = systemActions.length;
  const activeCount = systemActions.filter(a => (a.status || 'Active') === 'Active').length;
  const inactiveCount = systemActions.filter(a => (a.status || '').toLowerCase() === 'inactive').length;
  const archivedCount = systemActions.filter(a => (a.status || '').toLowerCase() === 'archived').length;

  const totalEl = document.getElementById('metricTotalActions');
  const activeEl = document.getElementById('metricActiveActions');
  const inactiveEl = document.getElementById('metricInactiveActions');

  if (totalEl) totalEl.textContent = total;
  if (activeEl) activeEl.textContent = activeCount;
  if (inactiveEl) inactiveEl.textContent = inactiveCount + archivedCount;

  // Update tab counts
  const tabActive = document.getElementById('tabCountActive');
  const tabInactive = document.getElementById('tabCountInactive');
  const tabArchived = document.getElementById('tabCountArchived');
  const tabAll = document.getElementById('tabCountAll');

  if (tabActive) tabActive.innerText = activeCount;
  if (tabInactive) tabInactive.innerText = inactiveCount;
  if (tabArchived) tabArchived.innerText = archivedCount;
  if (tabAll) tabAll.innerText = total;

  hideActionSkeleton();
}

function hideActionSkeleton() {
  const skel = document.getElementById('actionSkeleton');
  const real = document.getElementById('actionRealContent');
  if (!skel || !real) return;

  real.classList.remove('hidden');
  requestAnimationFrame(() => {
    skel.classList.add('opacity-0', 'pointer-events-none');
    real.classList.remove('opacity-0', 'translate-y-2');
    real.classList.add('opacity-100', 'translate-y-0');
    setTimeout(() => {
      skel.classList.add('hidden');
    }, 500);
  });
}

// FILTER ACTIONS REAL TIME
function filterActions() {
  const searchInput = document.getElementById('actionSearchInput');
  const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

  const filtered = systemActions.filter(act => {
    const matchesQuery = act.name.toLowerCase().includes(query) || 
                         (act.desc && act.desc.toLowerCase().includes(query));

    const aStatus = act.status || 'Active';
    const matchesStatus = currentTabStatus === 'ALL' || aStatus.toLowerCase() === currentTabStatus.toLowerCase();

    return matchesQuery && matchesStatus;
  });

  renderActionsTable(filtered);
}

// UPDATE ACTION STATUS IN DATABASE
async function updateActionStatusInDb(actionId, newStatus) {
  try {
    const response = await fetch('../../api/employee/actions.php', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action_id: actionId, status: newStatus })
    });
    const result = await response.json();
    if (result.status === 'success') {
      showToast(`Action status updated to ${newStatus}.`);
      await fetchActions();
      switchStatusTab(newStatus);
    } else {
      showToast(result.message || 'Failed to update action status.');
    }
  } catch (err) {
    console.error('Error updating status:', err);
    showToast('Error updating status IN DATABASE.');
  }
}

// TOGGLE STATUS
async function toggleActionStatus(id) {
  const act = systemActions.find(a => a.id === id);
  if (!act) return;

  const nextStatus = act.status === 'Active' ? 'Inactive' : 'Active';
  await updateActionStatusInDb(id, nextStatus);
}

// MODAL CONTROLS - CREATE / EDIT
function openCreateActionModal() {
  const isSuperAdmin = currentUserScope ? !!currentUserScope.is_superadmin : false;
  const grantedActions = currentUserScope ? (currentUserScope.granted_actions || []) : [];
  const canCreate = isSuperAdmin || grantedActions.includes('CREATE');

  if (!canCreate) {
    showToast('Forbidden. View-only access level cannot create system action verbs.');
    return;
  }

  document.getElementById('formActionId').value = '';
  document.getElementById('actionForm').reset();
  document.getElementById('modalHeaderTitle').textContent = 'Create New System Action';
  document.getElementById('actionStatus').value = 'Active';
  document.getElementById('actionCreatedAt').value = 'Auto-generated on save';
  
  showModalOverlay('actionModal', 'actionModalCard');
}

function openEditActionModal(id) {
  const isSuperAdmin = currentUserScope ? !!currentUserScope.is_superadmin : false;
  const grantedActions = currentUserScope ? (currentUserScope.granted_actions || []) : [];
  const canEdit = isSuperAdmin || grantedActions.includes('EDIT');

  if (!canEdit) {
    showToast('Forbidden. View-only access level cannot modify system action verbs.');
    return;
  }

  const act = systemActions.find(a => a.id === id);
  if (!act) return;

  document.getElementById('formActionId').value = act.id;
  document.getElementById('actionName').value = act.name;
  document.getElementById('actionStatus').value = act.status || 'Active';
  document.getElementById('actionCreatedAt').value = act.created_at || '';
  document.getElementById('actionDesc').value = act.desc || '';

  document.getElementById('modalHeaderTitle').textContent = `Edit Action: ${act.name}`;

  showModalOverlay('actionModal', 'actionModalCard');
}

function closeActionModal() {
  hideModalOverlay('actionModal', 'actionModalCard');
}

async function handleSaveAction(event) {
  event.preventDefault();

  const idVal = document.getElementById('formActionId').value;
  const name = document.getElementById('actionName').value.trim();
  const status = document.getElementById('actionStatus').value;
  const desc = document.getElementById('actionDesc').value.trim();

  const payload = {
    action_name: name,
    description: desc,
    status: status
  };

  if (idVal !== '') {
    payload.action_id = parseInt(idVal);
  }

  const method = idVal === '' ? 'POST' : 'PUT';

  try {
    const response = await fetch('../../api/employee/actions.php', {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json();

    if (result.status === 'success') {
      showToast(result.message || 'Action saved successfully.');
      closeActionModal();
      await fetchActions();
    } else {
      showToast(result.message || 'Failed to save action.');
    }
  } catch (err) {
    console.error('Error saving action:', err);
    showToast('Failed to save action TO DATABASE.');
  }
}

// MODAL CONTROLS - ARCHIVE
function openArchiveActionModal(id) {
  const act = systemActions.find(a => a.id === id);
  if (!act) return;

  archiveTargetActionId = id;
  const targetNameEl = document.getElementById('archiveTargetName');
  if (targetNameEl) targetNameEl.textContent = `Action: ${act.name}`;

  showModalOverlay('actionModal', 'actionModalCard');
}

function closeArchiveModal() {
  archiveTargetActionId = null;
  hideModalOverlay('archiveModal', 'archiveModalCard');
}

async function confirmArchiveAction() {
  if (!archiveTargetActionId) return;

  const targetId = archiveTargetActionId;
  closeArchiveModal();
  await updateActionStatusInDb(targetId, 'Archived');
}

// MODAL ANIMATION HELPERS
function showModalOverlay(modalId, cardId) {
  const modal = document.getElementById(modalId);
  const card = document.getElementById(cardId);
  if (!modal || !card) return;

  modal.classList.remove('hidden');
  setTimeout(() => {
    card.classList.remove('scale-95', 'opacity-0');
    card.classList.add('scale-100', 'opacity-100');
  }, 10);
}

function hideModalOverlay(modalId, cardId) {
  const modal = document.getElementById(modalId);
  const card = document.getElementById(cardId);
  if (!modal || !card) return;

  card.classList.remove('scale-100', 'opacity-100');
  card.classList.add('scale-95', 'opacity-0');
  setTimeout(() => {
    modal.classList.add('hidden');
  }, 200);
}

// DISMISS MODALS ON BACKDROP CLICK & ESCAPE KEY
document.addEventListener('click', (e) => {
  if (e.target.id === 'actionModal') closeActionModal();
  if (e.target.id === 'archiveModal') closeArchiveModal();
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeActionModal();
    closeArchiveModal();
  }
});

// TOAST NOTIFICATION
let actionToastTimer = null;
function showToast(message, isError = false) {
  const toast = document.getElementById('toast');
  const toastMsg = document.getElementById('toastMsg');
  let iconBox = document.getElementById('toastIconBox') || (toast ? toast.querySelector('div') : null);
  let iconSymbol = document.getElementById('toastIconSymbol') || (iconBox ? iconBox.querySelector('i') : null);

  if (!toast || !toastMsg) return;

  const msgLower = (message || '').toString().toLowerCase();
  if (!isError && (
    msgLower.includes('error') || 
    msgLower.includes('failed') || 
    msgLower.includes('invalid') || 
    msgLower.includes('network') || 
    msgLower.includes('require') || 
    msgLower.includes('forbidden') || 
    msgLower.includes('denied') || 
    msgLower.includes('cannot') ||
    msgLower.includes('unable') ||
    msgLower.includes('please')
  )) {
    isError = true;
  }

  toastMsg.innerText = message;

  if (iconBox) {
    if (isError) {
      iconBox.className = "h-5 w-5 rounded-full bg-rose-500 flex items-center justify-center text-white text-[10px] shrink-0";
    } else {
      iconBox.className = "h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px] shrink-0";
    }
  }

  if (iconSymbol) {
    if (isError) {
      iconSymbol.className = "fa-solid fa-xmark";
    } else {
      iconSymbol.className = "fa-solid fa-check";
    }
  }

  toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
  toast.classList.add('opacity-100', 'translate-y-0');

  if (actionToastTimer) clearTimeout(actionToastTimer);
  actionToastTimer = setTimeout(() => {
    toast.classList.remove('opacity-100', 'translate-y-0');
    toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
  }, 3200);
}

// INITIAL DOM READY BINDING
document.addEventListener('DOMContentLoaded', () => {
  fetchActions();
});
</script>

<?php include '../../includes/footer.php'; ?>
