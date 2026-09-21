    <?php
      $currentPage = basename($_SERVER['PHP_SELF']);

      $usermanagementPages = [
        'user-directory.php',
        'create-account.php',
        'account-status.php'
      ];

      $rolesmanagementPages = [
        'roles-management.php',
        'permissions.php',
        'module-management.php',
        'resource-management.php',
        'resourcemanagement.php',
        'action-management.php',
        'actionmanagement.php',
        'access-control.php'
      ];

      $departmentmanagementPages = [
        'departments.php'
      ];

      $citizenPages = [
        'citizen-directory.php',
        'citizen-account.php'
      ];

      $auditPages = [
        'user-activities.php',
        'login-history.php',
        'data-changes.php'
      ];

      $budgetPages = [
        'budget-index.php',
        'budget-my-requests.php',
        'budget-approvals.php',
        'index.php',
        'my-requests.php'
      ];

      $treasuryPages = [
        'index.php',
        'budget-approvals.php',
        'business-tax.php',
        'market-stall.php',
        'collection.php',
        'disbursement.php',
        'business.php',
        'online-payments.php',
        'reports.php'
      ];

      $isSuperAdmin = !empty($headerUser['is_superadmin']) || !empty($headerUser['is_global_access']);
      $userGrantedRes = $headerUser['granted_resources'] ?? [];

      // Dynamic RBAC Permission Checker
      $hasResourceAccess = function($keywords) use ($isSuperAdmin, $userGrantedRes) {
          if ($isSuperAdmin) return true;
          if (empty($userGrantedRes)) return false;
          if (is_string($keywords)) $keywords = [$keywords];
          foreach ($userGrantedRes as $resName) {
              $resLower = strtolower($resName);
              foreach ($keywords as $kw) {
                  if (strpos($resLower, strtolower($kw)) !== false) return true;
              }
          }
          return false;
      };
    ?>
    
    <aside id="sidebar" class="bg-brand-light text-slate-600 w-72 min-h-[calc(100vh-5rem)] flex flex-col justify-between transition-all duration-300 border-r border-brand-border/60 sticky top-20 h-[calc(100vh-5rem)] z-30 shrink-0 shadow-sm">
      
      <div class="flex flex-col h-full overflow-hidden">
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto custom-scrollbar">
          
          <div class="sidebar-divider px-1 pb-3 mb-2 border-b">
            <button onclick="toggleSidebar()" class="sidebar-collapse-btn w-full py-2 rounded-xl border flex items-center justify-center focus:outline-none transition cursor-pointer shadow-xs" title="Collapse Menu Panel">
              <i id="toggleArrow" class="fa-solid fa-chevron-left text-xs"></i>
            </button>
          </div>

          <!-- UPPER SECTION: TREASURY MODULES -->
          <span class="sidebar-text text-[9px] font-bold tracking-widest text-slate-400 uppercase block px-3 mb-2">Treasury Operations</span>

          <!-- Treasury Overview / Primary Dashboard -->
          <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo ($currentPage == 'index.php' && strpos($_SERVER['PHP_SELF'], '/treasury/') !== false) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
            <i class="fa-solid fa-vault text-sm <?php echo ($currentPage == 'index.php' && strpos($_SERVER['PHP_SELF'], '/treasury/') !== false) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
            <span class="sidebar-text truncate">Treasury Dashboard</span>
          </a>

          <!-- Revenue Collection -->
          <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/collection.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo $currentPage == 'collection.php' ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
            <i class="fa-solid fa-cash-register text-sm <?php echo $currentPage == 'collection.php' ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
            <span class="sidebar-text truncate">Revenue Collection</span>
          </a>

          <!-- Disbursement & Vouchers -->
          <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/disbursement.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo $currentPage == 'disbursement.php' ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
            <i class="fa-solid fa-hand-holding-dollar text-sm <?php echo $currentPage == 'disbursement.php' ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
            <span class="sidebar-text truncate">Disbursements & Vouchers</span>
          </a>

          <!-- Budget Management Dropdown -->
          <?php 
            $canAccessBudget = $isSuperAdmin || $hasResourceAccess(['budget', 'approval', 'treasury']);
            if ($canAccessBudget): 
          ?>
          <div class="space-y-1">
            <button onclick="toggleDropdown('budgetDropdown', 'budgetChevron')" class="dropdown-btn w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo (in_array($currentPage, ['budget-approvals.php', 'index.php', 'my-requests.php']) && strpos($_SERVER['PHP_SELF'], '/budget') !== false) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
              <div class="flex items-center space-x-3">
                <i class="fa-solid fa-money-bill-trend-up text-sm <?php echo (strpos($_SERVER['PHP_SELF'], '/budget') !== false) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
                <span class="sidebar-text truncate">Budget Management</span>
              </div>
              <div class="dropdown-right">
                <i id="budgetChevron" class="fa-solid fa-chevron-down text-[10px] opacity-60 dropdown-chevron transition-transform duration-200 <?php echo (strpos($_SERVER['PHP_SELF'], '/budget') !== false || $currentPage == 'budget-approvals.php') ? 'rotate-180' : ''; ?>"></i>
              </div>
            </button>
            <div id="budgetDropdown" class="<?php echo (strpos($_SERVER['PHP_SELF'], '/budget') !== false || $currentPage == 'budget-approvals.php') ? '' : 'hidden'; ?> pl-8 pr-2 space-y-0.5 font-medium sidebar-text">
              <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/budget-approvals.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'budget-approvals.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-check-double text-[10px] <?php echo $currentPage == 'budget-approvals.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Budget Approvals</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/budget/index.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo ($currentPage == 'index.php' && strpos($_SERVER['PHP_SELF'], '/budget/') !== false) ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-chart-line text-[10px] <?php echo ($currentPage == 'index.php' && strpos($_SERVER['PHP_SELF'], '/budget/') !== false) ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Budget Overview</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/budget/my-requests.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'my-requests.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-file-invoice text-[10px] <?php echo $currentPage == 'my-requests.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Department Requests</span></a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Business Tax & Licensing Dropdown -->
          <div class="space-y-1">
            <button onclick="toggleDropdown('businessTaxDropdown', 'businessTaxChevron')" class="dropdown-btn w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo in_array($currentPage, ['business-tax.php', 'business.php']) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
              <div class="flex items-center space-x-3">
                <i class="fa-solid fa-building-columns text-sm <?php echo in_array($currentPage, ['business-tax.php', 'business.php']) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
                <span class="sidebar-text truncate">Business Tax & Permits</span>
              </div>
              <div class="dropdown-right">
                <i id="businessTaxChevron" class="fa-solid fa-chevron-down text-[10px] opacity-60 dropdown-chevron transition-transform duration-200 <?php echo in_array($currentPage, ['business-tax.php', 'business.php']) ? 'rotate-180' : ''; ?>"></i>
              </div>
            </button>
            <div id="businessTaxDropdown" class="<?php echo in_array($currentPage, ['business-tax.php', 'business.php']) ? '' : 'hidden'; ?> pl-8 pr-2 space-y-0.5 font-medium sidebar-text">
              <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/business-tax.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'business-tax.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-calculator text-[10px] <?php echo $currentPage == 'business-tax.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Tax Assessments</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/business.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'business.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-briefcase text-[10px] <?php echo $currentPage == 'business.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Business Applications</span></a>
            </div>
          </div>

          <!-- Market Stall Management -->
          <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/market-stall.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo $currentPage == 'market-stall.php' ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
            <i class="fa-solid fa-store text-sm <?php echo $currentPage == 'market-stall.php' ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
            <span class="sidebar-text truncate">Market Stall Leasing</span>
          </a>

          <!-- Online Payments -->
          <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/online-payments.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo in_array($currentPage, ['online-payments.php', 'online-payment.php']) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
            <i class="fa-solid fa-credit-card text-sm <?php echo in_array($currentPage, ['online-payments.php', 'online-payment.php']) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
            <span class="sidebar-text truncate">Online Payment Gateway</span>
          </a>

          <!-- Treasury Reports -->
          <a href="<?php echo $basePath ?? '../'; ?>pages/treasury/reports.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo $currentPage == 'reports.php' ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
            <i class="fa-solid fa-chart-pie text-sm <?php echo $currentPage == 'reports.php' ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
            <span class="sidebar-text truncate">Financial Reports</span>
          </a>

          <!-- BOTTOM SECTION: MAIN CONTROL (ADMINISTRATION & GOVERNANCE) -->
          <?php
          $canAccessUserMgmt    = $isSuperAdmin || $hasResourceAccess(['user directory', 'user account', 'users account', 'account status', 'user', 'account', 'employee']);
          $canAccessRoleMgmt    = $isSuperAdmin || $hasResourceAccess(['role', 'permission', 'module', 'resource', 'access control']);
          $canAccessDeptMgmt    = $isSuperAdmin || $hasResourceAccess(['department', 'dept', 'organization']);
          $canAccessCitizenMgmt = $isSuperAdmin || $hasResourceAccess(['citizen', 'citizen directory', 'citizen account', 'resident']);
          $canAccessAudit       = $isSuperAdmin || $hasResourceAccess(['audit', 'audit log', 'user activity', 'login history', 'data change']);

          if ($canAccessUserMgmt || $canAccessRoleMgmt || $canAccessDeptMgmt || $canAccessCitizenMgmt || $canAccessAudit):
          ?>
          <span class="sidebar-text text-[9px] font-bold tracking-widest text-slate-400 uppercase block px-3 mt-6 mb-2">Main Control</span>
          <?php endif; ?>

          <!-- User Management Dropdown -->
          <?php if ($canAccessUserMgmt): ?>
          <div class="space-y-1">
            <button onclick="toggleDropdown('userDropdown', 'userChevron')" class="dropdown-btn w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo in_array($currentPage, $usermanagementPages) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
              <div class="flex items-center space-x-3">
                <i class="fa-solid fa-users-gear text-sm <?php echo in_array($currentPage, $usermanagementPages) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
                <span class="sidebar-text truncate">User Management</span>
              </div>
              <div class="dropdown-right">
                <i id="userChevron" class="fa-solid fa-chevron-down text-[10px] opacity-60 dropdown-chevron transition-transform duration-200 <?php echo in_array($currentPage, $usermanagementPages) ? 'rotate-180' : ''; ?>"></i>
              </div>
            </button>
            <div id="userDropdown" class="<?php echo in_array($currentPage, $usermanagementPages) ? '' : 'hidden'; ?> pl-8 pr-2 space-y-0.5 font-medium sidebar-text">
              <a href="<?php echo $basePath ?? '../'; ?>pages/usermanagement/user-directory.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'user-directory.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-user-pen text-[10px] <?php echo $currentPage == 'user-directory.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>User Directory</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/usermanagement/create-account.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'create-account.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-user-plus text-[10px] <?php echo $currentPage == 'create-account.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Create Staff Accounts</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/usermanagement/account-status.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'account-status.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-user-check text-[10px] <?php echo $currentPage == 'account-status.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Activate/Deactivate</span></a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Role & Permission Dropdown -->
          <?php if ($canAccessRoleMgmt): ?>
          <div class="space-y-1">
            <button onclick="toggleDropdown('roleDropdown', 'roleChevron')" class="dropdown-btn w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo in_array($currentPage, $rolesmanagementPages) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
              <div class="flex items-center space-x-3">
                <i class="fa-solid fa-shield-halved text-sm <?php echo in_array($currentPage, $rolesmanagementPages) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
                <span class="sidebar-text truncate">Role & Permission</span>
              </div>
              <div class="dropdown-right">
                <i id="roleChevron" class="fa-solid fa-chevron-down text-[10px] opacity-60 dropdown-chevron transition-transform duration-200 <?php echo in_array($currentPage, $rolesmanagementPages) ? 'rotate-180' : ''; ?>"></i>
              </div>
            </button>
            <div id="roleDropdown" class="<?php echo in_array($currentPage, $rolesmanagementPages) ? '' : 'hidden'; ?> pl-8 pr-2 space-y-0.5 font-medium sidebar-text">
              <a href="<?php echo $basePath ?? '../'; ?>pages/rolespermission/roles-management.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'roles-management.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-user-shield text-[10px] <?php echo $currentPage == 'roles-management.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Role Management</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/rolespermission/permissions.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'permissions.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-key text-[10px] <?php echo $currentPage == 'permissions.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Permissions Matrix</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/rolespermission/module-management.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'module-management.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-cubes text-[10px] <?php echo $currentPage == 'module-management.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Module Registry</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/rolespermission/resource-management.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo in_array($currentPage, ['resource-management.php', 'resourcemanagement.php']) ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-folder-tree text-[10px] <?php echo in_array($currentPage, ['resource-management.php', 'resourcemanagement.php']) ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Resource Control</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/rolespermission/access-control.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'access-control.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-lock text-[10px] <?php echo $currentPage == 'access-control.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Access Control</span></a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Department Management -->
          <?php if ($canAccessDeptMgmt): ?>
          <a href="<?php echo $basePath ?? '../'; ?>pages/departments.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo $currentPage == 'departments.php' ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
            <i class="fa-solid fa-sitemap text-sm <?php echo $currentPage == 'departments.php' ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
            <span class="sidebar-text truncate">Department Management</span>
          </a>
          <?php endif; ?>

          <!-- Citizen Management Dropdown -->
          <?php if ($canAccessCitizenMgmt): ?>
          <div class="space-y-1">
            <button onclick="toggleDropdown('citizenDropdown', 'citizenChevron')" class="dropdown-btn w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo in_array($currentPage, $citizenPages) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
              <div class="flex items-center space-x-3">
                <i class="fa-solid fa-address-book text-sm <?php echo in_array($currentPage, $citizenPages) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
                <span class="sidebar-text truncate">Citizen Management</span>
              </div>
              <div class="dropdown-right">
                <i id="citizenChevron" class="fa-solid fa-chevron-down text-[10px] opacity-60 dropdown-chevron transition-transform duration-200 <?php echo in_array($currentPage, $citizenPages) ? 'rotate-180' : ''; ?>"></i>
              </div>
            </button>
            <div id="citizenDropdown" class="<?php echo in_array($currentPage, $citizenPages) ? '' : 'hidden'; ?> pl-8 pr-2 space-y-0.5 font-medium sidebar-text">
              <a href="<?php echo $basePath ?? '../'; ?>pages/citizen/citizen-directory.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'citizen-directory.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-users text-[10px] <?php echo $currentPage == 'citizen-directory.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Citizen Directory</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/citizen/citizen-account.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'citizen-account.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-id-card text-[10px] <?php echo $currentPage == 'citizen-account.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Citizen Accounts</span></a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Audit Logs Dropdown -->
          <?php if ($canAccessAudit): ?>
          <div class="space-y-1">
            <button onclick="toggleDropdown('auditDropdown', 'auditChevron')" class="dropdown-btn w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs tracking-wide transition group cursor-pointer <?php echo in_array($currentPage, $auditPages) ? 'bg-white text-brand-dark border border-brand-border font-bold shadow-xs' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-brand-dark border border-transparent font-semibold'; ?>">
              <div class="flex items-center space-x-3">
                <i class="fa-solid fa-clock-rotate-left text-sm <?php echo in_array($currentPage, $auditPages) ? 'text-brand-medium' : 'text-slate-400'; ?> group-hover:text-brand-medium transition"></i>
                <span class="sidebar-text truncate">Audit Logs System</span>
              </div>
              <div class="dropdown-right">
                <i id="auditChevron" class="fa-solid fa-chevron-down text-[10px] opacity-60 dropdown-chevron transition-transform duration-200 <?php echo in_array($currentPage, $auditPages) ? 'rotate-180' : ''; ?>"></i>
              </div>
            </button>
            <div id="auditDropdown" class="<?php echo in_array($currentPage, $auditPages) ? '' : 'hidden'; ?> pl-8 pr-2 space-y-0.5 font-medium sidebar-text">
              <a href="<?php echo $basePath ?? '../'; ?>pages/audit/user-activities.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'user-activities.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-chart-line text-[10px] <?php echo $currentPage == 'user-activities.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>User Activities</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/audit/login-history.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'login-history.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-history text-[10px] <?php echo $currentPage == 'login-history.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Login History</span></a>
              <a href="<?php echo $basePath ?? '../'; ?>pages/audit/data-changes.php" class="flex items-center space-x-2 px-3 py-2 text-[11px] rounded-md transition <?php echo $currentPage == 'data-changes.php' ? 'text-brand-medium font-black bg-white border border-brand-border/40 shadow-xs' : 'text-slate-500 hover:text-brand-dark'; ?>"><i class="fa-solid fa-pen-to-square text-[10px] <?php echo $currentPage == 'data-changes.php' ? 'text-brand-medium' : 'opacity-50'; ?>"></i> <span>Data Changes</span></a>
            </div>
          </div>
          <?php endif; ?>

        </nav>
        
        <div class="p-4 border-t shrink-0 sidebar-footer">
          <a href="#" onclick="openLogoutModal(event)" class="sidebar-logout-btn flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs font-bold tracking-wide transition group cursor-pointer">
            <i class="fa-solid fa-arrow-right-from-bracket text-sm"></i>
            <span class="sidebar-text truncate">Logout</span>
          </a>
        </div>
      </div>
    </aside>