// User Directory Bootstrap Module

const v = '?v=' + new Date().getTime();
window.loadCiventralScript('assets/js/usermanagement/shared/toast.js' + v);
window.loadCiventralScript('assets/js/usermanagement/user-directory/api.js' + v);
window.loadCiventralScript('assets/js/usermanagement/user-directory/ui.js' + v);
window.loadCiventralScript('assets/js/usermanagement/user-directory/filters.js' + v);
window.loadCiventralScript('assets/js/usermanagement/user-directory/modal.js' + v, () => {

    function initUserDirectoryModule() {
        if (typeof renderSkeletonTable === 'function') {
            renderSkeletonTable();
        }
        if (typeof fetchUsersData === 'function') {
            fetchUsersData();
        }

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (typeof filterAndSearch === 'function') filterAndSearch();
            });
        }

        const roleFilter = document.getElementById('roleFilter');
        if (roleFilter) {
            roleFilter.addEventListener('change', () => {
                if (typeof filterAndSearch === 'function') filterAndSearch();
            });
        }

        const deptFilter = document.getElementById('deptFilter');
        if (deptFilter) {
            deptFilter.addEventListener('change', () => {
                if (typeof filterAndSearch === 'function') filterAndSearch();
            });
        }

        // Edit Form Submit
        const editStaffForm = document.getElementById('editStaffForm');
        if (editStaffForm) {
            editStaffForm.addEventListener('submit', (e) => {
                if (typeof handleEditStaff === 'function') handleEditStaff(e);
            });
        }

        // Backdrop click for modals
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('mousedown', function(e) {
                if (e.target === this && typeof closeModal === 'function') {
                    closeModal(this.id);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUserDirectoryModule);
    } else {
        initUserDirectoryModule();
    }
});
