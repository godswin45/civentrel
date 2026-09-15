// CALENDAR/CLOCK FUNCTION
    function updateClock() {
      const now = new Date();
      const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
      document.getElementById('headerClock').innerText = now.toLocaleDateString('en-US', options);
    }
    setInterval(updateClock, 1000);
    updateClock();

    // DROPDOWN
    function toggleDropdown(id, chevronId) {
      if (typeof isCollapsed !== 'undefined' && isCollapsed) return; 

      const dropdown = document.getElementById(id);
      const chevron = document.getElementById(chevronId);
      if (!dropdown) return;
      
      const dropdowns = ['userDropdown', 'roleDropdown', 'deptDropdown', 'citizenDropdown', 'scholarshipDropdown', 'auditDropdown'];
      const chevrons = ['userChevron', 'roleChevron', 'deptChevron', 'citizenChevron', 'scholarshipChevron', 'auditChevron'];
      
      dropdowns.forEach((d, i) => {
        if (d !== id) {
          const otherEl = document.getElementById(d);
          if (otherEl) otherEl.classList.add('hidden');
          const otherChevron = document.getElementById(chevrons[i]);
          if (otherChevron) otherChevron.classList.remove('rotate-180');
        }
      });

      if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        if (chevron) chevron.classList.add('rotate-180');
      } else {
        dropdown.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-180');
      }
    }

    // SIDEBAR RESPONSIVE
  let isCollapsed = false;

function toggleSidebar() {

    const sidebar = document.getElementById('sidebar');
    const arrow = document.getElementById('toggleArrow');

    const sideLabels = document.querySelectorAll('.sidebar-text');
    const dropdownButtons = document.querySelectorAll('.dropdown-btn');
    const dropdownRights = document.querySelectorAll('.dropdown-right');

    const dropdowns = [
        'userDropdown',
        'roleDropdown',
        'deptDropdown',
        'citizenDropdown',
        'auditDropdown'
    ];

    isCollapsed = !isCollapsed;

    if (isCollapsed) {

        // CLOSE
        dropdowns.forEach(id => {
            const menu = document.getElementById(id);

            if (menu) {
                menu.classList.add('hidden');
            }
        });

        // COLLAPSE
        sidebar.classList.remove('w-72');
        sidebar.classList.add('w-20');

        arrow.className = "fa-solid fa-chevron-right text-xs";

        // HIDE
        sideLabels.forEach(label => {
            label.classList.add('hidden');
        });

        // HIDE DROPDOWN
        dropdownRights.forEach(right => {
            right.classList.add('hidden');
        });

        // CENTER ICON
        dropdownButtons.forEach(btn => {
            btn.classList.remove('justify-between');
            btn.classList.add('justify-center');
        });

    } else {

        // EXPAND
        sidebar.classList.remove('w-20');
        sidebar.classList.add('w-72');

        arrow.className = "fa-solid fa-chevron-left text-xs";

        // SHOW TEXT
        sideLabels.forEach(label => {
            label.classList.remove('hidden');
        });

        // SHOW DROP DOWN BUTTON
        dropdownRights.forEach(right => {
            right.classList.remove('hidden');
        });

        // RESTORE
        dropdownButtons.forEach(btn => {
            btn.classList.remove('justify-center');
            btn.classList.add('justify-between');
        });

        // RESET
        document.querySelectorAll('.dropdown-chevron').forEach(chv => {
            chv.classList.remove('rotate-180');
        });

    }
}

// FORMAT NAME FIELDS (Letters only, Title Case)
document.addEventListener('DOMContentLoaded', () => {
    const nameInputs = document.querySelectorAll('input[name="payer_name"], input[name="owner_name"], input[name="stall_holder"], input[name="first_name"], input[name="last_name"]');
    
    nameInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let val = this.value;
            // 1. Remove anything that isn't a letter or a space
            val = val.replace(/[^a-zA-Z\s]/g, '');
            
            // 2. Auto-capitalize the first letter of every word
            val = val.replace(/\b[a-zA-Z]/g, function(char) {
                return char.toUpperCase();
            });
            
            this.value = val;
        });
    });

    // FORMAT PAYEE FIELD (Allows numbers, Title Case)
    const payeeInputs = document.querySelectorAll('input[name="payee"]');
    payeeInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let val = this.value;
            val = val.replace(/\b[a-zA-Z]/g, function(char) {
                return char.toUpperCase();
            });
            this.value = val;
        });
    });

    // FORMAT ALPHANUMERIC FIELDS (Allows numbers, Title Case)
    const budgetInputs = document.querySelectorAll('input[name="department_name"], input[name="project_title"], input[name="business_name"], input[name="barangay"], input[name="line_of_business"], input[name="stall_number"]');
    budgetInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let val = this.value;
            val = val.replace(/\b[a-zA-Z]/g, function(char) {
                return char.toUpperCase();
            });
            this.value = val;
        });
    });
});