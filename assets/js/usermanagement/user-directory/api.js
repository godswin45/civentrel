// USER DIRECTORY DATA API

var systemUsers = [];
var availableRoles = [];
var availablePositions = [];
var availableDepartments = [];
var currentUserScope = null;

// FETCH ALL USERS, ROLES, POSITIONS & DEPARTMENTS FROM Database API
async function fetchUsersData() {
  try {
    const response = await fetch('../../api/employee/users.php');
    const result = await response.json();

    if (result.status === 'success') {
      systemUsers = result.data || [];
      availableRoles = result.roles || [];
      availablePositions = result.positions || [];
      availableDepartments = result.departments || [];
      currentUserScope = result.current_user || null;

      if (typeof populateFilterOptions === 'function') populateFilterOptions();
      if (typeof populateEditFormOptions === 'function') populateEditFormOptions();
      if (typeof filterAndSearch === 'function') filterAndSearch();
      if (typeof updateMetrics === 'function') updateMetrics();
    } else {
      console.warn('Fetch users notice:', result.message);
      if (typeof showToast === 'function') showToast('Notice loading user records.', true);
    }
  } catch (err) {
    console.error('Error fetching users from Database API:', err);
    if (typeof showToast === 'function') showToast('Network error connecting to Database.', true);
  }
}

// EDIT STAFF API CALL
async function handleEditStaff(e) {
  e.preventDefault();

  const userId = document.getElementById('editEmpIdRef').value;
  if (!userId) return;

  const nameInput = document.getElementById('editName').value.trim();
  const email = document.getElementById('editEmail').value.trim();
  const phone = document.getElementById('editPhone').value.trim();
  const positionId = parseInt(document.getElementById('editPosition').value) || 0;
  const roleId = document.getElementById('editRole').value;
  const status = document.getElementById('editStatus').value;

  const featureCheckboxes = document.querySelectorAll('#editFeaturePermissions input[type="checkbox"]:checked');
  const featurePermissions = Array.from(featureCheckboxes).map(cb => cb.value);

  const user = systemUsers.find(u => u.user_id == userId);
  const isSelf = (
    (window.currentUserId && userId == window.currentUserId) ||
    (user && (
      (window.currentEmployeeId && user.employee_id == window.currentEmployeeId) ||
      (window.currentUserEmail && user.email && user.email.toLowerCase() === window.currentUserEmail.toLowerCase())
    ))
  );

  if (isSelf && ['deactivated', 'inactive', 'locked', 'archived'].includes(status.toLowerCase())) {
    if (typeof showToast === 'function') showToast("Forbidden. You cannot set your own account status to inactive, locked, or archived.", true);
    return;
  }

  const nameParts = nameInput.split(/\s+/);
  const firstName = nameParts[0] || '';
  const lastName = nameParts.length > 1 ? nameParts.slice(1).join(' ') : '';
  const employeeId = user ? user.employee_id : '';

  const payload = {
    user_id: userId,
    employee_id: employeeId,
    first_name: firstName,
    last_name: lastName,
    email: email,
    mobile_number: phone,
    role_id: roleId,
    position_id: positionId,
    status: status,
    feature_permissions: featurePermissions
  };

  try {
    const response = await fetch('../../api/employee/users.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (result.status === 'success') {
      if (typeof showToast === 'function') showToast(`Successfully updated user profile for ${firstName} ${lastName}`);
      if (typeof closeModal === 'function') closeModal('editModal');
      await fetchUsersData();
    } else {
      if (typeof showToast === 'function') showToast(result.message || 'Error updating user profile.', true);
    }
  } catch (err) {
    console.error('Update user error:', err);
    if (typeof showToast === 'function') showToast('Failed to update user profile TO DATABASE.', true);
  }
}
