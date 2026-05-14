<!-- Toast Container -->
<div id="toastContainer"
     class="toast-container position-fixed bottom-0 end-0 p-3">
</div>

<script>
function showToast(message, type = 'primary') {

    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toastEl = document.createElement('div');

    // icon mapping
    const icons = {
        primary: 'bi-info-circle-fill',
        success: 'bi-check-circle-fill',
        danger:  'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info:    'bi-info-circle-fill'
    };

    const icon = icons[type] || icons.primary;

    toastEl.className = `toast align-items-center toast-${type} border-0`;

    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');

    toastEl.innerHTML = `
        <div class="d-flex align-items-center">

            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi ${icon}"></i>
                <span>${message}</span>
            </div>

            <button type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"
                    aria-label="Close">
            </button>

        </div>
    `;

    container.appendChild(toastEl);

    const toast = new bootstrap.Toast(toastEl, {
        delay: 5000,
        autohide: true
    });

    toast.show();

    toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
    });
}
</script>