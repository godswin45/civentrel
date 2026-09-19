// ACCOUNT STATUS DATA API

var accountUsers = [];
window.activeStatusTab = 'all';
window.pendingActionUser = null;

// FETCH ALL USERS FROM Database API
async function fetchAccountStatusUsers() {
  const tbody = document.getElementById('statusTableBody');
  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-semibold">
          <i class="fa-solid fa-spinner fa-spin text-2xl mb-3 block text-brand-dark"></i>
          Loading user security profiles from database...
        </td>
      </tr>
    `;
  }

  try {
    const response = await fetch('../../api/employee/users.php');
    const result = await response.json();

    if (result.status === 'success' && Array.isArray(result.data)) {
      accountUsers = result.data.map(u => {
        const firstName = u.first_name || '';
        const lastName = u.last_name || '';
        const name = `${firstName} ${lastName}`.trim() || u.email || 'System User';
        const initials = ((firstName[0] || '') + (lastName[0] || '')).toUpperCase() || 'SU';
        
        const dept = u.positions?.departments?.department_name || 'General Services';
        const position = u.positions?.position_name || 'Staff Member';
        const role = u.roles?.role_name || 'Staff';
        
        let lastLogin = 'Never';
        if (Array.isArray(u.login_history) && u.login_history.length > 0) {
          const lastEntry = u.login_history[u.login_history.length - 1];
          lastLogin = lastEntry.login_time || 'Never';
        } else if (u.created_at) {
          lastLogin = u.created_at.replace('T', ' ').substring(0, 16);
        }

        // Map status
        let userStatus = u.status || 'Active';
        if (userStatus === 'Inactive') userStatus = 'Deactivated';

        return {
          db_id: u.user_id,
          id: u.employee_id || `EMP-${u.user_id}`,
          name: name,
          email: u.email,
          dept: dept,
          position: position,
          role: role,
          status: userStatus,
          profile_picture: u.profile_picture || null,
          initials: initials,
          lastLogin: lastLogin,
          failedAttempts: parseInt(u.failed_attempts || 0)
        };
      });

      if (typeof updateCounts === 'function') updateCounts();
      if (typeof filterAndSearch === 'function') filterAndSearch();
    } else {
      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="px-6 py-12 text-center text-rose-500 font-semibold">
              <i class="fa-solid fa-triangle-exclamation text-3xl mb-3 block"></i>
              Failed to load user accounts from database.
            </td>
          </tr>
        `;
      }
    }
  } catch (error) {
    console.error('Error fetching users:', error);
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" class="px-6 py-12 text-center text-rose-500 font-semibold">
            <i class="fa-solid fa-wifi text-3xl mb-3 block"></i>
            Connection error fetching user status records.
          </td>
        </tr>
      `;
    }
  }
}

// UPDATE USER STATUS VIA API
async function updateUserStatusAPI(userId, newStatus, failedAttempts = null) {
  const user = accountUsers.find(u => u.id === userId || u.db_id === userId);
  if (!user) return false;

  const payload = {
    user_id: user.db_id,
    status: newStatus
  };
  if (failedAttempts !== null) {
    payload.failed_attempts = failedAttempts;
  }

  try {
    const response = await fetch('../../api/employee/users.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const res = await response.json();
    if (res.status === 'success') {
      user.status = newStatus;
      if (failedAttempts !== null) user.failedAttempts = failedAttempts;
      if (typeof updateCounts === 'function') updateCounts();
      if (typeof filterAndSearch === 'function') filterAndSearch();
      return true;
    } else {
      if (typeof showToast === 'function') showToast(res.message || 'Error updating account status.', true);
      return false;
    }
  } catch (err) {
    console.error('API Error:', err);
    if (typeof showToast === 'function') showToast('Network error updating account status.', true);
    return false;
  }
}
