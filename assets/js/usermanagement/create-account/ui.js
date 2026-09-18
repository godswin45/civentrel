// RENDER SKELETON OPTIONS WHILE LOADING
function renderSkeletonOptions() {
  const deptSelect = document.getElementById('department');
  const roleSelect = document.getElementById('role');
  if (deptSelect) {
    deptSelect.innerHTML = '<option value="" disabled selected class="animate-pulse">Loading departments...</option>';
  }
  if (roleSelect) {
    roleSelect.innerHTML = '<option value="" disabled selected class="animate-pulse">Loading roles...</option>';
  }
}

function applyUserScopeRules() {
  const deptSelect = document.getElementById('department');
  const roleAlertBox = document.getElementById('roleAlertBox');
  if (!currentUserScope) return;

  const isSuperAdmin = !!currentUserScope.is_superadmin;
  const userDeptId = currentUserScope.department_id;
  const userDeptName = currentUserScope.department_name || 'your department';

  if (!isSuperAdmin && userDeptId && deptSelect) {
    deptSelect.value = userDeptId;
    deptSelect.disabled = true;
    deptSelect.classList.add('bg-slate-100', 'cursor-not-allowed', 'text-slate-500');

    if (roleAlertBox) {
      roleAlertBox.innerHTML = `
        <i class="fa-solid fa-lock text-amber-500 text-base mt-0.5"></i>
        <div class="space-y-1 text-xs">
          <p class="font-bold text-amber-900">Department Scope Clearance Notice</p>
          <p class="leading-relaxed text-amber-700">As a Department Administrator for <strong>${userDeptName}</strong>, your account creation scope is locked to your department. Available access roles are restricted to staff and officer roles created for your department (Administrator role creation is restricted).</p>
        </div>
      `;
      roleAlertBox.className = "bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-4 flex items-start gap-3 shadow-xs transition duration-300";
    }

    if (typeof fetchRolesForDepartment === 'function') {
      fetchRolesForDepartment(userDeptId);
    }
  } else if (isSuperAdmin && deptSelect) {
    deptSelect.disabled = false;
    deptSelect.onchange = function() {
      const selectedDeptId = this.value;
      if (typeof fetchRolesForDepartment === 'function') {
        fetchRolesForDepartment(selectedDeptId);
      }
    };
  }
}

// POPULATE ROLES SELECT
function populateRoles() {
  const roleSelect = document.getElementById('role');
  if (!roleSelect) return;
  const currentVal = roleSelect.value;
  roleSelect.innerHTML = '<option value="">Choose system role...</option>';

  const isSuperAdmin = currentUserScope ? (!!currentUserScope.is_superadmin || !!currentUserScope.is_global_access) : false;
  const userRoleName = currentUserScope ? (currentUserScope.role_name || '').toLowerCase() : '';

  systemRoles.forEach(r => {
    const roleNameLower = (r.role_name || '').toLowerCase();
    const rolePrefixUpper = (r.role_prefix || '').toUpperCase();

    // If not superadmin, exclude superadmin, global access, and any admin/administrator roles
    if (!isSuperAdmin) {
      const isSuper = r.is_superadmin == 1 || r.is_superadmin === true || r.is_global_access == 1;
      const isSuperPrefix = ['SA', 'SADM'].includes(rolePrefixUpper);
      const isAdminRole = ['ADM', 'DADM', 'ADMIN'].includes(rolePrefixUpper) || 
                          roleNameLower.includes('admin') || 
                          roleNameLower.includes('administrator') || 
                          roleNameLower.includes('superadmin') ||
                          (userRoleName && roleNameLower === userRoleName);

      if (isSuper || isSuperPrefix || isAdminRole) {
        return; // Do not show administrator roles to Department Admin
      }
    }

    const opt = document.createElement('option');
    opt.value = r.role_id;
    opt.dataset.prefix = r.role_prefix || 'STF';
    opt.dataset.name = r.role_name;
    opt.textContent = `${r.role_name} (${r.role_prefix})`;
    roleSelect.appendChild(opt);
  });

  // ── Client-side fallback ──────────────────────────────────────────
  // If after all filtering the dropdown is still empty, inject hardcoded
  // Treasury staff roles so the form is always usable.
  if (roleSelect.options.length <= 1) {
    const localFallback = [
      { id: 'TRMG-L', name: 'Treasury Manager',   prefix: 'TRMG' },
      { id: 'TRES-L', name: 'Treasury Officer',   prefix: 'TRES' },
      { id: 'CASH-L', name: 'Cashier',            prefix: 'CASH' },
      { id: 'TSTA-L', name: 'Treasury Staff',     prefix: 'TSTA' },
      { id: 'RCOL-L', name: 'Revenue Collector',  prefix: 'RCOL' },
      { id: 'BDGT-L', name: 'Budget Officer',     prefix: 'BDGT' },
    ];
    localFallback.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.id;
      opt.dataset.prefix = r.prefix;
      opt.dataset.name = r.name;
      opt.textContent = `${r.name} (${r.prefix})`;
      roleSelect.appendChild(opt);
    });
  }

  if (currentVal && Array.from(roleSelect.options).some(o => o.value === currentVal)) {
    roleSelect.value = currentVal;
  }
}


// POPULATE DEPARTMENTS SELECT
function populateDepartments() {
  const deptSelect = document.getElementById('department');
  if (!deptSelect) return;

  const isSuperAdmin = currentUserScope ? !!currentUserScope.is_superadmin : false;
  const userDeptId = currentUserScope ? currentUserScope.department_id : null;

  deptSelect.innerHTML = '';

  if (isSuperAdmin || !userDeptId) {
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = 'Choose department...';
    deptSelect.appendChild(defaultOpt);
  }

  systemDepartments.forEach(d => {
    if (!isSuperAdmin && userDeptId && String(d.department_id) !== String(userDeptId)) {
      return;
    }

    const opt = document.createElement('option');
    opt.value = d.department_id;
    opt.textContent = d.department_name;
    if (userDeptId && String(d.department_id) === String(userDeptId)) {
      opt.selected = true;
    }
    deptSelect.appendChild(opt);
  });

  if (userDeptId) {
    deptSelect.value = userDeptId;
  }
}

// AUTO-GENERATE EMPLOYEE ID BASED ON ROLE PREFIX, YEAR & SEQUENCE (e.g. TRMG-2026-002)
async function autoGenerateEmpId() {
  const roleSelect = document.getElementById('role');
  const empIdInput = document.getElementById('empId');
  const roleId = roleSelect ? roleSelect.value : '';

  if (!roleId) {
    if (typeof showToast === 'function') showToast('Please select a System Access Role first.', true);
    return;
  }

  // If it's a local fallback ID (e.g. 'TRMG-L'), generate entirely client-side
  if (isNaN(parseInt(roleId))) {
    const roleOpt = roleSelect ? roleSelect.options[roleSelect.selectedIndex] : null;
    const prefix = roleOpt ? (roleOpt.dataset.prefix || 'EMP') : 'EMP';
    const year = new Date().getFullYear();
    const seq = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
    const empId = `${prefix}-${year}-${seq}`;
    if (empIdInput) empIdInput.value = empId;
    if (typeof showToast === 'function') showToast(`Auto-generated Employee ID: ${empId}`);
    return;
  }

  // Numeric ID: call the API
  try {
    const response = await fetch(`../../api/employee/users.php?action=generate_emp_id&role_id=${roleId}`);
    const data = await response.json();

    if (data.status === 'success' && data.employee_id) {
      if (empIdInput) empIdInput.value = data.employee_id;
      if (typeof showToast === 'function') showToast(`Auto-generated Employee ID: ${data.employee_id}`);
    } else {
      if (typeof showToast === 'function') showToast('Failed to generate Employee ID.', true);
    }
  } catch (err) {
    console.error('Error generating Employee ID:', err);
    if (typeof showToast === 'function') showToast('Network error calculating next Employee ID.', true);
  }
}

