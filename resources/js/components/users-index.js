/**
 * Alpine.js Component for Users Index Page
 * Module 1: Users & Customers Management
 */

export default function usersIndex() {
    return {
        // ─── State ───
        filters: {
            mobile: '',
            email: '',
            name: '',
            status: '',
            role: '',
            trashed: '0',
        },
        sortBy: '',
        sortDir: 'asc',
        page: 1,
        pageSize: 25,
        pageSizeOptions: [10, 25, 50, 100],
        selectedIds: [],
        selectAll: false,
        bulkActionModalOpen: false,
        bulkActionType: null,
        openRowId: null,
        isLoading: false,
        isInitialLoading: false,
        searchTimeout: null,
        toast: {
            show: false,
            message: '',
            type: 'success',
        },
        deleteFormUserId: null,
        deleteModalOpen: false,

        // ─── Computed ───
        get hasSelection() {
            return this.selectedIds.length > 0;
        },
        get selectionCount() {
            return this.selectedIds.length;
        },

        // ─── Init ───
        init() {
            const params = new URLSearchParams(window.location.search);
            this.filters.mobile = params.get('mobile') || '';
            this.filters.email = params.get('email') || '';
            this.filters.name = params.get('name') || '';
            this.filters.status = params.get('status') || '';
            this.filters.role = params.get('role') || '';
            this.filters.trashed = params.get('trashed') || '0';
            this.sortBy = params.get('sort') || '';
            this.sortDir = params.get('direction') || 'asc';
            this.page = parseInt(params.get('page')) || 1;
            this.pageSize = parseInt(params.get('perPage')) || 25;

            // Watch filters for auto-search (debounced)
            this.$watch('filters', () => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.search();
                }, 600);
            }, { deep: true });

            // Close dropdown on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-row-dropdown]')) {
                    this.closeRowDropdown();
                }
            });

            // Toast from sessionStorage
            const toastData = window.sessionStorage.getItem('toast');
            if (toastData) {
                try {
                    const t = JSON.parse(toastData);
                    this.showToast(t.message, t.type);
                    window.sessionStorage.removeItem('toast');
                } catch (e) { }
            }
        },

        search() {
            this.page = 1;
            this.applyFilters();
        },

        applyFilters() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) params.set(key, value);
            });
            if (this.sortBy) params.set('sort', this.sortBy);
            if (this.sortDir) params.set('direction', this.sortDir);
            if (this.page > 1) params.set('page', this.page);
            if (this.pageSize !== 25) params.set('perPage', this.pageSize);
            const qs = params.toString();
            window.location.href = qs ? window.location.pathname + '?' + qs : window.location.pathname;
        },

        resetFilters() {
            this.filters = { mobile: '', email: '', name: '', status: '', role: '', trashed: '0' };
            this.sortBy = '';
            this.sortDir = 'asc';
            this.page = 1;
            this.applyFilters();
        },

        toggleSort(field) {
            if (this.sortBy === field) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = field;
                this.sortDir = 'asc';
            }
            this.applyFilters();
        },

        toggleUser(userId) {
            const id = String(userId);
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) this.selectedIds.push(id);
            else this.selectedIds.splice(idx, 1);
            this.updateSelectAll();
        },

        toggleSelectAll() {
            const cbs = document.querySelectorAll('.user-checkbox');
            const checkedCount = this.selectedIds.length;
            const totalCount = cbs.length;
            if (checkedCount === totalCount && totalCount > 0) {
                this.selectedIds = [];
                this.selectAll = false;
            } else {
                this.selectedIds = Array.from(cbs).map(cb => cb.value);
                this.selectAll = true;
            }
        },

        updateSelectAll() {
            const cbs = document.querySelectorAll('.user-checkbox');
            const checked = document.querySelectorAll('.user-checkbox:checked').length;
            this.selectAll = checked === cbs.length && cbs.length > 0;
        },

        openBulkAction(type) {
            if (this.selectedIds.length === 0) return;
            this.bulkActionType = type;
            this.bulkActionModalOpen = true;
        },

        confirmBulkAction() {
            if (!this.bulkActionType || this.selectedIds.length === 0) return;
            const form = document.getElementById('bulk-form');
            if (!form) return;
            document.getElementById('bulk-ids').value = JSON.stringify(this.selectedIds);
            document.getElementById('bulk-action-hidden').value = this.bulkActionType;
            try {
                const routeMap = JSON.parse(form.dataset.routes || '{}');
                form.action = routeMap[this.bulkActionType] || form.action;
            } catch (e) { }
            form.submit();
        },

        cancelBulkAction() {
            this.bulkActionModalOpen = false;
            this.bulkActionType = null;
        },

        openDeleteModal(userId) {
            this.deleteFormUserId = userId;
            this.deleteModalOpen = true;
        },

        confirmDelete() {
            if (!this.deleteFormUserId) return;
            const form = document.getElementById('delete-form-' + this.deleteFormUserId);
            if (form) form.submit();
        },

        cancelDelete() {
            this.deleteModalOpen = false;
            this.deleteFormUserId = null;
        },

        toggleRowDropdown(rowId) {
            this.openRowId = this.openRowId === rowId ? null : rowId;
        },

        closeRowDropdown() {
            this.openRowId = null;
        },

        showToast(message, type = 'success') {
            this.toast.show = true;
            this.toast.message = message;
            this.toast.type = type;
            setTimeout(() => {
                this.toast.show = false;
            }, 5000);
        },

        dismissToast() {
            this.toast.show = false;
        },

        getBulkActionLabel(type) {
            const labels = {
                activate: 'فعال‌سازی',
                deactivate: 'غیرفعال‌سازی',
                delete: 'حذف',
                restore: 'بازیابی',
            };
            return labels[type] || type;
        },

        exportCSV() {
            const params = new URLSearchParams(window.location.search);
            const exportUrl = document.getElementById('export-csv-btn')?.dataset.url;
            if (exportUrl) window.location.href = exportUrl + '?' + params.toString();
        },

        changePageSize(event) {
            this.pageSize = parseInt(event.target.value);
            this.page = 1;
            this.applyFilters();
        },
    };
}
