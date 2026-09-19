// ACCOUNT STATUS UI

// RENDER TABLE
function renderTable(usersList = accountUsers) {
  const tbody = document.getElementById('statusTableBody');
  if (!tbody) return;
  tbody.innerHTML = '';
  
  if (usersList.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-semibold">
          <i class="fa-solid fa-user-slash text-3xl mb-3 block opacity-60"></i>
          No profiles matched your filter criteria.
        </td>
      </tr>
    `;
    const pagText = document.getElementById('statusPaginationText');
    if (pagText) pagText.innerText = "Showing 0 to 0 of 0 profiles";
    return;
  }

  usersList.forEach(user => {
    let statusStyles = '';
    let dotPulse = '';
    
    if (user.status === 'Active') {
      statusStyles = 'bg-emerald-50 text-emerald-700 border-emerald-150';
      dotPulse = '<span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>';
    } else if (user.status === 'Deactivated' || user.status === 'Inactive') {
      statusStyles = 'bg-slate-50 text-slate-600 border-slate-200';
      dotPulse = '<span class="h-1.5 w-1.5 rounded-full bg-slate-400 shrink-0"></span>';
    } else if (user.status === 'Locked') {
      statusStyles = 'bg-rose-50 text-rose-700 border-rose-150';
      dotPulse = '<i class="fa-solid fa-lock text-[9px] shrink-0 text-rose-500"></i>';
    } else if (user.status === 'Archived') {
      statusStyles = 'bg-amber-50 text-amber-700 border-amber-150';
      dotPulse = '<i class="fa-solid fa-box-archive text-[9px] shrink-0 text-amber-500"></i>';
    }

    let attemptsText = `${user.failedAttempts}/5`;
    if (user.failedAttempts >= 5 || user.status === 'Locked') {
      attemptsText = '<span class="text-rose-600 font-bold flex items-center gap-1"><i class="fa-solid fa-lock text-[9px]"></i> Locked</span>';
    }

    // Status switch details
    const isChecked = user.status === 'Active' ? 'checked' : '';
    const isToggleDisabled = (user.status === 'Locked' || user.status === 'Archived') ? 'disabled opacity-50 cursor-not-allowed' : '';

    const lockBtnIcon = user.status === 'Locked' ? 'fa-lock text-rose-600 hover:bg-rose-50 border-rose-100' : 'fa-lock-open text-slate-400 hover:text-slate-700 hover:bg-slate-50 border-transparent';
    const lockBtnTitle = user.status === 'Locked' ? 'Unlock Account' : 'Suspend Account (Lock)';

    const archiveBtnIcon = user.status === 'Archived' ? 'fa-arrow-rotate-left text-amber-600 hover:bg-amber-50 hover:border-amber-150' : 'fa-box-archive text-slate-400 hover:text-rose-600 hover:bg-rose-50 border-transparent';
    const archiveBtnTitle = user.status === 'Archived' ? 'Restore Profile' : 'Archive Profile';

    const colors = ['bg-blue-500', 'bg-indigo-500', 'bg-purple-500', 'bg-rose-500', 'bg-emerald-500', 'bg-slate-500', 'bg-amber-600'];
    let colorIndex = (user.name.charCodeAt(0) + user.name.charCodeAt(user.name.length - 1)) % colors.length;
    let avatarBg = colors[colorIndex];

    let avatarHTML = `
      <div class="h-9 w-9 rounded-xl ${avatarBg} text-white font-extrabold flex items-center justify-center text-xs shadow-xs border border-white/20 shrink-0">
        ${user.initials}
      </div>
    `;
    if (user.profile_picture && user.profile_picture !== 'default-avatar.png') {
      let pic = user.profile_picture;
      if (!pic.startsWith('http') && !pic.startsWith('data:')) {
        const bPath = window.civentralBasePath || '../../';
        pic = bPath + pic.replace(/^\/+/, '');
      }
      avatarHTML = `<img src="${pic}" alt="${user.name}" class="h-9 w-9 rounded-xl object-cover border border-slate-200 shrink-0">`;
    }

    const row = `
      <tr class="hover:bg-slate-50/50 transition">
        <!-- User Details -->
        <td class="px-6 py-3.5 flex items-center space-x-3">
          ${avatarHTML}
          <div class="flex flex-col">
            <span class="font-extrabold text-slate-800 tracking-tight leading-snug">${user.name}</span>
            <span class="text-[10px] text-slate-400 font-mono font-medium">${user.id} &bull; ${user.position}</span>
          </div>
        </td>

        <!-- Current Status Badge -->
        <td class="px-6 py-3.5">
          <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full border ${statusStyles} inline-flex items-center gap-1.5">
            ${dotPulse}
            <span>${(user.status === 'Deactivated' || user.status === 'Inactive') ? 'Inactive' : user.status}</span>
          </span>
        </td>

        <!-- Security Health Indicators -->
        <td class="px-6 py-3.5 space-y-1">
          <div class="flex items-center gap-1.5 text-slate-650">
            <span class="text-[9px] font-bold text-slate-400 uppercase">Last Login:</span>
            <span class="font-semibold text-[11px]">${user.lastLogin}</span>
          </div>
          <div class="flex items-center gap-1.5 text-slate-650">
            <span class="text-[9px] font-bold text-slate-400 uppercase">Failed attempts:</span>
            <span class="font-semibold text-[11px]">${attemptsText}</span>
          </div>
        </td>

        <!-- Action Controls -->
        <td class="px-6 py-3.5 text-right whitespace-nowrap">
          <div class="inline-flex items-center space-x-3">
            
            <!-- iOS-style Status Toggle Switch -->
            <label class="relative inline-flex items-center ${isToggleDisabled ? 'cursor-not-allowed' : 'cursor-pointer'} select-none" title="Activate/Deactivate toggle">
              <input type="checkbox" ${isChecked} ${isToggleDisabled} onchange="if(typeof handleStatusToggle === 'function') handleStatusToggle('${user.id}', this)" class="sr-only peer">
              <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-brand-medium/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-emerald-500"></div>
            </label>

            <!-- Lock/Unlock toggle button -->
            <button onclick="if(typeof triggerLockToggle === 'function') triggerLockToggle('${user.id}')" class="border p-1.5 rounded-lg transition cursor-pointer flex items-center justify-center ${lockBtnIcon}" title="${lockBtnTitle}">
              <i class="fa-solid ${user.status === 'Locked' ? 'fa-lock' : 'fa-lock-open'} text-xs"></i>
            </button>

            <!-- Archive/Restore toggle button -->
            <button onclick="if(typeof triggerArchiveToggle === 'function') triggerArchiveToggle('${user.id}')" class="border p-1.5 rounded-lg transition cursor-pointer flex items-center justify-center ${archiveBtnIcon}" title="${archiveBtnTitle}">
              <i class="fa-solid ${user.status === 'Archived' ? 'fa-arrow-rotate-left' : 'fa-box-archive'} text-xs"></i>
            </button>

          </div>
        </td>
      </tr>
    `;
    tbody.innerHTML += row;
  });

  const pagText = document.getElementById('statusPaginationText');
  if (pagText) pagText.innerText = `Showing 1 to ${usersList.length} of ${usersList.length} profiles`;
}

// UPDATE FILTER COUNTS
function updateCounts() {
  const all = accountUsers.length;
  const active = accountUsers.filter(u => u.status === 'Active').length;
  const deactivated = accountUsers.filter(u => u.status === 'Deactivated' || u.status === 'Inactive').length;
  const locked = accountUsers.filter(u => u.status === 'Locked').length;
  const archived = accountUsers.filter(u => u.status === 'Archived').length;

  const countAll = document.getElementById('tabCountAll');
  const countActive = document.getElementById('tabCountActive');
  const countDeactivated = document.getElementById('tabCountDeactivated');
  const countLocked = document.getElementById('tabCountLocked');
  const countArchived = document.getElementById('tabCountArchived');

  if (countAll) countAll.innerText = all;
  if (countActive) countActive.innerText = active;
  if (countDeactivated) countDeactivated.innerText = deactivated;
  if (countLocked) countLocked.innerText = locked;
  if (countArchived) countArchived.innerText = archived;
}

// EXPORT TO CSV
function exportStatusList() {
  const searchInput = document.getElementById('statusSearchInput');
  const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
  const activeTab = window.activeStatusTab;

  let csvContent = "data:text/csv;charset=utf-8,";
  csvContent += "Employee ID,Full Name,Email Address,Operational Status,Last Authentication,Failed Counters\r\n";

  const filtered = accountUsers.filter(user => {
    const matchesSearch = user.name.toLowerCase().includes(query) ||
                          user.email.toLowerCase().includes(query) ||
                          user.id.toLowerCase().includes(query);
    let matchesTab = true;
    if (activeTab !== 'all') {
      if (activeTab === 'Deactivated') {
        matchesTab = user.status === 'Deactivated' || user.status === 'Inactive';
      } else {
        matchesTab = user.status === activeTab;
      }
    }
    return matchesSearch && matchesTab;
  });

  filtered.forEach(user => {
    const row = `"${user.id}","${user.name}","${user.email}","${user.status}","${user.lastLogin}","${user.failedAttempts}"`;
    csvContent += row + "\r\n";
  });

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `LGU_Account_Status_Roster_${new Date().toISOString().split('T')[0]}.csv`);
  document.body.appendChild(link);
  
  link.click();
  document.body.removeChild(link);

  if (typeof showToast === 'function') showToast(`Successfully compiled and exported ${filtered.length} credentials records.`);
}
