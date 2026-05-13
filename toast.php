<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">

    <div id="appToast"
         class="toast align-items-center text-bg-primary border-0"
         role="alert"
         aria-live="assertive"
         aria-atomic="true">

        <div class="d-flex">
            <div class="toast-body" id="appToastBody">
                Message goes here
            </div>

            <button type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"
                    aria-label="Close"></button>
        </div>

    </div>

</div>

<script>
function showToast(message, type = 'primary') {
    const toastEl = document.getElementById('appToast');
    const toastBody = document.getElementById('appToastBody');

    if (!toastEl || !toastBody) return;

    console.log("Toast:", message);

    toastBody.textContent = message;

    toastEl.classList.remove(
        'text-bg-primary',
        'text-bg-success',
        'text-bg-danger',
        'text-bg-warning',
        'text-bg-info'
    );

    toastEl.classList.add('toast-' + type);

    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, {
        delay: 10000,
        autohide: true
    });

    toast.show();
}
</script>