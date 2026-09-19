// TOGGLE DEACTIVATE
async function handleStatusToggle(userId, toggleInput) {
  const user = accountUsers.find(u => u.id === userId);
  if (!user) return;

  if (toggleInput.checked) {
    if (typeof updateUserStatusAPI === 'function') {
        const ok = await updateUserStatusAPI(userId, 'Active', 0);
        if (ok) {
        if (typeof showToast === 'function') showToast(`Operational access re-established for ${user.name}`);
        } else {
        toggleInput.checked = false;
        }
    }
  } else {
    toggleInput.checked = true;
    window.pendingActionUser = userId;
    const msg = document.getElementById('deactivateMessage');
    if (msg) msg.innerText = `Are you sure you want to deactivate ${user.name}'s account? The user will immediately be barred from active session ports.`;
    if (typeof openModal === 'function') openModal('deactivateModal');
  }
}

async function executeDeactivate() {
  const user = accountUsers.find(u => u.id === window.pendingActionUser);
  if (user) {
    if (typeof updateUserStatusAPI === 'function') {
        const ok = await updateUserStatusAPI(window.pendingActionUser, 'Deactivated');
        if (ok && typeof showToast === 'function') {
            showToast(`Operational credentials deactivated for ${user.name}`);
        }
    }
  }
  if (typeof closeModal === 'function') closeModal('deactivateModal');
}

// LOCK/UNLOCK MODAL TRIGGERS
function triggerLockToggle(userId) {
  const user = accountUsers.find(u => u.id === userId);
  if (!user) return;

  if (user.status === 'Archived') {
    if (typeof showToast === 'function') showToast(`Cannot lock/unlock archived staff profile. Please restore profile first.`, true);
    return;
  }

  window.pendingActionUser = userId;
  const mTitle = document.getElementById('lockModalTitle');
  const mIcon = document.getElementById('lockModalIcon');
  const mHeader = document.getElementById('lockMessageHeader');
  const mMsg = document.getElementById('lockModalMessage');
  const mConfirm = document.getElementById('lockModalConfirmBtn');
  const iconBox = document.getElementById('lockModalStatusIconBox');

  if (user.status === 'Locked') {
    if (mTitle) mTitle.innerText = "Unlock Account";
    if (mIcon) mIcon.className = "fa-solid fa-lock-open text-brand-medium";
    if (mHeader) mHeader.innerText = `Unlock ${user.name}'s Access?`;
    if (mMsg) mMsg.innerText = "Unlocking this account immediately resets the failed login attempts counter back to 0/5, restoring validation access keycards.";
    if (mConfirm) {
      mConfirm.innerText = "Unlock Access";
      mConfirm.className = "bg-[#0f172a] hover:bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer shadow-xs";
    }
    if (iconBox) {
      iconBox.className = "h-10 w-10 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0";
      iconBox.innerHTML = '<i class="fa-solid fa-shield-halved text-base"></i>';
    }
  } else {
    if (mTitle) mTitle.innerText = "Lock Account Access";
    if (mIcon) mIcon.className = "fa-solid fa-lock text-rose-500";
    if (mHeader) mHeader.innerText = `Lock ${user.name}'s Credentials?`;
    if (mMsg) mMsg.innerText = "Suspending this account places an administrative lock. The user will be prohibited from logging in until manually unlocked.";
    if (mConfirm) {
      mConfirm.innerText = "Lock Account";
      mConfirm.className = "bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer shadow-xs";
    }
    if (iconBox) {
      iconBox.className = "h-10 w-10 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 shrink-0";
      iconBox.innerHTML = '<i class="fa-solid fa-user-lock text-base"></i>';
    }
  }

  if (typeof openModal === 'function') openModal('lockModal');
}

async function executeLockToggle() {
  const user = accountUsers.find(u => u.id === window.pendingActionUser);
  if (user && typeof updateUserStatusAPI === 'function') {
    if (user.status === 'Locked') {
      const ok = await updateUserStatusAPI(window.pendingActionUser, 'Active', 0);
      if (ok && typeof showToast === 'function') showToast(`Credential security locks cleared. Access restored for ${user.name}`);
    } else {
      const ok = await updateUserStatusAPI(window.pendingActionUser, 'Locked', 5);
      if (ok && typeof showToast === 'function') showToast(`Administrative credential suspension lock applied to ${user.name}`);
    }
  }
  if (typeof closeModal === 'function') closeModal('lockModal');
}

// ARCHIVE/RESTORE MODAL TRIGGERS
function triggerArchiveToggle(userId) {
  const user = accountUsers.find(u => u.id === userId);
  if (!user) return;

  window.pendingActionUser = userId;
  const mTitle = document.getElementById('archiveModalTitle');
  const mIcon = document.getElementById('archiveModalIcon');
  const mHeader = document.getElementById('archiveMessageHeader');
  const mMsg = document.getElementById('archiveModalMessage');
  const mConfirm = document.getElementById('archiveModalConfirmBtn');
  const iconBox = document.getElementById('archiveModalIconBox');

  if (user.status === 'Archived') {
    if (mTitle) mTitle.innerText = "Restore Profile";
    if (mIcon) mIcon.className = "fa-solid fa-arrow-rotate-left text-brand-medium";
    if (mHeader) mHeader.innerText = `Restore ${user.name}'s Profile?`;
    if (mMsg) mMsg.innerText = `Are you sure you want to restore this profile from archives? This will return the staff directory record to active status.`;
    if (mConfirm) {
      mConfirm.innerText = "Confirm Restore";
      mConfirm.className = "bg-[#0f172a] hover:bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer shadow-xs";
    }
    if (iconBox) {
      iconBox.className = "h-10 w-10 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shrink-0";
      iconBox.innerHTML = '<i class="fa-solid fa-circle-check text-base"></i>';
    }
  } else {
    if (mTitle) mTitle.innerText = "Archive Staff Profile";
    if (mIcon) mIcon.className = "fa-solid fa-box-archive text-amber-500";
    if (mHeader) mHeader.innerText = `Archive ${user.name}'s Profile?`;
    if (mMsg) mMsg.innerText = `Warning: Archiving soft-deletes this user from main roster indexes. All active terminal sessions terminate instantly. Records remain logged.`;
    if (mConfirm) {
      mConfirm.innerText = "Confirm Archive";
      mConfirm.className = "bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer shadow-xs";
    }
    if (iconBox) {
      iconBox.className = "h-10 w-10 rounded-full bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shrink-0";
      iconBox.innerHTML = '<i class="fa-solid fa-circle-exclamation text-base"></i>';
    }
  }

  if (typeof openModal === 'function') openModal('archiveModal');
}

async function executeArchiveToggle() {
  const user = accountUsers.find(u => u.id === window.pendingActionUser);
  if (user && typeof updateUserStatusAPI === 'function') {
    if (user.status === 'Archived') {
      const ok = await updateUserStatusAPI(window.pendingActionUser, 'Active');
      if (ok && typeof showToast === 'function') showToast(`Staff profile index restored successfully for ${user.name}`);
    } else {
      const ok = await updateUserStatusAPI(window.pendingActionUser, 'Archived');
      if (ok && typeof showToast === 'function') showToast(`Operational staff roster archived for ${user.name}`);
    }
  }
  if (typeof closeModal === 'function') closeModal('archiveModal');
}
