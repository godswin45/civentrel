<?php
$basePath = '../../';
require_once __DIR__ . '/../../src/bootstrap.php';

// Access Control: Only Superadmins can manage Access Boundaries
if (empty($headerUser['is_superadmin'])) {
    header("Location: ../dashboard.php");
    exit;
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

  <!-- Breadcrumb & Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
    <div class="space-y-1">
      <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
        <span>Role & Permissions</span>
        <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
        <span class="text-brand-dark">Access Control (RBAC)</span>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
        <i class="fa-solid fa-sliders text-brand-dark"></i>
        Resource Access Control Mapping
      </h1>
      <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
        Configure organizational boundaries, department restrictions, and resource scopes for system roles.
      </p>
    </div>
  </div>

  <!-- SSO Concept Ribbon -->
  <div class="bg-blue-50/70 border border-blue-200/50 rounded-2xl p-4 flex items-center gap-4 text-xs shadow-xs text-blue-900">
    <div class="h-9 w-9 rounded-xl bg-blue-500 text-white flex items-center justify-center shrink-0 shadow-xs">
      <i class="fa-solid fa-key text-sm"></i>
    </div>
    <div class="space-y-1">
      <p class="font-extrabold tracking-tight">CIVENTRAL Identity & Access Management (SSO)</p>
      <div class="flex flex-wrap items-center gap-2 font-bold text-blue-800">
        <span class="bg-blue-100/60 px-2 py-0.5 rounded border border-blue-200/40 text-[10px]">1. Who are you? (Role)</span>
        <i class="fa-solid fa-arrow-right text-[9px] opacity-50"></i>
        <span class="bg-blue-100/60 px-2 py-0.5 rounded border border-blue-200/40 text-[10px]">2. What can you do? (Permissions)</span>
        <i class="fa-solid fa-arrow-right text-[9px] opacity-50"></i>
        <span class="bg-blue-100/60 px-2 py-0.5 rounded border border-blue-200/40 text-[10px]">3. Where can you do it? (Access Scope)</span>
      </div>
    </div>
  </div>

  <!-- Two Column Layout Config Panel -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Column 1: Select Role or User (1/3 width) -->
    <div class="space-y-4">
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs space-y-4">
        <div class="space-y-1">
          <h3 class="text-xs font-black uppercase tracking-wider text-slate-700">Security Profiles</h3>
          <p class="text-[10px] text-slate-400">Select a security role designation to map department resource access scopes.</p>
        </div>

        <!-- Role List Selector -->
        <div id="scopeRoleSelectorList" class="space-y-2">
          <!-- Dynamically populated by JS -->
        </div>
      </div>
    </div>

    <!-- Column 2: Define Scope & Restrictions (2/3 width) -->
    <div class="lg:col-span-2 space-y-6">
      
      <!-- Scope Panel Details -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-xs space-y-6">
        
        <!-- Header status -->
        <div class="border-b border-slate-100 pb-4">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Boundary Mapping Module</p>
          <h2 id="scopeSelectedRoleTitle" class="text-sm font-black text-slate-900 tracking-tight">Access Boundaries for: Loading...</h2>
        </div>

        <!-- Scope Toggles -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 bg-slate-50 p-4 border border-slate-200/60 rounded-xl">
          <!-- Switch 1: Global Access -->
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-0.5">
              <span class="text-xs font-bold text-slate-800 block">Global Access Mode</span>
              <p class="text-[10px] text-slate-450 leading-relaxed">Overrides all restriction rules, granting clearance to every department.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer select-none">
              <input type="checkbox" id="toggleGlobalAccess" onchange="handleGlobalToggle(this)" class="sr-only peer">
              <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-brand-medium/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-[#0f172a]"></div>
            </label>
          </div>

          <!-- Switch 2: Department Isolation -->
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-0.5">
              <span class="text-xs font-bold text-slate-800 block">Department Lockin</span>
              <p class="text-[10px] text-slate-450 leading-relaxed">Locks access exclusively to the user's primary department unit.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer select-none">
              <input type="checkbox" id="toggleDeptLockin" onchange="handleLockinToggle(this)" class="sr-only peer">
              <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-brand-medium/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-[#0f172a]"></div>
            </label>
          </div>
        </div>

        <!-- Module/Department Card Choices -->
        <div class="space-y-3">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Authorized Scope Departments</label>
          <div id="deptCardsGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Dynamically Populated Cards by JS -->
          </div>
        </div>

        <!-- Token Preview Panel -->
        <div class="space-y-2">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block flex items-center gap-1.5">
            <i class="fa-solid fa-code text-slate-400"></i>
            Core API SSO Authorization Token Preview
          </label>
          
          <div class="bg-[#0f172a] text-slate-300 rounded-xl p-4 font-mono text-[10px] leading-relaxed shadow-inner border border-slate-900 overflow-x-auto select-all">
            <pre><code id="tokenJsonCode"><!-- JSON preview --></code></pre>
          </div>
        </div>

        <!-- Action Footer -->
        <div class="border-t border-slate-100 pt-5 flex items-center justify-end space-x-3">
          <button onclick="discardScopeChanges()" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-5 py-2.5 rounded-xl text-xs font-bold cursor-pointer transition shadow-xs">
            Discard Changes
          </button>
          <button onclick="saveScopeChanges()" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-xs cursor-pointer transition shadow-xs">
            Save Boundary Rules
          </button>
        </div>

      </div>
    </div>

  </div>

  <!-- ========================================================================= -->
  <!-- TOAST NOTIFICATION HUB -->
  <!-- ========================================================================= -->
  <div id="toast" class="fixed bottom-4 right-4 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-3.5 rounded-xl shadow-lg flex items-center gap-3 transform translate-y-4 opacity-0 pointer-events-none transition-all duration-300">
    <div id="toastIconBox" class="h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px]">
      <i id="toastIconSymbol" class="fa-solid fa-check"></i>
    </div>
    <span id="toastMsg" class="tracking-wide">Access boundary rules successfully compiled.</span>
  </div>

</main>

<script src="<?php echo $basePath ?? '../'; ?>assets/js/rolespermission/access-control.js"></script>

<?php include '../../includes/footer.php'; ?>
