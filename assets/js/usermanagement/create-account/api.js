// CREATE ACCOUNT API

var systemRoles = [];
var systemDepartments = [];
var currentUserScope = null;

// FETCH ROLES & DEPARTMENTS FROM Database API
async function fetchFormData() {
  try {
    const response = await fetch('../../api/employee/users.php');
    const result = await response.json();

    if (result.status === 'success') {
      systemRoles = result.roles || [];
      systemDepartments = result.departments || [];
      currentUserScope = result.current_user || null;

      if (typeof populateDepartments === 'function') populateDepartments();
      if (typeof populateRoles === 'function') populateRoles();
      if (typeof applyUserScopeRules === 'function') applyUserScopeRules();
    } else {
      console.warn('Fetch form data notice:', result.message);
    }
  } catch (err) {
    console.error('Error fetching form options FROM DATABASE:', err);
    if (typeof showToast === 'function') showToast('Network error loading database options.', true);
  }
}

// FETCH ROLES FOR SELECTED DEPARTMENT
async function fetchRolesForDepartment(deptId) {
  try {
    const url = deptId 
      ? `../../api/employee/users.php?action=get_roles_by_dept&department_id=${deptId}`
      : '../../api/employee/users.php?action=get_roles_by_dept';
    const response = await fetch(url);
    const result = await response.json();

    if (result.status === 'success') {
      systemRoles = result.roles || [];
      if (typeof populateRoles === 'function') populateRoles();
    }
  } catch (err) {
    console.error('Error fetching department roles:', err);
  }
}

// SUBMIT CREATE USER FORM
async function handleCreateUser(e) {
  e.preventDefault();
  
  const submitBtn = document.getElementById('submitBtn');
  const spinner = document.getElementById('spinnerIcon');
  const roleSelect = document.getElementById('role');
  const deptSelect = document.getElementById('department');
  const posInput = document.getElementById('position');
  const empIdInput = document.getElementById('empId');

  const fName = document.getElementById('firstName').value.trim();
  const mName = document.getElementById('middleName').value.trim();
  const lName = document.getElementById('lastName').value.trim();
  const emailVal = document.getElementById('email').value.trim();
  const mobileVal = document.getElementById('mobileNumber') ? document.getElementById('mobileNumber').value.trim() : '';

  // deptId: prefer select value, fall back to currentUserScope (handles disabled selects)
  const deptIdFromSelect = deptSelect ? parseInt(deptSelect.value) : 0;
  const deptIdFromScope  = currentUserScope ? parseInt(currentUserScope.department_id) : 0;
  const deptId = deptIdFromSelect || deptIdFromScope;

  const positionTitle = posInput ? posInput.value.trim() : '';

  // Support both numeric IDs (live server) and string IDs like 'TRMG-L' (local fallback)
  const roleIdRaw = roleSelect ? roleSelect.value : '';
  const roleId = parseInt(roleIdRaw) || roleIdRaw;

  const roleOpt = roleSelect ? roleSelect.options[roleSelect.selectedIndex] : null;
  const roleName = roleOpt ? (roleOpt.dataset.name || 'User') : 'User';
  const rolePrefix = roleOpt ? (roleOpt.dataset.prefix || 'EMP') : 'EMP';

  // Auto-generate Employee ID if it was cleared or never set
  let empIdCode = empIdInput ? empIdInput.value.trim() : '';
  if (!empIdCode && roleIdRaw) {
    const year = new Date().getFullYear();
    const seq  = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
    empIdCode  = `${rolePrefix}-${year}-${seq}`;
    if (empIdInput) empIdInput.value = empIdCode;
  }

  if (!fName || !lName || !emailVal || !mobileVal || !positionTitle || !roleIdRaw) {
    if (typeof showToast === 'function') showToast('Please complete all required fields and generate an Employee ID first.', true);
    return;
  }

  if (submitBtn) submitBtn.disabled = true;
  if (spinner) spinner.classList.remove('hidden');

  const payload = {
    first_name: fName,
    middle_name: mName,
    last_name: lName,
    employee_id: empIdCode,
    email: emailVal,
    mobile_number: mobileVal,
    department_id: deptId,
    position_name: positionTitle,
    role_id: roleId
  };

  try {
    const response = await fetch('../../api/employee/users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (spinner) spinner.classList.add('hidden');
    if (submitBtn) submitBtn.disabled = false;

    if (result.status === 'success') {
      // Populate and trigger Success Modal
      document.getElementById('modalUserName').innerText = result.user_name || `${fName} ${lName}`;
      document.getElementById('modalUserEmail').innerText = result.email || emailVal;
      document.getElementById('modalEmpId').innerText = result.employee_id || empIdCode;
      document.getElementById('modalTempPass').innerText = result.temp_password || '********';
      document.getElementById('modalRole').innerText = roleName;

      if (typeof openSuccessModal === 'function') openSuccessModal();
      if (typeof showToast === 'function') showToast('Account successfully created!');
    } else {
      if (typeof showToast === 'function') showToast(result.message || 'Error creating account.', true);
    }
  } catch (err) {
    console.error('Create user error:', err);
    if (typeof showToast === 'function') showToast('Network error creating user account.', true);
    if (spinner) spinner.classList.add('hidden');
    if (submitBtn) submitBtn.disabled = false;
  }
}
